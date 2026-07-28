<?php

namespace App\Repositories\RiskAssessment;

use App\Models\Vessel;

use App\Models\RiskAssessment\RiskAssessment;
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

    public static function columns(): array
    {
        return self::COLUMNS;
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
}
