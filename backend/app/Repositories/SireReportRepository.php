<?php

namespace App\Repositories;

use App\Models\SireReport;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SireReportRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'pending', 'label' => 'PENDING', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_sire.php's loadData() WHERE
     * clause — with a real reduction in what "pending" means here, not
     * just a missing display column: legacy shows a report if it's
     * published-and-unapproved OR has pending observations. Observations
     * doesn't exist as a module yet (same deferral as the audit-style
     * dashlets), so only the first half applies. A SIRE report with
     * open observations but that's already approved won't appear here
     * yet — unlike the audit dashlets, this isn't a minor gap, it's half
     * of this filter's real meaning.
     */
    public function pendingQuery(): Builder
    {
        return SireReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->where('is_published', true)
            ->where('is_approved', false);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('dateof_inspection', 'like', $term)
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
