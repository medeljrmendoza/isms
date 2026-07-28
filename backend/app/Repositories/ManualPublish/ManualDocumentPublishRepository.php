<?php

namespace App\Repositories\ManualPublish;

use App\Models\ManualPublish\ManualDocument;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ManualDocumentPublishRepository
{
    private const COLUMNS = [
        ['key' => 'chapter', 'label' => 'CHAPTER', 'sortable' => false],
        ['key' => 'manual', 'label' => 'MANUAL', 'sortable' => false],
        ['key' => 'date_of_revision', 'label' => 'DATE', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_sms_publish_manual.php's
     * loadSMSPublishData(). Legacy's attachment-view link (S3) and
     * Publish action button are dropped — this dashlet is read-only,
     * consistent with every other migrated dashlet.
     */
    public function pendingQuery(): Builder
    {
        return ManualDocument::query()
            ->with('manualChapter')
            ->where('is_published', false);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('manual_name', 'like', $term)
                    ->orWhere('reference_no', 'like', $term)
                    ->orWhere('date_of_revision', 'like', $term)
                    ->orWhereHas('manualChapter', fn (Builder $c) => $c->where('chapter_name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_revision';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
