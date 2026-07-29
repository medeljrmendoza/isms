<?php

namespace App\Repositories\RiskAssessment;

use App\Models\RiskAssessment\RiskAssessmentHazardShore;
use App\Models\RiskAssessment\RiskAssessmentPersonShore;
use App\Models\RiskAssessment\RiskAssessmentShore;
use App\Models\RiskAssessment\RiskCategoryShore;
use App\Models\RiskAssessment\RiskOperationShore;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Risk_assessment_shore.php. Fully independent
 * of RiskAssessmentRepository (the Vessel module) — legacy keeps these
 * as two genuinely separate tables, not a shared one.
 */
class RiskAssessmentShoreRepository
{
    private const COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'report_type', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'port', 'label' => 'PORT/LOCATION', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function categoryOptions(): array
    {
        return RiskCategoryShore::query()->orderBy('name')->get()
            ->map(fn (RiskCategoryShore $c) => ['id' => $c->id, 'label' => $c->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function operationOptions(): array
    {
        return RiskOperationShore::query()->orderBy('name')->get()
            ->map(fn (RiskOperationShore $o) => ['id' => $o->id, 'label' => $o->name])
            ->all();
    }

    public function years(): array
    {
        return RiskAssessmentShore::query()
            ->selectRaw("DISTINCT strftime('%Y', risk_date) as year")
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Ported from loadData(): SHORE-type rows are always visible; a
     * VESSEL-type row only shows when it belongs to the selected
     * vessel. With no vessel filter ("ALL"), everything is visible —
     * unlike the Vessel module's list, this one has no required filter.
     */
    public function table(TableQuery $query, ?int $vesselId, ?int $year): LengthAwarePaginator
    {
        $builder = RiskAssessmentShore::query()->with(['vessel', 'riskCategoryShore', 'riskOperationShore'])
            ->withCount('hazards');

        if ($vesselId !== null) {
            $builder->where(function (Builder $q) use ($vesselId) {
                $q->where('report_type', 'SHORE')
                    ->orWhere(function (Builder $vq) use ($vesselId) {
                        $vq->where('report_type', 'VESSEL')->where('vessel_id', $vesselId);
                    });
            });
        }

        if ($year !== null) {
            $builder->whereRaw("strftime('%Y', risk_date) = ?", [(string) $year]);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('report_no', 'like', $term)
                    ->orWhere('report_type', 'like', $term)
                    ->orWhere('port', 'like', $term)
                    ->orWhere('other_category_name', 'like', $term)
                    ->orWhere('other_operation_name', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('riskCategoryShore', fn (Builder $c) => $c->where('name', 'like', $term))
                    ->orWhereHas('riskOperationShore', fn (Builder $o) => $o->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'risk_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_report()'s insert branch. report_type/vessel_id/
     * category/operation are only ever set here — update() freezes them.
     */
    public function create(array $data, array $hazards, array $people): RiskAssessmentShore
    {
        $report = RiskAssessmentShore::create($data);

        $this->syncHazards($report, $hazards);
        $this->syncPeople($report, $people);

        return $report;
    }

    /**
     * Ported from add_report()'s edit branch: report_type, vessel_id,
     * and the category/operation FK-or-OTHER choice are frozen at
     * creation — legacy always re-reads them from the existing row
     * rather than from the edit payload. The free-text OTHER labels
     * stay editable since legacy re-reads those from POST regardless.
     */
    public function update(RiskAssessmentShore $report, array $data, array $hazards, array $people): RiskAssessmentShore
    {
        unset($data['report_type'], $data['vessel_id'], $data['risk_category_shore_id'], $data['risk_operation_shore_id']);

        if ($report->risk_category_shore_id !== null) {
            unset($data['other_category_name']);
        }

        if ($report->risk_operation_shore_id !== null) {
            unset($data['other_operation_name']);
        }

        $report->update($data);

        $this->syncHazards($report, $hazards);
        $this->syncPeople($report, $people);

        return $report;
    }

    /** Ported from reopen_report(): clears date_closed, no other side effects. */
    public function reopen(RiskAssessmentShore $report): RiskAssessmentShore
    {
        $report->update(['date_closed' => null]);

        return $report;
    }

    /** Ported from delete_report(): a real delete, not a soft one. Hazards/people cascade via FK. */
    public function delete(RiskAssessmentShore $report): void
    {
        $report->delete();
    }

    /** @param array<int, array{unwanted_consequences: ?string, underlying_causes: ?string, severity: ?int, likelihood: ?int, risk: ?string, existing_control: ?string, additional_control: ?string, re_severity: ?int, re_likelihood: ?int, re_risk: ?string}> $rows */
    private function syncHazards(RiskAssessmentShore $report, array $rows): void
    {
        $report->hazards()->delete();

        foreach (array_values($rows) as $index => $row) {
            RiskAssessmentHazardShore::create([
                'risk_assessment_shore_id' => $report->id,
                'arrangement' => $index + 1,
                'unwanted_consequences' => $row['unwanted_consequences'] ?? null,
                'underlying_causes' => $row['underlying_causes'] ?? null,
                'severity' => $row['severity'] ?? null,
                'likelihood' => $row['likelihood'] ?? null,
                'risk' => $row['risk'] ?? null,
                'existing_control' => $row['existing_control'] ?? null,
                'additional_control' => $row['additional_control'] ?? null,
                're_severity' => $row['re_severity'] ?? null,
                're_likelihood' => $row['re_likelihood'] ?? null,
                're_risk' => $row['re_risk'] ?? null,
            ]);
        }
    }

    /** @param array<int, array{person_details: string}> $rows */
    private function syncPeople(RiskAssessmentShore $report, array $rows): void
    {
        $report->people()->delete();

        foreach (array_values($rows) as $index => $row) {
            RiskAssessmentPersonShore::create([
                'risk_assessment_shore_id' => $report->id,
                'arrangement' => $index + 1,
                'person_details' => $row['person_details'],
            ]);
        }
    }
}
