<?php

namespace App\Repositories\Pms;

use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Pms_setup_configuration.php. Legacy's own
 * "add_item" is really an edit-in-place of an existing vessel's
 * configuration (SHORE/VESSEL) — there's no create or delete here.
 * legacyUpdateConfiguration() writes to the legacy connection — legacy
 * genuinely supports this action, so read-only-by-default doesn't apply.
 */
class PmsConfigurationRepository
{
    /** @return array<int, array{id:string,label:string}> */
    public function legacyPrincipalOptions(): array
    {
        return DB::connection('legacy')->table('tb_principal')
            ->where('status', 1)
            ->orderBy('principal_name')
            ->get()
            ->map(fn ($p) => ['id' => $p->principalID, 'label' => $p->principal_name])
            ->all();
    }

    /**
     * Ported from Pms_setup_configuration::loadData(), reading tb_vessel
     * directly from the legacy connection.
     *
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function legacyTable(string $principalId, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_vessel')
            ->where('principalID', $principalId)
            ->where('vessel_status', 'ACTIVE');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('vessel_name', 'like', $term)->orWhere('short_name', 'like', $term);
            });
        }

        $sortable = ['vessel' => 'vessel_name', 'short_name' => 'short_name', 'configuration' => 'configuration'];
        $sort = $sortable[$query->sort ?? 'vessel'] ?? 'vessel_name';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($v) => $this->mapLegacyRow($v))->all();

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

    /** Ported from add_item(), writing to the legacy connection. */
    public function legacyUpdateConfiguration(string $vesselId, string $configuration): array
    {
        DB::connection('legacy')->table('tb_vessel')
            ->where('vesID', $vesselId)
            ->update(['configuration' => $configuration]);

        $v = DB::connection('legacy')->table('tb_vessel')->where('vesID', $vesselId)->first();

        return $this->mapLegacyRow($v);
    }

    private function mapLegacyRow(object $v): array
    {
        return [
            'id' => $v->vesID,
            'vessel_name' => trim("{$v->vessel_prefix} {$v->vessel_name}"),
            'short_name' => $v->short_name,
            'configuration' => $v->configuration,
            'can_edit' => true,
        ];
    }
}
