<?php

namespace App\Http\Controllers\Api\VesselDocumentation;

use App\Http\Controllers\Controller;
use App\Http\Requests\VesselDocumentation\VesselDocumentRecordRequest;
use App\Models\VesselDocumentation\VesselDocumentRecord;
use App\Repositories\VesselDocumentation\VesselDocumentationRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Vessel_documentation.php. Not ported: file
 * attachment upload/S3 storage, the per-update history archive, the
 * "printer-friendly" grouped print view, export_vessel_docs() (zips
 * attachments — no attachments exist here), the tb_logs audit trail,
 * and user_level-gated (MEMBER) visibility — see
 * VesselDocumentationRepository's docblocks for the reasoning behind
 * each.
 */
class VesselDocumentationController extends Controller
{
    public function __construct(private readonly VesselDocumentationRepository $vesselDocumentation) {}

    /**
     * GET /api/vessel-documentation/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->vesselDocumentation->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->vesselDocumentation->vesselOptions(),
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/vessel-documentation/type-options?vessel_id=
     */
    public function typeOptions(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->vesselDocumentation->legacyDocumentTypeOptionsForVessel((string) $request->query('vessel_id')),
            ]);
        }

        return response()->json([
            'data' => $this->vesselDocumentation->documentTypeOptionsForVessel((int) $request->query('vessel_id')),
        ]);
    }

    /**
     * GET /api/vessel-documentation/document-options?vessel_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->vesselDocumentation->legacyDocumentOptionsForVessel((string) $request->query('vessel_id')),
            ]);
        }

        return response()->json([
            'data' => $this->vesselDocumentation->catalogOptionsForVessel((int) $request->query('vessel_id')),
        ]);
    }

    /**
     * GET /api/vessel-documentation?vessel_id=&type_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $typeId = $request->query('type_id');
        $typeId = $typeId !== null && $typeId !== '' ? $typeId : null;

        if (LegacyDb::isConfigured()) {
            $result = $this->vesselDocumentation->legacyFullTable(
                (string) $request->query('vessel_id'),
                $typeId !== null ? (string) $typeId : null,
                TableQuery::fromRequest($request),
            );

            return response()->json([
                'data' => [
                    'columns' => VesselDocumentationRepository::moduleColumns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $paginator = $this->vesselDocumentation->fullTable(
            (int) $request->query('vessel_id'),
            $typeId !== null ? (int) $typeId : null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => VesselDocumentationRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (VesselDocumentRecord $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/vessel-documentation/{vesselDocumentRecord}
     */
    public function show(string $vesselDocumentRecord): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->vesselDocumentation->legacyDetail($vesselDocumentRecord);

            return response()->json(['data' => $detail]);
        }

        $record = VesselDocumentRecord::query()->with('vesselDocument.vesselDocumentType')->findOrFail((int) $vesselDocumentRecord);

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * POST /api/vessel-documentation
     */
    public function store(VesselDocumentRecordRequest $request): JsonResponse
    {
        $record = $this->vesselDocumentation->create($request->validated());
        $record->load('vesselDocument.vesselDocumentType');

        return response()->json(['data' => $this->mapDetail($record)], 201);
    }

    /**
     * PUT /api/vessel-documentation/{vesselDocumentRecord}
     */
    public function update(VesselDocumentRecordRequest $request, VesselDocumentRecord $vesselDocumentRecord): JsonResponse
    {
        $record = $this->vesselDocumentation->update($vesselDocumentRecord, $request->validated());
        $record->load('vesselDocument.vesselDocumentType');

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * POST /api/vessel-documentation/{vesselDocumentRecord}/toggle-status
     */
    public function toggleStatus(VesselDocumentRecord $vesselDocumentRecord): JsonResponse
    {
        $record = $this->vesselDocumentation->toggleStatus($vesselDocumentRecord);
        $record->load('vesselDocument.vesselDocumentType');

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * DELETE /api/vessel-documentation/{vesselDocumentRecord}
     */
    public function destroy(VesselDocumentRecord $vesselDocumentRecord): JsonResponse
    {
        $this->vesselDocumentation->delete($vesselDocumentRecord);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(VesselDocumentRecord $r): array
    {
        return [
            'id' => $r->id,
            'document_type' => $r->vesselDocument?->vesselDocumentType?->name ?? '',
            'document' => $r->vesselDocument?->name ?? '',
            'doc_number' => $r->doc_number,
            'issuing_body' => $r->issuing_body,
            'date_issued' => $r->date_issued?->format('Y-m-d'),
            'date_expired' => $r->date_expired?->format('Y-m-d'),
            'is_printer_friendly' => $r->is_printer_friendly,
            'warning_status' => $r->warning_status ?? 0,
            'is_active' => $r->is_active,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    private function mapDetail(VesselDocumentRecord $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'vessel_document_id' => $r->vessel_document_id,
            'date_range_from' => $r->date_range_from?->format('Y-m-d'),
            'date_range_to' => $r->date_range_to?->format('Y-m-d'),
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
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
