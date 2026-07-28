<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Normalizes the page/search/sort query params every dashlet table
 * endpoint accepts. `sort` is validated by each repository against its
 * own whitelist of sortable columns, not trusted directly as a column
 * name.
 */
final class TableQuery
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $search,
        public readonly ?string $sort,
        public readonly string $direction,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $search = trim((string) $request->query('search', ''));
        $sort = trim((string) $request->query('sort', ''));

        return new self(
            page: max(1, (int) $request->query('page', 1)),
            perPage: min(100, max(1, (int) $request->query('per_page', 10))),
            search: $search === '' ? null : $search,
            sort: $sort === '' ? null : $sort,
            direction: strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        );
    }
}
