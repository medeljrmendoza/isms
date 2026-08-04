<?php

namespace App\Repositories\Nonconformities;

use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\FlagState\FlagStateReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NonconformityRepository
{
    /**
     * These source types get approved as part of their own report
     * workflow (Flag State, PSC, Company Inspection, Internal Audit), so
     * an unapproved NC from one of them doesn't count as "pending" here
     * the way a manually-logged one does.
     */
    private const SOURCES_APPROVED_ELSEWHERE = [
        'FLAG STATE',
        'PSC INSPECTION',
        'COMPANY INSPECTION',
        'INTERNAL AUDIT',
    ];

    /** Matches the legacy DataTable's column list (minus the Actions column, which we don't build). */
    private const COLUMNS = [
        ['key' => 'ncr_no', 'label' => 'NCR NO.', 'sortable' => true],
        ['key' => 'date_of_nc', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => true],
        ['key' => 'description', 'label' => 'DESCRIPTION', 'sortable' => true],
    ];

    /** The full module list's column set — see Controllers/Nonconformities.php's loadData(). */
    private const MODULE_COLUMNS = [
        ['key' => 'ncr_no', 'label' => 'NCR NO.', 'sortable' => true],
        ['key' => 'date_of_nc', 'label' => 'DATE OF NC', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'source_of_nc', 'label' => 'SOURCE', 'sortable' => true],
        ['key' => 'reported_by', 'label' => 'REPORTER', 'sortable' => false],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => false],
        ['key' => 'description', 'label' => 'DESCRIPTION', 'sortable' => true],
        ['key' => 'is_published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
        ['key' => 'status', 'label' => 'STATUS', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_nonconformities.php's loadData()
     * WHERE clause. Not scoped by vessel/user (no Vessels module yet —
     * see conversation notes) and drops the vessel-history name
     * resolution; otherwise the same "in progress or for approval" rule:
     *
     * - Vessel-added: still open, OR unapproved-and-not-a-special-source
     * - Shore-added, unpublished: still open
     * - Shore-added, published: still open, OR unapproved-and-not-special
     * - Always excludes inactive (soft-deleted) records
     */
    public function pendingQuery(): Builder
    {
        return Nonconformity::query()
            ->with('vessel')
            ->where('is_inactive', false)
            ->where(function (Builder $query) {
                $query->where(function (Builder $vessel) {
                    $vessel->where('added_by', 'VESSEL')
                        ->where(fn (Builder $q) => $this->openOrUnapproved($q));
                })->orWhere(function (Builder $shore) {
                    $shore->where('added_by', 'SHORE')
                        ->where(function (Builder $publishBranch) {
                            $publishBranch
                                ->where(function (Builder $unpublished) {
                                    $unpublished->where('is_published', false)
                                        ->whereNull('close_out_date');
                                })
                                ->orWhere(function (Builder $published) {
                                    $published->where('is_published', true)
                                        ->where(fn (Builder $q) => $this->openOrUnapproved($q));
                                });
                        });
                });
            });
    }

    public function pending(): Collection
    {
        return $this->pendingQuery()->orderByDesc('date_of_nc')->get();
    }

    /**
     * Same "pending" filter, with search/sort/pagination applied on top
     * for the dashlet's interactive table.
     */
    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('ncr_no', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('date_of_nc', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_nc';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Reads the dashlet's "pending" set from the real legacy staging
     * database instead of local seed data — see App\Support\LegacyDb.
     * Same open-or-unapproved rule as pendingQuery(), replicated directly
     * in SQL since tb_nonconformities' column names match 1:1. Also
     * ported: legacy's `(vesID = '' OR vesID IN (SELECT vesID FROM
     * tb_user_vessel WHERE userID = ...))` scoping, which restricts
     * results to vessels assigned to the logged-in user (company/shore
     * records with no vessel, vesID = '', are always visible).
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->where('is_inactive', 0)
            ->where(function ($q) {
                $q->where(function ($vessel) {
                    $vessel->where('added_by', 'VESSEL')->where(fn ($q2) => $this->legacyOpenOrUnapproved($q2));
                })->orWhere(function ($shore) {
                    $shore->where('added_by', 'SHORE')->where(function ($publishBranch) {
                        $publishBranch->where(function ($unpublished) {
                            $unpublished->where('is_published', 0)->where('close_out_date', '0000-00-00');
                        })->orWhere(function ($published) {
                            $published->where('is_published', 1)->where(fn ($q2) => $this->legacyOpenOrUnapproved($q2));
                        });
                    });
                });
            })
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('vesID', '')->orWhereIn('vesID', $assignedVesselIds);
            });

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('ncr_no', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('date_of_nc', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_nc';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($nc) => [
            'record_id' => $nc->ncID,
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $nc->date_of_nc,
            'vessel_company' => $nc->vessel_company === 'VESSEL' ? ($vessels[$nc->vesID] ?? '') : ($nc->company ?? ''),
            'description' => $nc->description,
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Powers the dashlet's "click NCR No. to view" — ported from
     * Nonconformities::view_item()'s field set (see
     * Views/admin/nonconformities/view_nonconformity.php), minus the
     * report header/footer and file attachments (both already dropped
     * everywhere else in this migration) and the tb_logs write. The
     * list-only computed fields (status/is_published/is_approved/can_*)
     * are inert placeholders — this view has no action buttons.
     */
    public function detail(int $id): ?array
    {
        $nc = Nonconformity::query()->with(['vessel', 'manualChapter'])->find($id);

        if ($nc === null) {
            return null;
        }

        return $this->toDetailArray([
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $nc->date_of_nc->format('Y-m-d'),
            'added_by' => $nc->added_by,
            'source_of_nc' => $nc->source_of_nc,
            'reported_by' => $nc->reported_by,
            'reporter_name' => $nc->reporter_name,
            'vessel_or_company_name' => $nc->vessel_company === 'VESSEL' ? ($nc->vessel?->display_name ?? '') : ($nc->company ?? ''),
            'vessel_company' => $nc->vessel_company,
            'company' => $nc->company,
            'department_name' => $nc->department_name,
            'source_of_nc_others' => $nc->source_of_nc_others,
            'source_of_nc_ref_no' => $nc->source_of_nc_ref_no,
            'manual_chapter_label' => $nc->manualChapter ? "({$nc->manualChapter->reference_no}) {$nc->manualChapter->chapter_name}" : null,
            'sms_details' => $nc->sms_details,
            'description' => $nc->description,
            'root_cause' => $nc->root_cause,
            'root_cause_incharge' => $nc->root_cause_incharge,
            'corrective_action' => $nc->corrective_action,
            'corrective_action_incharge' => $nc->corrective_action_incharge,
            'corrective_action_date' => $nc->corrective_action_date?->format('Y-m-d'),
            'verification' => $nc->verification,
            'verification_followup' => $nc->verification_followup,
            'verification_assistance' => $nc->verification_assistance,
            'verification_dpa' => $nc->verification_dpa,
            'verification_date' => $nc->verification_date?->format('Y-m-d'),
            'close_out_completed' => $nc->close_out_completed,
            'close_out_followup' => $nc->close_out_followup,
            'close_out_followup_nature' => $nc->close_out_followup_nature,
            'close_out_dpa' => $nc->close_out_dpa,
            'close_out_date' => $nc->close_out_date?->format('Y-m-d'),
            'attach_safety_meeting' => $nc->attach_safety_meeting,
            'attach_record_training' => $nc->attach_record_training,
            'attach_logbook' => $nc->attach_logbook,
            'attach_delivery_note' => $nc->attach_delivery_note,
            'attach_photo' => $nc->attach_photo,
            'attach_company_forms' => $nc->attach_company_forms,
            'attach_others' => $nc->attach_others,
            'attach_others_details' => $nc->attach_others_details,
        ]);
    }

    /** Same as detail(), reading tb_nonconformities directly from the legacy connection. */
    public function legacyDetail(string $ncID): ?array
    {
        $nc = DB::connection('legacy')->table('tb_nonconformities')->where('ncID', $ncID)->first();

        if ($nc === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $chapter = $nc->sms_chapterID !== ''
            ? DB::connection('legacy')->table('tb_manual_chapter')->where('chapterID', $nc->sms_chapterID)->first()
            : null;

        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        return $this->toDetailArray([
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $zeroDateToNull($nc->date_of_nc),
            'added_by' => $nc->added_by,
            'source_of_nc' => $nc->source_of_nc,
            'reported_by' => $nc->reported_by,
            'reporter_name' => $nc->reporter_name,
            'vessel_or_company_name' => $nc->vessel_company === 'VESSEL' ? ($vessels[$nc->vesID] ?? '') : ($nc->company ?? ''),
            'vessel_company' => $nc->vessel_company,
            'company' => $nc->company,
            'department_name' => $nc->department_name,
            'source_of_nc_others' => $nc->source_of_nc_others,
            'source_of_nc_ref_no' => $nc->source_of_nc_ref_no,
            'manual_chapter_label' => $chapter ? "({$chapter->reference_no}) {$chapter->chapter_name}" : null,
            'sms_details' => $nc->sms_details,
            'description' => $nc->description,
            'root_cause' => $nc->root_cause,
            'root_cause_incharge' => $nc->root_cause_incharge,
            'corrective_action' => $nc->corrective_action,
            'corrective_action_incharge' => $nc->corrective_action_incharge,
            'corrective_action_date' => $zeroDateToNull($nc->corrective_action_date),
            'verification' => $nc->verification !== '' ? $nc->verification : null,
            'verification_followup' => $nc->verification_followup,
            'verification_assistance' => $nc->verification_assistance,
            'verification_dpa' => $nc->verification_dpa,
            'verification_date' => $zeroDateToNull($nc->verification_date),
            'close_out_completed' => $nc->close_out_completed,
            'close_out_followup' => $nc->close_out_followup,
            'close_out_followup_nature' => $nc->close_out_followup_nature,
            'close_out_dpa' => $nc->close_out_dpa,
            'close_out_date' => $zeroDateToNull($nc->close_out_date),
            'attach_safety_meeting' => $nc->attach_safety_meeting,
            'attach_record_training' => $nc->attach_record_training,
            'attach_logbook' => $nc->attach_logbook,
            'attach_delivery_note' => $nc->attach_delivery_note,
            'attach_photo' => $nc->attach_photo,
            'attach_company_forms' => $nc->attach_company_forms,
            'attach_others' => $nc->attach_others,
            'attach_others_details' => $nc->attach_others_details,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => 0,
            'ncr_no' => $r['ncr_no'],
            'date_of_nc' => $r['date_of_nc'],
            'added_by' => $r['added_by'],
            'source_of_nc' => $r['source_of_nc'],
            'reported_by' => trim("{$r['reported_by']} - {$r['reporter_name']}", ' -'),
            'vessel_company' => $r['vessel_or_company_name'],
            'description' => $r['description'],
            'is_published' => null,
            'is_approved' => null,
            'status' => 'IN PROGRESS',
            'status_color' => 'yellow',
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_reopen' => false,
            'vessel_id' => null,
            'vessel_company_raw' => $r['vessel_company'],
            'company' => $r['company'],
            'department_name' => $r['department_name'],
            'reported_by_raw' => $r['reported_by'],
            'reporter_name' => $r['reporter_name'],
            'source_of_nc_raw' => $r['source_of_nc'],
            'source_of_nc_others' => $r['source_of_nc_others'],
            'source_of_nc_ref_no' => $r['source_of_nc_ref_no'],
            'manual_chapter_id' => null,
            'manual_chapter_label' => $r['manual_chapter_label'],
            'sms_details' => $r['sms_details'],
            'root_cause' => $r['root_cause'],
            'root_cause_incharge' => $r['root_cause_incharge'],
            'corrective_action' => $r['corrective_action'],
            'corrective_action_incharge' => $r['corrective_action_incharge'],
            'corrective_action_date' => $r['corrective_action_date'],
            'verification' => $r['verification'],
            'verification_followup' => $r['verification_followup'],
            'verification_assistance' => $r['verification_assistance'],
            'verification_dpa' => $r['verification_dpa'],
            'verification_date' => $r['verification_date'],
            'close_out_completed' => (bool) $r['close_out_completed'],
            'close_out_followup' => (bool) $r['close_out_followup'],
            'close_out_followup_nature' => $r['close_out_followup_nature'],
            'close_out_dpa' => $r['close_out_dpa'],
            'close_out_date' => $r['close_out_date'],
            'attach_safety_meeting' => (bool) $r['attach_safety_meeting'],
            'attach_record_training' => (bool) $r['attach_record_training'],
            'attach_logbook' => (bool) $r['attach_logbook'],
            'attach_delivery_note' => (bool) $r['attach_delivery_note'],
            'attach_photo' => (bool) $r['attach_photo'],
            'attach_company_forms' => (bool) $r['attach_company_forms'],
            'attach_others' => (bool) $r['attach_others'],
            'attach_others_details' => $r['attach_others_details'],
        ];
    }

    /**
     * The legacy table stores an empty close_out_date as the zero-date
     * sentinel '0000-00-00' (column is NOT NULL) rather than true NULL —
     * matches the same convention seen throughout legacy's PHP source.
     */
    private function legacyOpenOrUnapproved($query)
    {
        return $query->where('close_out_date', '0000-00-00')
            ->orWhere(function ($unapproved) {
                $unapproved->where('is_approved', 0)
                    ->whereNotIn('source_of_nc', self::SOURCES_APPROVED_ELSEWHERE);
            });
    }

    private function openOrUnapproved(Builder $query): Builder
    {
        return $query->whereNull('close_out_date')
            ->orWhere(function (Builder $unapproved) {
                $unapproved->where('is_approved', false)
                    ->whereNotIn('source_of_nc', self::SOURCES_APPROVED_ELSEWHERE);
            });
    }

    /**
     * Ported from Controllers/Nonconformities.php's loadData() — the full
     * module list (every non-inactive record, not just "pending" ones).
     * The `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is
     * dropped like everywhere else in this migration (see conversation
     * notes on deferred vessel/user permission scoping); the
     * vessel/company + date-range filters are kept since those are
     * genuine user-facing filters, not permission scoping.
     */
    public function fullTable(TableQuery $query, ?string $vesselOrCompany, ?string $dateFrom, ?string $dateTo): LengthAwarePaginator
    {
        $builder = Nonconformity::query()
            ->with(['vessel', 'manualChapter'])
            ->where('is_inactive', false);

        if ($vesselOrCompany === 'COMPANY') {
            $builder->where('vessel_company', 'COMPANY');
        } elseif ($vesselOrCompany !== null && $vesselOrCompany !== 'ALL' && $vesselOrCompany !== '') {
            $builder->where('vessel_id', $vesselOrCompany);
        }

        if ($dateFrom !== null && $dateTo !== null) {
            $builder->whereBetween('date_of_nc', [$dateFrom, $dateTo]);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('ncr_no', 'like', $term)
                    ->orWhere('date_of_nc', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhere('source_of_nc', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_nc';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Nonconformities.php's save_item() insert
     * branch. Our Add form only offers OPERATIONAL/OTHERS as a source
     * (the FLAG STATE/EXTERNAL AUDIT/etc. auto-populated sources are set
     * from those modules' own "add related NC" actions, which haven't
     * been built yet), so the auto-is_published lookups below mostly
     * apply if this is reused once that integration exists.
     */
    public function create(array $data): Nonconformity
    {
        $sourceOfNc = $data['source_of_nc'];
        $isPublished = match ($sourceOfNc) {
            'FLAG STATE' => (bool) FlagStateReport::query()->where('ref_no', $data['source_of_nc_ref_no'] ?? null)->value('is_published'),
            'EXTERNAL AUDIT' => (bool) ExternalAuditReport::query()->where('ref_no', $data['source_of_nc_ref_no'] ?? null)->value('is_published'),
            default => false,
        };

        $nonconformity = Nonconformity::create([
            ...$data,
            'added_by' => 'SHORE',
            'is_published' => $isPublished,
            'is_approved' => false,
            'is_inactive' => false,
        ]);

        $this->cascadeSourceApprovalReset($nonconformity);

        return $nonconformity;
    }

    /**
     * Ported from save_item()'s edit branch: vessel/company attribution
     * and added_by are frozen at creation time and not accepted from the
     * update payload; every edit resets is_approved back to false
     * (matches legacy exactly — editing an NC means it needs
     * re-approval, not a bug).
     */
    public function update(Nonconformity $nonconformity, array $data): Nonconformity
    {
        unset($data['vessel_company'], $data['vessel_id'], $data['added_by'], $data['is_published'], $data['is_inactive']);

        $nonconformity->update([...$data, 'is_approved' => false]);

        $this->cascadeSourceApprovalReset($nonconformity);

        return $nonconformity;
    }

    /**
     * Ported from publish_nonconformity(): toggles is_published, and —
     * matching legacy exactly, in both directions — also sets
     * is_approved to true. Only meaningful for SHORE-added,
     * vessel-attributed records from a source this record owns its own
     * publish workflow for.
     */
    public function publish(Nonconformity $nonconformity): Nonconformity
    {
        $nonconformity->update([
            'is_published' => ! $nonconformity->is_published,
            'is_approved' => true,
        ]);

        return $nonconformity;
    }

    /**
     * Ported from approve_nonconformity(). Legacy's `/*if($added_by ==
     * "SHORE")*\/` is commented out in the source, i.e. approval applies
     * to vessel-attributed records regardless of who added them —
     * replicated as-is via the controller's canApprove() gate.
     */
    public function approve(Nonconformity $nonconformity): Nonconformity
    {
        $nonconformity->update(['is_approved' => true]);

        return $nonconformity;
    }

    /** Ported from reopen_nonconformity(). */
    public function reopen(Nonconformity $nonconformity): Nonconformity
    {
        $nonconformity->update(['is_approved' => false, 'close_out_date' => null]);

        $this->cascadeSourceApprovalReset($nonconformity);

        return $nonconformity;
    }

    /**
     * Ported from delete_nonconformity() (a soft-delete via is_inactive,
     * not a real row delete). The reverse "Activate" branch of legacy's
     * edit_stat() isn't ported: every list query — this module's and the
     * dashlet's — excludes is_inactive rows unconditionally, so that
     * button could never actually be reached from anywhere in the UI.
     */
    public function delete(Nonconformity $nonconformity): Nonconformity
    {
        $nonconformity->update(['is_inactive' => true]);

        return $nonconformity;
    }

    /**
     * Flag State / External Audit reports drive their own approval, but
     * an NC sourced from one of them being saved/reopened/edited means
     * that report needs shore's attention again. Matches legacy's
     * save_item()/reopen_nonconformity() cascade exactly (edit_stat and
     * delete_nonconformity do NOT carry this cascade in legacy, so
     * neither do our delete()/publish()/approve() here).
     */
    private function cascadeSourceApprovalReset(Nonconformity $nonconformity): void
    {
        if ($nonconformity->source_of_nc === 'FLAG STATE') {
            FlagStateReport::query()->where('ref_no', $nonconformity->source_of_nc_ref_no)->update(['is_approved' => false]);
        }

        if ($nonconformity->source_of_nc === 'EXTERNAL AUDIT') {
            ExternalAuditReport::query()->where('ref_no', $nonconformity->source_of_nc_ref_no)->update(['is_approved' => false]);
        }
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
