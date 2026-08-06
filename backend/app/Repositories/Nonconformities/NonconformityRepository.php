<?php

namespace App\Repositories\Nonconformities;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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

    /** Powers the dashlet's "click NCR No. to view" — ported from Nonconformities::view_item()'s field set, minus report header/footer and file attachments (both already dropped everywhere else in this migration) and the tb_logs write. */
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

    /**
     * Ported from Controllers/Nonconformities.php's loadData() — the full
     * module list (every non-inactive record, not just "pending" ones),
     * reading tb_nonconformities directly from the legacy connection.
     * Keeps legacy's vesID-in-assigned-vessels scoping — a real legacy
     * user should only see their own fleet's non-conformities, same as
     * every dashlet's legacy path. Read-only: can_edit/can_publish/
     * can_approve/can_reopen are always false — there is no write path.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselOrCompany, ?string $dateFrom, ?string $dateTo, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->where('is_inactive', '!=', '1')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('vesID', '')->orWhereIn('vesID', $assignedVesselIds);
            });

        if ($vesselOrCompany === 'COMPANY') {
            $builder->where('vessel_company', 'COMPANY');
        } elseif ($vesselOrCompany !== null && $vesselOrCompany !== 'ALL' && $vesselOrCompany !== '') {
            $builder->where('vesID', $vesselOrCompany);
        }

        if ($dateFrom !== null && $dateTo !== null) {
            $builder->whereBetween('date_of_nc', [$dateFrom, $dateTo]);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('ncr_no', 'like', $term)
                    ->orWhere('date_of_nc', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhere('source_of_nc', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('company', 'like', $term);
            });
        }

        $sortMap = [
            'ncr_no' => 'ncr_no',
            'date_of_nc' => 'date_of_nc',
            'added_by' => 'added_by',
            'source_of_nc' => 'source_of_nc',
            'description' => 'description',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'date_of_nc';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($nc) => $this->legacyRow($nc, $vessels))->all();

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

    /** @param array<string, string> $vessels */
    private function legacyRow(object $nc, array $vessels): array
    {
        $approvedElsewhere = in_array($nc->source_of_nc, self::SOURCES_APPROVED_ELSEWHERE, true);
        $hasVessel = $nc->vesID !== '';
        $isPublished = $nc->is_published === '1';
        $isApproved = $nc->is_approved === '1';
        $isClosed = $nc->close_out_date !== '0000-00-00' && $nc->close_out_date !== null && $nc->close_out_date !== '';

        $publishedDisplay = match (true) {
            $approvedElsewhere => null,
            $nc->added_by === 'SHORE' => $hasVessel ? $isPublished : null,
            default => true,
        };

        $approvedDisplay = ($approvedElsewhere || ! $hasVessel || ! $isPublished) ? null : $isApproved;

        $statusColor = match (true) {
            ! $isClosed => 'yellow',
            $hasVessel && $isPublished && ! $isApproved => 'yellow',
            default => 'green',
        };

        $sourceLabel = match ($nc->source_of_nc) {
            'OPERATIONAL' => 'NC - OPERATIONAL',
            'OTHERS' => "NC - OTHERS ({$nc->source_of_nc_others})",
            default => $nc->source_of_nc,
        };

        return [
            'id' => $nc->ncID,
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $nc->date_of_nc,
            'added_by' => $nc->added_by,
            'source_of_nc' => $sourceLabel,
            'reported_by' => trim("{$nc->reported_by} - {$nc->reporter_name}", ' -'),
            'vessel_company' => $nc->vessel_company === 'VESSEL' ? ($vessels[$nc->vesID] ?? '') : ($nc->company ?? ''),
            'description' => $nc->description,
            'is_published' => $publishedDisplay,
            'is_approved' => $approvedDisplay,
            'status' => $isClosed ? 'CLOSED' : 'IN PROGRESS',
            'status_color' => $statusColor,
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_reopen' => false,
        ];
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }
}
