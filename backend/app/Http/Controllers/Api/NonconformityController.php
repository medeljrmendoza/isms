<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NonconformityRequest;
use App\Models\Nonconformity;
use App\Repositories\NonconformityRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Nonconformities.php. Not ported: file
 * attachment upload/S3 storage, the tb_logs audit trail, and
 * user_level-gated button visibility (no roles system exists yet — see
 * NonconformityRepository docblocks and the conversation's standing
 * decision to defer permission scoping everywhere). The
 * report_nonconformities_v.php drill-down (NCs filtered by source
 * ref-no, reached from another report's own page) is deferred too:
 * nothing in this app links into it yet since those other modules don't
 * have their own full pages built.
 */
class NonconformityController extends Controller
{
    public function __construct(private readonly NonconformityRepository $nonconformities)
    {
    }

    /**
     * GET /api/nonconformities
     */
    public function index(Request $request): JsonResponse
    {
        $vesselOrCompany = $request->query('vessel_company');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $paginator = $this->nonconformities->fullTable(
            TableQuery::fromRequest($request),
            $vesselOrCompany !== '' ? $vesselOrCompany : null,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
        );

        return response()->json([
            'data' => [
                'columns' => NonconformityRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (Nonconformity $nc) => $this->mapRow($nc))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/nonconformities/options — vessel list + SMS chapter list for the add/edit form.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->nonconformities->vesselOptions(),
                'manual_chapters' => \App\Models\ManualChapter::query()->orderBy('reference_no')
                    ->get()->map(fn ($c) => ['id' => $c->id, 'label' => "({$c->reference_no}) {$c->chapter_name}"])->all(),
            ],
        ]);
    }

    /**
     * GET /api/nonconformities/{nonconformity}
     */
    public function show(Nonconformity $nonconformity): JsonResponse
    {
        $nonconformity->load(['vessel', 'manualChapter']);

        return response()->json(['data' => $this->mapDetail($nonconformity)]);
    }

    /**
     * POST /api/nonconformities
     */
    public function store(NonconformityRequest $request): JsonResponse
    {
        $nonconformity = $this->nonconformities->create($request->validated());

        return response()->json(['data' => $this->mapDetail($nonconformity)], 201);
    }

    /**
     * PUT /api/nonconformities/{nonconformity}
     */
    public function update(NonconformityRequest $request, Nonconformity $nonconformity): JsonResponse
    {
        if (! $this->canEdit($nonconformity)) {
            abort(422, 'This non-conformity can no longer be edited.');
        }

        $nonconformity = $this->nonconformities->update($nonconformity, $request->validated());

        return response()->json(['data' => $this->mapDetail($nonconformity)]);
    }

    /**
     * DELETE /api/nonconformities/{nonconformity}
     */
    public function destroy(Nonconformity $nonconformity): JsonResponse
    {
        $this->nonconformities->delete($nonconformity);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/publish
     */
    public function publish(Nonconformity $nonconformity): JsonResponse
    {
        if (! $this->canPublish($nonconformity)) {
            abort(422, 'This non-conformity cannot be published/unpublished.');
        }

        $nonconformity = $this->nonconformities->publish($nonconformity);

        return response()->json(['data' => $this->mapDetail($nonconformity)]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/approve
     */
    public function approve(Nonconformity $nonconformity): JsonResponse
    {
        if (! $this->canApprove($nonconformity)) {
            abort(422, 'This non-conformity cannot be approved.');
        }

        $nonconformity = $this->nonconformities->approve($nonconformity);

        return response()->json(['data' => $this->mapDetail($nonconformity)]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/reopen
     */
    public function reopen(Nonconformity $nonconformity): JsonResponse
    {
        if (! $nonconformity->is_closed) {
            abort(422, 'This non-conformity is not closed.');
        }

        $nonconformity = $this->nonconformities->reopen($nonconformity);

        return response()->json(['data' => $this->mapDetail($nonconformity)]);
    }

    private function mapRow(Nonconformity $nc): array
    {
        return [
            'id' => $nc->id,
            'ncr_no' => $nc->ncr_no,
            'date_of_nc' => $nc->date_of_nc->format('Y-m-d'),
            'added_by' => $nc->added_by,
            'source_of_nc' => $this->sourceLabel($nc),
            'reported_by' => trim("{$nc->reported_by} - {$nc->reporter_name}", ' -'),
            'vessel_company' => $nc->vessel_company === 'VESSEL' ? ($nc->vessel?->display_name ?? '') : ($nc->company ?? ''),
            'description' => $nc->description,
            'is_published' => $this->publishedDisplay($nc),
            'is_approved' => $this->approvedDisplay($nc),
            'status' => $nc->is_closed ? 'CLOSED' : 'IN PROGRESS',
            'status_color' => $this->statusColor($nc),
            'can_edit' => $this->canEdit($nc),
            'can_publish' => $this->canPublish($nc),
            'can_approve' => $this->canApprove($nc),
            'can_reopen' => $nc->is_closed,
        ];
    }

    private function mapDetail(Nonconformity $nc): array
    {
        return [
            ...$this->mapRow($nc),
            'vessel_id' => $nc->vessel_id,
            'vessel_company_raw' => $nc->vessel_company,
            'company' => $nc->company,
            'department_name' => $nc->department_name,
            'reported_by_raw' => $nc->reported_by,
            'reporter_name' => $nc->reporter_name,
            'source_of_nc_raw' => $nc->source_of_nc,
            'source_of_nc_others' => $nc->source_of_nc_others,
            'source_of_nc_ref_no' => $nc->source_of_nc_ref_no,
            'manual_chapter_id' => $nc->manual_chapter_id,
            'manual_chapter_label' => $nc->manualChapter ? "({$nc->manualChapter->reference_no}) {$nc->manualChapter->chapter_name}" : null,
            'sms_details' => $nc->sms_details,
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
        ];
    }

    private function sourceLabel(Nonconformity $nc): string
    {
        return match ($nc->source_of_nc) {
            'OPERATIONAL' => 'NC - OPERATIONAL',
            'OTHERS' => "NC - OTHERS ({$nc->source_of_nc_others})",
            default => $nc->source_of_nc,
        };
    }

    /** null = "—" (not applicable to this record), otherwise a real published state. */
    private function publishedDisplay(Nonconformity $nc): ?bool
    {
        if ($nc->approved_elsewhere) {
            return null;
        }

        if ($nc->added_by === 'SHORE') {
            return $nc->vessel_id !== null ? $nc->is_published : null;
        }

        return true;
    }

    private function approvedDisplay(Nonconformity $nc): ?bool
    {
        if ($nc->approved_elsewhere || $nc->vessel_id === null || ! $nc->is_published) {
            return null;
        }

        return $nc->is_approved;
    }

    /**
     * Ported from loadData()'s status column formatter: closed rows for a
     * published, active, vessel-attributed record that isn't approved
     * yet still show as "needs attention" (yellow) rather than fully
     * done (green).
     */
    private function statusColor(Nonconformity $nc): string
    {
        if (! $nc->is_closed) {
            return 'yellow';
        }

        if ($nc->vessel_id !== null && $nc->is_published && ! $nc->is_approved) {
            return 'yellow';
        }

        return 'green';
    }

    private function canEdit(Nonconformity $nc): bool
    {
        return ! $nc->is_closed && ! $nc->is_inactive;
    }

    private function canPublish(Nonconformity $nc): bool
    {
        return ! $nc->approved_elsewhere && $nc->added_by === 'SHORE' && $nc->vessel_id !== null && ! $nc->is_inactive;
    }

    private function canApprove(Nonconformity $nc): bool
    {
        return ! $nc->approved_elsewhere && $nc->vessel_id !== null && $nc->is_published && ! $nc->is_inactive && ! $nc->is_approved;
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
