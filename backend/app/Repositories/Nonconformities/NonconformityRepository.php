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

    /** Powers the View modal and the Add/Edit form's prefill — ported from Nonconformities::view_item()'s field set, minus report header/footer and file attachments (both already dropped everywhere else in this migration) and the tb_logs write. */
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

        return $this->toDetailArray($nc->ncID, [
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $zeroDateToNull($nc->date_of_nc),
            'added_by' => $nc->added_by,
            'source_of_nc' => $nc->source_of_nc,
            'reported_by' => $nc->reported_by,
            'reporter_name' => $nc->reporter_name,
            'vessel_or_company_name' => $nc->vessel_company === 'VESSEL' ? ($vessels[$nc->vesID] ?? '') : ($nc->company ?? ''),
            'vessel_company' => $nc->vessel_company,
            'vesID' => $nc->vesID,
            'company' => $nc->company,
            'department_name' => $nc->department_name,
            'source_of_nc_others' => $nc->source_of_nc_others,
            'source_of_nc_ref_no' => $nc->source_of_nc_ref_no,
            'sms_chapterID' => $nc->sms_chapterID,
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
        ], $this->computeFlags($nc));
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(string $ncID, array $r, array $flags): array
    {
        return [
            'id' => $ncID,
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
            ...$flags,
            'vessel_id' => $r['vesID'] !== '' ? $r['vesID'] : null,
            'vessel_company_raw' => $r['vessel_company'],
            'company' => $r['company'],
            'department_name' => $r['department_name'],
            'reported_by_raw' => $r['reported_by'],
            'reporter_name' => $r['reporter_name'],
            'source_of_nc_raw' => $r['source_of_nc'],
            'source_of_nc_others' => $r['source_of_nc_others'],
            'source_of_nc_ref_no' => $r['source_of_nc_ref_no'],
            'manual_chapter_id' => $r['sms_chapterID'] !== '' ? $r['sms_chapterID'] : null,
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
     * Ported verbatim from loadData()'s `ncID` column callback — the
     * legacy Actions-column button visibility logic. The five sources
     * in SOURCES_APPROVED_ELSEWHERE also blank out Publish/Approve AND
     * Inactivate (all three sit inside the same source_of_nc branch in
     * the legacy PHP, not just Publish/Approve as it first appears).
     * user_level (MEMBER vs SUPERADMIN/BTSOLVE) gating is intentionally
     * dropped, matching the no-roles-yet precedent used by every other
     * write-enabled module in this migration (Defects, PMS Department/
     * Classifications, ExposureHours, Drills).
     */
    private function computeFlags(object $nc): array
    {
        $isInactive = self::isFlagSet($nc->is_inactive);
        $isClosed = self::isFlagSet($nc->status);
        $addedByShore = $nc->added_by === 'SHORE';
        $hasVessel = $nc->vesID !== '';
        $isPublished = self::isFlagSet($nc->is_published);
        $isApproved = self::isFlagSet($nc->is_approved);
        // SOURCES_APPROVED_ELSEWHERE covers 4 of the 5 legacy special sources; EXTERNAL AUDIT is the 5th (loadData()'s ncID column checks all five).
        $specialSource = in_array($nc->source_of_nc, self::SOURCES_APPROVED_ELSEWHERE, true) || $nc->source_of_nc === 'EXTERNAL AUDIT';

        $canEdit = ! $isClosed && ! $isInactive;

        if ($specialSource) {
            $publishAction = null;
            $inactiveAction = null;
            $canApprove = false;
        } else {
            $publishAction = ($addedByShore && $hasVessel && ! $isInactive) ? ($isPublished ? 'unpublish' : 'publish') : null;
            $inactiveAction = $addedByShore ? ($isInactive ? 'activate' : 'inactivate') : null;
            $canApprove = $hasVessel && $isPublished && ! $isInactive && ! $isApproved;
        }

        return [
            'can_edit' => $canEdit,
            'can_approve' => $canApprove,
            'can_reopen' => $isClosed,
            'can_delete' => true,
            'publish_action' => $publishAction,
            'inactive_action' => $inactiveAction,
        ];
    }

    /**
     * tb_nonconformities' is_inactive/is_published/is_approved/status
     * flag columns are real SQL `int` columns, and this connection's PDO
     * returns native-typed ints for them (not the all-strings behavior
     * some MySQL PDO configurations default to) — so comparing against
     * the string '1' silently and always fails. Every flag read in this
     * class goes through here instead of a raw `=== '1'`.
     */
    private static function isFlagSet(mixed $value): bool
    {
        return (string) $value === '1';
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
     * every dashlet's legacy path.
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
        $isPublished = self::isFlagSet($nc->is_published);
        $isApproved = self::isFlagSet($nc->is_approved);
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
            ...$this->computeFlags($nc),
        ];
    }

    /**
     * Ported from save_item(): create (ncID empty) and edit share one
     * delete-then-reinsert save. Only reachable with source_of_nc
     * OPERATIONAL/OTHERS from the Add form (the other 5 sources are
     * auto-generated from their own modules and never exposed on this
     * form) and edits of a FLAG STATE-sourced record carry that source
     * through unchanged (locked in the frontend, see NonconformityForm).
     * added_by/is_inactive/is_published/module are frozen from the
     * existing row on edit, exactly like legacy's `$ncID == ""` branch.
     * is_approved is unconditionally reset to '0' on every save, per
     * legacy — editing an approved record un-approves it. Not ported:
     * tb_logs audit write and S3 file upload (no infra anywhere in this
     * migration — see class docblock precedent in DefectRepository).
     */
    public function legacySave(?string $ncID, array $data): array
    {
        $legacy = DB::connection('legacy');
        $isEdit = $ncID !== null;
        $newNcId = $ncID ?? ('nc'.uniqid());
        $existing = $isEdit ? $legacy->table('tb_nonconformities')->where('ncID', $newNcId)->first() : null;

        if ($isEdit && $existing === null) {
            abort(404);
        }

        if ($isEdit) {
            $vesselCompany = $existing->vessel_company;
            $vesId = $existing->vesID;
            $company = $data['company'] ?? '';
            $addedBy = $existing->added_by;
            $isInactive = $existing->is_inactive;
            $isPublished = $existing->is_published;
            $module = $existing->module;
            $sourceOfNc = $data['source_of_nc'];
        } else {
            $vesselCompany = $data['vessel_company'];
            $vesId = $vesselCompany === 'VESSEL' ? $data['vessel_id'] : '';
            $company = $vesselCompany === 'VESSEL' ? '' : ($data['company'] ?? '');
            $addedBy = 'SHORE';
            $isInactive = '0';
            $isPublished = '0';
            $module = 'nonconformities';
            $sourceOfNc = $data['source_of_nc'];
        }

        $closeOutDate = $data['close_out_date'] ?? '';
        $status = ($closeOutDate !== '' && $closeOutDate !== '0000-00-00') ? '1' : '0';

        $row = [
            'ncID' => $newNcId,
            'added_by' => $addedBy,
            'module' => $module,
            'ncr_no' => $data['ncr_no'],
            'date_of_nc' => $data['date_of_nc'],
            'vessel_company' => $vesselCompany,
            'vesID' => $vesId,
            'company' => $company,
            'department_name' => $data['department_name'] ?? '',
            'reported_by' => $data['reported_by'],
            'reporter_name' => $data['reporter_name'] ?? '',
            'source_of_nc' => $sourceOfNc,
            'source_of_nc_others' => $data['source_of_nc_others'] ?? '',
            'source_of_nc_ref_no' => $data['source_of_nc_ref_no'] ?? '',
            'sms_chapterID' => $data['sms_chapterID'] ?? '',
            'sms_details' => $data['sms_details'] ?? '',
            'description' => $data['description'],
            'root_cause' => $data['root_cause'] ?? '',
            'root_cause_incharge' => $data['root_cause_incharge'] ?? '',
            'corrective_action' => $data['corrective_action'] ?? '',
            'corrective_action_incharge' => $data['corrective_action_incharge'] ?? '',
            'corrective_action_date' => $data['corrective_action_date'] ?? '',
            'verification' => $data['verification'] ?? '',
            'verification_followup' => $data['verification_followup'] ?? '',
            'verification_assistance' => $data['verification_assistance'] ?? '',
            'verification_dpa' => $data['verification_dpa'] ?? '',
            'verification_date' => $data['verification_date'] ?? '',
            'close_out_completed' => $data['close_out_completed'] ?? '0',
            'close_out_followup' => $data['close_out_followup'] ?? '0',
            'close_out_followup_nature' => $data['close_out_followup_nature'] ?? '',
            'close_out_dpa' => $data['close_out_dpa'] ?? '',
            'close_out_date' => $closeOutDate,
            'attach_safety_meeting' => $data['attach_safety_meeting'] ?? '0',
            'attach_record_training' => $data['attach_record_training'] ?? '0',
            'attach_logbook' => $data['attach_logbook'] ?? '0',
            'attach_delivery_note' => $data['attach_delivery_note'] ?? '0',
            'attach_photo' => $data['attach_photo'] ?? '0',
            'attach_company_forms' => $data['attach_company_forms'] ?? '0',
            'attach_others' => $data['attach_others'] ?? '0',
            'attach_others_details' => $data['attach_others_details'] ?? '',
            'status' => $status,
            'is_inactive' => $isInactive,
            'is_published' => $isPublished,
            'is_approved' => '0',
        ];

        $legacy->table('tb_nonconformities')->where('ncID', $newNcId)->delete();
        $legacy->table('tb_nonconformities')->insert($row);

        if ($sourceOfNc === 'FLAG STATE') {
            $legacy->table('tb_flag_state')->where('ref_no', $data['source_of_nc_ref_no'] ?? '')->update(['is_approved' => '0']);
        }
        if ($sourceOfNc === 'EXTERNAL AUDIT') {
            $legacy->table('tb_external_audit_report')->where('ref_no', $data['source_of_nc_ref_no'] ?? '')->update(['is_approved' => '0']);
        }

        return $this->legacyDetail($newNcId);
    }

    /** Ported from edit_stat(): toggles is_inactive, and unconditionally resets a linked Flag State/External Audit record's approval — matches legacy exactly, including that this reset fires regardless of which direction the toggle went. */
    public function legacyToggleInactive(string $ncID): array
    {
        $legacy = DB::connection('legacy');
        $nc = $legacy->table('tb_nonconformities')->where('ncID', $ncID)->first();
        abort_if($nc === null, 404);

        $legacy->table('tb_nonconformities')->where('ncID', $ncID)->update([
            'is_inactive' => self::isFlagSet($nc->is_inactive) ? '0' : '1',
        ]);

        $this->resetLinkedApproval($nc);

        return $this->legacyDetail($ncID);
    }

    /** Ported from publish_nonconformity(): delete-then-reinsert toggling is_published, forcing is_approved to '1' regardless of direction (a legacy quirk, kept as-is). */
    public function legacyTogglePublish(string $ncID): array
    {
        $legacy = DB::connection('legacy');
        $nc = $legacy->table('tb_nonconformities')->where('ncID', $ncID)->first();
        abort_if($nc === null, 404);

        $row = (array) $nc;
        $row['is_published'] = self::isFlagSet($nc->is_published) ? '0' : '1';
        $row['is_approved'] = '1';

        $legacy->table('tb_nonconformities')->where('ncID', $ncID)->delete();
        $legacy->table('tb_nonconformities')->insert($row);

        return $this->legacyDetail($ncID);
    }

    /** Ported from approve_nonconformity(): delete-then-reinsert forcing is_approved to '1'. */
    public function legacyApprove(string $ncID): array
    {
        $legacy = DB::connection('legacy');
        $nc = $legacy->table('tb_nonconformities')->where('ncID', $ncID)->first();
        abort_if($nc === null, 404);

        $row = (array) $nc;
        $row['is_approved'] = '1';

        $legacy->table('tb_nonconformities')->where('ncID', $ncID)->delete();
        $legacy->table('tb_nonconformities')->insert($row);

        return $this->legacyDetail($ncID);
    }

    /** Ported from reopen_nonconformity(): plain UPDATE resetting is_approved/close_out_date/status, plus the same linked Flag State/External Audit reset as toggleInactive(). */
    public function legacyReopen(string $ncID): array
    {
        $legacy = DB::connection('legacy');
        $nc = $legacy->table('tb_nonconformities')->where('ncID', $ncID)->first();
        abort_if($nc === null, 404);

        $legacy->table('tb_nonconformities')->where('ncID', $ncID)->update([
            'is_approved' => '0',
            'close_out_date' => '0000-00-00',
            'status' => '0',
        ]);

        $this->resetLinkedApproval($nc);

        return $this->legacyDetail($ncID);
    }

    /** Ported from delete_nonconformity(): soft-delete via is_inactive, not a real SQL delete — tb_nonconformities has no hard-delete path in legacy. */
    public function legacyDelete(string $ncID): array
    {
        $legacy = DB::connection('legacy');
        abort_if($legacy->table('tb_nonconformities')->where('ncID', $ncID)->doesntExist(), 404);

        $legacy->table('tb_nonconformities')->where('ncID', $ncID)->update(['is_inactive' => '1']);

        return $this->legacyDetail($ncID);
    }

    private function resetLinkedApproval(object $nc): void
    {
        $legacy = DB::connection('legacy');
        if ($nc->source_of_nc === 'FLAG STATE') {
            $legacy->table('tb_flag_state')->where('ref_no', $nc->source_of_nc_ref_no)->update(['is_approved' => '0']);
        }
        if ($nc->source_of_nc === 'EXTERNAL AUDIT') {
            $legacy->table('tb_external_audit_report')->where('ref_no', $nc->source_of_nc_ref_no)->update(['is_approved' => '0']);
        }
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyChapterOptions(): array
    {
        return DB::connection('legacy')->table('tb_manual_chapter')->orderBy('reference_no')->get()
            ->map(fn ($c) => ['id' => $c->chapterID, 'label' => "({$c->reference_no}) {$c->chapter_name}"])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }
}
