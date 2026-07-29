<?php

namespace App\Repositories\RiskAssessment;

use App\Models\RiskAssessment\RiskAssessment;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RiskAssessmentRepository
{
    /**
     * "vessel", "category" and "task" (operation) aren't real sortable
     * columns — vessel is relation-derived, and category/task each
     * resolve to either a lookup name or a free-text fallback.
     */
    private const COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    /** The full-module list's column set — see Controllers/Risk_assessment_vessel.php's loadData(). */
    private const FULL_COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'port', 'label' => 'PORT', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function fullColumns(): array
    {
        return self::FULL_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_risk_assessment.php's
     * loadRiskAssessmentData() WHERE clause: reports still awaiting an
     * approval they require (shore and/or marine), independent of any
     * non-conformity/observation count — this module has neither.
     * Vessel scoping deferred as elsewhere.
     */
    public function pendingQuery(): Builder
    {
        return RiskAssessment::query()
            ->with(['vessel', 'riskCategory', 'riskOperation'])
            ->where(function (Builder $query) {
                $query->where(function (Builder $shore) {
                    $shore->where('approval_from_shore', true)->where('shore_is_approved', false);
                })->orWhere(function (Builder $marine) {
                    $marine->where('approval_from_marine', true)->where('marine_is_approved', false);
                });
            });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('report_no', 'like', $term)
                    ->orWhere('risk_date', 'like', $term)
                    ->orWhere('other_category_name', 'like', $term)
                    ->orWhere('other_operation_name', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('riskCategory', fn (Builder $c) => $c->where('name', 'like', $term))
                    ->orWhereHas('riskOperation', fn (Builder $o) => $o->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'risk_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Ported from get_riskassessment_report_year(): distinct years with
     * at least one report, newest first.
     */
    public function years(): array
    {
        return RiskAssessment::query()
            ->selectRaw("DISTINCT strftime('%Y', risk_date) as year")
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Ported from loadData(): the full-module report list requires both
     * a vessel and a year — legacy returns an empty result set
     * (WHERE 1 = 0) until both filters are applied, no default
     * "show everything" list.
     */
    public function fullTable(?int $vesselId, ?int $year, TableQuery $query): LengthAwarePaginator
    {
        if ($vesselId === null || $year === null) {
            return new LengthAwarePaginator([], 0, $query->perPage, $query->page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $builder = RiskAssessment::query()
            ->with(['vessel', 'riskCategory', 'riskOperation'])
            ->withCount('hazards')
            ->where('vessel_id', $vesselId)
            ->whereRaw("strftime('%Y', risk_date) = ?", [(string) $year]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('report_no', 'like', $term)
                    ->orWhere('port', 'like', $term)
                    ->orWhere('other_category_name', 'like', $term)
                    ->orWhere('other_operation_name', 'like', $term)
                    ->orWhereHas('riskCategory', fn (Builder $c) => $c->where('name', 'like', $term))
                    ->orWhereHas('riskOperation', fn (Builder $o) => $o->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::FULL_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'risk_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_report()'s shore-approval branch. Unlike
     * add_report(), only the fields this track actually owns are
     * touched — nothing else on the report changes.
     */
    public function approveShore(RiskAssessment $report, bool $approved, ?string $dateApproved, ?string $remarks): RiskAssessment
    {
        $report->update([
            'shore_is_approved' => $approved,
            'date_approved' => $dateApproved,
            'shore_remarks' => $remarks,
        ]);

        return $report;
    }

    /** Ported from add_report()'s marine-approval branch. */
    public function approveMarine(RiskAssessment $report, bool $approved, ?string $dateApproved, ?string $remarks): RiskAssessment
    {
        $report->update([
            'marine_is_approved' => $approved,
            'marine_date_approved' => $dateApproved,
            'marine_remarks' => $remarks,
        ]);

        return $report;
    }
}
