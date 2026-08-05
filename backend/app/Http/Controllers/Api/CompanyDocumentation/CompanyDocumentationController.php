<?php

namespace App\Http\Controllers\Api\CompanyDocumentation;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyDocumentation\CompanyDocumentationRecordRequest;
use App\Models\CompanyDocumentation\CompanyDocumentationRecord;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Company_documentation.php. Not ported: file
 * attachment upload/S3 storage, the per-update history archive, the
 * "printer-friendly" grouped print view, and the tb_logs audit trail —
 * see CompanyDocumentationRepository's docblocks. Unlike Vessel
 * Documentation, this module is company-wide, not per-vessel — there's
 * no vessel gate anywhere here.
 */
class CompanyDocumentationController extends Controller
{
    public function __construct(private readonly CompanyDocumentationRepository $companyDocumentation) {}

    /**
     * GET /api/company-documentation/type-options
     */
    public function typeOptions(): JsonResponse
    {
        return response()->json([
            'data' => [
                'types' => LegacyDb::isConfigured() ? $this->companyDocumentation->legacyTypeOptions() : $this->companyDocumentation->typeOptions(),
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/company-documentation/document-options
     */
    public function documentOptions(): JsonResponse
    {
        return response()->json([
            'data' => LegacyDb::isConfigured() ? $this->companyDocumentation->legacyDocumentOptions() : $this->companyDocumentation->catalogOptions(),
        ]);
    }

    /**
     * GET /api/company-documentation?type_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $typeId = $request->query('type_id');
        $typeId = $typeId !== null && $typeId !== '' ? $typeId : null;

        if (LegacyDb::isConfigured()) {
            $result = $this->companyDocumentation->legacyFullTable($typeId !== null ? (string) $typeId : null, TableQuery::fromRequest($request));

            return response()->json([
                'data' => [
                    'columns' => CompanyDocumentationRepository::moduleColumns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $paginator = $this->companyDocumentation->fullTable(
            $typeId !== null ? (int) $typeId : null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => CompanyDocumentationRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (CompanyDocumentationRecord $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/company-documentation/{companyDocumentationRecord}
     */
    public function show(string $companyDocumentationRecord): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->companyDocumentation->legacyDetail($companyDocumentationRecord)]);
        }

        $record = CompanyDocumentationRecord::query()->with('companyDocument.companyDocumentType')->findOrFail((int) $companyDocumentationRecord);

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * POST /api/company-documentation
     */
    public function store(CompanyDocumentationRecordRequest $request): JsonResponse
    {
        $record = $this->companyDocumentation->create($request->validated());
        $record->load('companyDocument.companyDocumentType');

        return response()->json(['data' => $this->mapDetail($record)], 201);
    }

    /**
     * PUT /api/company-documentation/{companyDocumentationRecord}
     */
    public function update(CompanyDocumentationRecordRequest $request, CompanyDocumentationRecord $companyDocumentationRecord): JsonResponse
    {
        $record = $this->companyDocumentation->update($companyDocumentationRecord, $request->validated());
        $record->load('companyDocument.companyDocumentType');

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * POST /api/company-documentation/{companyDocumentationRecord}/toggle-status
     */
    public function toggleStatus(CompanyDocumentationRecord $companyDocumentationRecord): JsonResponse
    {
        $record = $this->companyDocumentation->toggleStatus($companyDocumentationRecord);
        $record->load('companyDocument.companyDocumentType');

        return response()->json(['data' => $this->mapDetail($record)]);
    }

    /**
     * DELETE /api/company-documentation/{companyDocumentationRecord}
     */
    public function destroy(CompanyDocumentationRecord $companyDocumentationRecord): JsonResponse
    {
        $this->companyDocumentation->delete($companyDocumentationRecord);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(CompanyDocumentationRecord $r): array
    {
        return [
            'id' => $r->id,
            'document_type' => $r->companyDocument?->companyDocumentType?->name ?? '',
            'document' => $r->companyDocument?->name ?? '',
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

    private function mapDetail(CompanyDocumentationRecord $r): array
    {
        return [
            ...$this->mapRow($r),
            'company_document_id' => $r->company_document_id,
            'date_range_from' => $r->date_range_from?->format('Y-m-d'),
            'date_range_to' => $r->date_range_to?->format('Y-m-d'),
            'remarks' => $r->remarks,
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
