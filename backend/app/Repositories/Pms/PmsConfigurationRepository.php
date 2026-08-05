<?php

namespace App\Repositories\Pms;

use App\Models\Principal;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Pms_setup_configuration.php. Legacy's own
 * "add_item" is really an edit-in-place of an existing vessel's
 * configuration (SHORE/VESSEL) — there's no create or delete here.
 */
class PmsConfigurationRepository
{
    /** @return array<int, array{id:int,label:string}> */
    public function principalOptions(): array
    {
        return Principal::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Principal $p) => ['id' => $p->id, 'label' => $p->name])
            ->all();
    }

    public function table(int $principalId, TableQuery $query): LengthAwarePaginator
    {
        $builder = Vessel::query()->where('principal_id', $principalId);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('short_name', 'like', $term);
            });
        }

        $sortable = ['vessel' => 'name', 'short_name' => 'short_name', 'configuration' => 'configuration'];
        $sort = $sortable[$query->sort ?? 'vessel'] ?? 'name';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_item(). */
    public function updateConfiguration(Vessel $vessel, string $configuration): Vessel
    {
        $vessel->update(['configuration' => $configuration]);

        return $vessel;
    }
}
