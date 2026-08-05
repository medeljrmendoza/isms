<?php

namespace App\Http\Controllers\Api\ExposureHours;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExposureHours\ExposureHoursRecordRequest;
use App\Models\ExposureHours\ExposureHoursRecord;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Exposure_hours.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility, and
 * summary_export_to_excel() (an .xlsx download of the summary report —
 * no Excel export exists anywhere else in this migration either). The
 * static Legends page (legends()) has no data at all in legacy — it's a
 * fixed FAT/PTD/PPD/LWC/RWC/MTC/LTI/TRC/LTIF/TRCF glossary, so it's
 * rendered client-side from a constant instead of a backend endpoint.
 */
class ExposureHoursController extends Controller
{
    public function __construct(private readonly ExposureHoursRepository $exposureHours) {}

    /**
     * GET /api/exposure-hours/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->exposureHours->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->exposureHours->vesselOptions(),
                // A new record's vessel_id is a local Vessel foreign key —
                // legacy-sourced vessel ids don't have a matching local
                // row, so creation is only offered when reading locally.
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/exposure-hours/summary?vessel_id=&date_from=&date_to=
     */
    public function summary(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->exposureHours->legacySummary(
                    $request->query('vessel_id'),
                    $request->query('date_from') ?: null,
                    $request->query('date_to') ?: null,
                    $request->user()?->legacy_user_id,
                ),
            ]);
        }

        return response()->json([
            'data' => $this->exposureHours->summary(
                $request->query('vessel_id'),
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
            ),
        ]);
    }

    /**
     * GET /api/exposure-hours-records?vessel_id=&date_from=&date_to=
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $vesselId = $request->query('vessel_id');

            if ($vesselId === null || $vesselId === '') {
                return response()->json(['data' => ['columns' => ExposureHoursRepository::recordColumns(), 'rows' => [], 'meta' => null]]);
            }

            $result = $this->exposureHours->legacyFullTable(
                $vesselId,
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
                TableQuery::fromRequest($request),
                $request->user()?->legacy_user_id,
            );

            return response()->json([
                'data' => [
                    'columns' => ExposureHoursRepository::recordColumns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $vesselId = (int) $request->query('vessel_id');

        if ($vesselId === 0) {
            return response()->json(['data' => ['columns' => ExposureHoursRepository::recordColumns(), 'rows' => [], 'meta' => null]]);
        }

        $paginator = $this->exposureHours->fullTable(
            $vesselId,
            $request->query('date_from') ?: null,
            $request->query('date_to') ?: null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => ExposureHoursRepository::recordColumns(),
                'rows' => collect($paginator->items())->map(fn (ExposureHoursRecord $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/exposure-hours-records/{exposureHoursRecord}
     */
    public function show(string $exposureHoursRecord): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->exposureHours->legacyDetail($exposureHoursRecord);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = ExposureHoursRecord::query()->with('vessel')->findOrFail((int) $exposureHoursRecord);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/exposure-hours-records
     */
    public function store(ExposureHoursRecordRequest $request): JsonResponse
    {
        $record = $this->exposureHours->create($request->validated());
        $record->load('vessel');

        return response()->json(['data' => $this->mapDetail($record)], 201);
    }

    /**
     * PUT /api/exposure-hours-records/{exposureHoursRecord}
     */
    public function update(ExposureHoursRecordRequest $request, ExposureHoursRecord $exposureHoursRecord): JsonResponse
    {
        $exposureHoursRecord = $this->exposureHours->update($exposureHoursRecord, $request->validated());
        $exposureHoursRecord->load('vessel');

        return response()->json(['data' => $this->mapDetail($exposureHoursRecord)]);
    }

    /**
     * DELETE /api/exposure-hours-records/{exposureHoursRecord}
     */
    public function destroy(ExposureHoursRecord $exposureHoursRecord): JsonResponse
    {
        $this->exposureHours->delete($exposureHoursRecord);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(ExposureHoursRecord $r): array
    {
        return [
            'id' => $r->id,
            'added_by' => $r->added_by,
            'date_from' => $r->date_from->format('Y-m-d'),
            'date_to' => $r->date_to->format('Y-m-d'),
            'no_of_crew' => $r->no_of_crew,
            'no_of_fat' => $r->no_of_fat,
            'no_of_ptd' => $r->no_of_ptd,
            'no_of_ppd' => $r->no_of_ppd,
            'no_of_lwc' => $r->no_of_lwc,
            'no_of_rwc' => $r->no_of_rwc,
            'no_of_mtc' => $r->no_of_mtc,
            'total_hours' => $r->total_hours,
            'vessel_remarks' => $r->vessel_remarks,
            'shore_remarks' => $r->shore_remarks,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    private function mapDetail(ExposureHoursRecord $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'vessel' => $r->vessel?->display_name ?? '',
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
