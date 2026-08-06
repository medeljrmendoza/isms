<?php

namespace App\Repositories\VesselExports;

use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard.php's loadImportData(). Only the
 * export-file log is ported (see dashboard_export_import_dashlet.php) —
 * the Export/Import buttons themselves depend on a vessel-side sync
 * application (zip files under includes/sync/, S3 backups, SQL-diff
 * encoding/decoding) that has no counterpart anywhere in this
 * migration, so they're rendered disabled rather than faked.
 */
class VesselExportRepository
{
    private const COLUMNS = [
        ['key' => 'vessel_file', 'label' => 'FILE', 'sortable' => true],
        ['key' => 'date_of_export', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'status', 'label' => 'STATUS', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** Ported from loadImportData(): vessel_file is truncated to its first 19 characters (the vessel code + export timestamp) before display. */
    public function legacyTable(TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_vessel_export')
            ->where('flag', '0')
            ->where('vesexID', 'not like', '%sample%');

        if ($query->search !== null) {
            $builder->where('vessel_file', 'like', "%{$query->search}%");
        }

        $columnMap = ['vessel_file' => 'vessel_file', 'date_of_export' => 'dateOf_export'];
        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'vessel_file';
        $sort = $columnMap[$sort] ?? 'vessel_file';

        $paginator = $builder->orderBy($sort, $query->direction)
            ->select(['vessel_file', 'dateOf_export', 'flag'])
            ->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($row) => [
            'vessel_file' => substr($row->vessel_file, 0, 19),
            'date_of_export' => $row->dateOf_export,
            'status' => ((int) $row->flag) === 1 ? 'Synced' : 'Pending',
        ])->all();

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
}
