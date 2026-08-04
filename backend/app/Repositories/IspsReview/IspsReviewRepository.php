<?php

namespace App\Repositories\IspsReview;

use App\Models\IspsReview\IspsReview;
use App\Models\IspsReview\IspsReviewPresent;
use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IspsReviewRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'review_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'review_quarter', 'label' => 'QTY', 'sortable' => true],
        ['key' => 'review_year', 'label' => 'YEAR', 'sortable' => true],
        ['key' => 'sms', 'label' => 'PROCEDURE', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Isps_review.php's
     * loadData(). Not ported: the tb_logs audit-trail writes on every
     * action and the S3-file-sync side effects, same as MasterReviewRepository.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'review_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'review_quarter', 'label' => 'QTR', 'sortable' => true],
        ['key' => 'review_year', 'label' => 'YR', 'sortable' => true],
        ['key' => 'sms', 'label' => 'PROCEDURE', 'sortable' => false],
        ['key' => 'review_recommendation', 'label' => 'RECOMMENDATION', 'sortable' => false],
        ['key' => 'has_vessel_remarks', 'label' => 'VESSEL REMARKS', 'sortable' => false],
        ['key' => 'has_shore_remarks', 'label' => 'SHORE REMARKS', 'sortable' => false],
        ['key' => 'shore_status', 'label' => 'STATUS', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /** Same shape/pattern as MasterReviewRepository — see its docblock. */
    public function pendingQuery(): Builder
    {
        return IspsReview::query()
            ->with(['vessel', 'manualDocument'])
            ->where('is_deleted', false)
            ->where('shore_status', '')
            ->where(function (Builder $query) {
                $query->where('added_by', 'SHORE')
                    ->orWhere(function (Builder $vessel) {
                        $vessel->where('added_by', 'VESSEL')->where('is_vessel_approved', true);
                    });
            });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('review_date', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhere('review_year', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'review_year';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Dashboard_isps_review.php's loadData() —
     * same shape as MasterReviewRepository::legacyTable(), see its
     * docblock.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_isps_review')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_isps_review.manDocID')
            ->where('tb_isps_review.is_deleted', '0')
            ->where('tb_isps_review.shore_status', '')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('tb_isps_review.added_by', 'SHORE')
                    ->orWhere(function ($vessel) use ($assignedVesselIds) {
                        $vessel->where('tb_isps_review.added_by', 'VESSEL')
                            ->where('tb_isps_review.is_vessel_approved', '1')
                            ->whereIn('tb_isps_review.vesID', $assignedVesselIds);
                    });
            })
            ->select([
                'tb_isps_review.reviewID',
                'tb_isps_review.vesID',
                'tb_isps_review.review_date',
                'tb_isps_review.added_by',
                'tb_isps_review.review_quarter',
                'tb_isps_review.review_year',
                'tb_isps_review.manual_section',
                'tb_manual_documents.reference_no',
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_isps_review.review_date', 'like', $term)
                    ->orWhere('tb_isps_review.added_by', 'like', $term)
                    ->orWhere('tb_isps_review.review_year', 'like', $term)
                    ->orWhere('tb_manual_documents.reference_no', 'like', $term);
            });
        }

        $sortMap = [
            'review_date' => 'tb_isps_review.review_date',
            'added_by' => 'tb_isps_review.added_by',
            'review_quarter' => 'tb_isps_review.review_quarter',
            'review_year' => 'tb_isps_review.review_year',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_isps_review.review_year';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->reviewID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'review_date' => $r->review_date,
            'added_by' => $r->added_by,
            'review_quarter' => $r->review_quarter,
            'review_year' => $r->review_year,
            'sms' => $r->reference_no !== null ? trim("{$r->reference_no} ({$r->manual_section})") : '',
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

    /**
     * Ported from Isps_review.php's view_record(), surfaced via the
     * dashboard's clickable review_date column. Read-only — see
     * SireReportRepository::detail()'s docblock for the convention.
     */
    public function detail(int $id): ?array
    {
        $r = IspsReview::query()->with(['vessel', 'manualChapter', 'manualDocument', 'present'])->find($id);

        if ($r === null) {
            return null;
        }

        $reference = $r->manualDocument?->reference_no ?? $r->manualChapter?->reference_no ?? '';

        return $this->toDetailArray([
            'vessel' => $r->vessel?->display_name ?? '',
            'review_date' => $r->review_date?->format('Y-m-d'),
            'added_by' => $r->added_by,
            'review_quarter' => (int) ltrim((string) $r->review_quarter, 'Q'),
            'review_year' => $r->review_year,
            'sms' => trim("{$reference} ({$r->manual_section})"),
            'review_recommendation' => $r->review_recommendation,
            'has_vessel_remarks' => filled($r->vessel_remarks),
            'has_shore_remarks' => filled($r->shore_remarks),
            'shore_status' => $r->shore_status,
            'manual_chapter_id' => $r->manual_chapter_id,
            'manual_document_id' => $r->manual_document_id,
            'manual_section' => $r->manual_section,
            'review_description' => $r->review_description,
            'shore_reviewed_by' => $r->shore_reviewed_by,
            'shore_remarks' => $r->shore_remarks,
            'vessel_reviewed_by' => $r->vessel_reviewed_by,
            'vessel_reviewed_position' => $r->vessel_reviewed_position,
            'vessel_remarks' => $r->vessel_remarks,
            'present' => $r->present->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position,
            ])->all(),
        ]);
    }

    /** Same as detail(), reading tb_isps_review directly from the legacy connection. */
    public function legacyDetail(string $reviewID): ?array
    {
        $r = DB::connection('legacy')->table('tb_isps_review')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_isps_review.manDocID')
            ->where('tb_isps_review.reviewID', $reviewID)
            ->select(['tb_isps_review.*', 'tb_manual_documents.reference_no'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $present = DB::connection('legacy')->table('tb_isps_review_present')
            ->where('reviewID', $reviewID)
            ->orderBy('arrangement')
            ->get();

        $shoreReviewedByName = null;
        if ($r->shore_reviewed_by !== null && $r->shore_reviewed_by !== '') {
            $person = DB::connection('legacy')->table('tb_address_book')->where('id', $r->shore_reviewed_by)->first();
            if ($person !== null) {
                $name = trim("{$person->firstname} {$person->lastname}");
                $shoreReviewedByName = $name !== '' ? trim("{$person->company} ({$name})") : $person->company;
            }
        }

        return $this->toDetailArray([
            'vessel' => $vessels[$r->vesID] ?? '',
            'review_date' => $zeroDateToNull($r->review_date),
            'added_by' => $r->added_by,
            'review_quarter' => (int) ltrim((string) $r->review_quarter, 'Q'),
            'review_year' => $r->review_year,
            'sms' => $r->reference_no !== null ? trim("{$r->reference_no} ({$r->manual_section})") : '',
            'review_recommendation' => $r->review_recommendation,
            'has_vessel_remarks' => filled($r->vessel_remarks),
            'has_shore_remarks' => filled($r->shore_remarks),
            'shore_status' => $r->shore_status,
            'manual_chapter_id' => null,
            'manual_document_id' => null,
            'manual_section' => $r->manual_section,
            'review_description' => $r->review_description,
            'shore_reviewed_by' => $shoreReviewedByName,
            'shore_remarks' => $r->shore_remarks,
            'vessel_reviewed_by' => $r->vessel_reviewed_by,
            'vessel_reviewed_position' => $r->vessel_reviewed_position,
            'vessel_remarks' => $r->vessel_remarks,
            'present' => $present->map(fn ($p) => [
                'id' => $p->reviewPresentID,
                'name' => $p->review_name,
                'position' => $p->review_position,
            ])->all(),
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => 0,
            'vessel' => $r['vessel'],
            'review_date' => $r['review_date'],
            'added_by' => $r['added_by'],
            'review_quarter' => $r['review_quarter'],
            'review_year' => $r['review_year'],
            'sms' => $r['sms'],
            'review_recommendation' => $r['review_recommendation'],
            'has_vessel_remarks' => $r['has_vessel_remarks'],
            'has_shore_remarks' => $r['has_shore_remarks'],
            'shore_status' => $r['shore_status'],
            'can_edit' => false,
            'can_approve' => false,
            'can_recommend_approval' => false,
            'can_disapprove' => false,
            'can_disregard' => false,
            'can_delete' => false,
            'can_reopen' => false,
            'manual_chapter_id' => $r['manual_chapter_id'],
            'manual_document_id' => $r['manual_document_id'],
            'manual_section' => $r['manual_section'],
            'review_description' => $r['review_description'],
            'shore_reviewed_by' => $r['shore_reviewed_by'],
            'shore_remarks' => $r['shore_remarks'],
            'vessel_reviewed_by' => $r['vessel_reviewed_by'],
            'vessel_reviewed_position' => $r['vessel_reviewed_position'],
            'vessel_remarks' => $r['vessel_remarks'],
            'present' => $r['present'],
        ];
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function chapterOptions(): array
    {
        return ManualChapter::query()->orderBy('reference_no')->get()
            ->map(fn (ManualChapter $c) => ['id' => $c->id, 'label' => "({$c->reference_no}) {$c->chapter_name}"])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function documentOptionsForChapter(int $chapterId): array
    {
        return ManualDocument::query()->where('manual_chapter_id', $chapterId)->orderBy('reference_no')->get()
            ->map(fn (ManualDocument $d) => ['id' => $d->id, 'label' => "({$d->reference_no}) {$d->manual_name}"])
            ->all();
    }

    /**
     * Ported from loadData(). Same SHORE-auto-include / VESSEL-needs-
     * approval visibility gate as pendingQuery(), but without the
     * shore_status='' restriction — this list shows every status,
     * filterable via $recordStatus.
     */
    public function fullTable(
        ?int $vesselId,
        ?int $startQuarter,
        ?int $startYear,
        ?int $endQuarter,
        ?int $endYear,
        ?string $recordStatus,
        ?int $chapterId,
        TableQuery $query,
    ): LengthAwarePaginator {
        $builder = IspsReview::query()
            ->with(['vessel', 'manualChapter', 'manualDocument.manualChapter'])
            ->where('is_deleted', false)
            ->where(function (Builder $q) {
                $q->where('added_by', 'SHORE')
                    ->orWhere(function (Builder $v) {
                        $v->where('added_by', 'VESSEL')->where('is_vessel_approved', true);
                    });
            });

        if ($vesselId !== null) {
            $builder->where('vessel_id', $vesselId);
        }

        // review_quarter is stored as "Q1".."Q4" (see create()/update()).
        if ($startQuarter !== null && $startYear !== null && $endQuarter !== null && $endYear !== null) {
            $quarterExpr = 'CAST(SUBSTR(review_quarter, 2) AS INTEGER)';

            $builder->where(function (Builder $q) use ($quarterExpr, $startQuarter, $startYear, $endQuarter, $endYear) {
                $q->where(function (Builder $q2) use ($quarterExpr, $startQuarter, $startYear, $endQuarter, $endYear) {
                    $q2->where('review_year', $startYear)
                        ->where('review_year', $endYear)
                        ->whereRaw("{$quarterExpr} >= ?", [$startQuarter])
                        ->whereRaw("{$quarterExpr} <= ?", [$endQuarter]);
                })->orWhere(function (Builder $q2) use ($quarterExpr, $startQuarter, $startYear, $endYear) {
                    $q2->where('review_year', $startYear)
                        ->where('review_year', '!=', $endYear)
                        ->whereRaw("{$quarterExpr} >= ?", [$startQuarter]);
                })->orWhere(function (Builder $q2) use ($startYear, $endYear) {
                    $q2->where('review_year', '>', $startYear)->where('review_year', '<', $endYear);
                })->orWhere(function (Builder $q2) use ($quarterExpr, $startYear, $endYear, $endQuarter) {
                    $q2->where('review_year', $endYear)
                        ->where('review_year', '!=', $startYear)
                        ->whereRaw("{$quarterExpr} <= ?", [$endQuarter]);
                });
            });
        }

        if ($recordStatus !== null) {
            $builder->where('shore_status', $recordStatus);
        }

        if ($chapterId !== null) {
            $builder->where(function (Builder $q) use ($chapterId) {
                $q->where('manual_chapter_id', $chapterId)
                    ->orWhereHas('manualDocument', fn (Builder $d) => $d->where('manual_chapter_id', $chapterId));
            });
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('review_date', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhere('review_year', 'like', $term)
                    ->orWhere('shore_status', 'like', $term)
                    ->orWhere('review_recommendation', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('manualChapter', fn (Builder $c) => $c->where('reference_no', 'like', $term))
                    ->orWhereHas('manualDocument', fn (Builder $d) => $d->where('reference_no', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'review_date';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_record()'s insert branch: new records are always
     * SHORE-added with no vessel — legacy's own Add form hides the
     * Vessel field entirely for SHORE records (there's no VESSEL-origin
     * path reachable from this admin, same deferral as every other
     * module).
     */
    public function create(array $data, array $present): IspsReview
    {
        $review = IspsReview::create([
            ...$data,
            'review_quarter' => "Q{$data['review_quarter']}",
            'added_by' => 'SHORE',
            'vessel_id' => null,
            'vessel_reviewed_by' => null,
            'vessel_reviewed_position' => null,
            'vessel_remarks' => null,
            'is_vessel_approved' => false,
            'shore_status' => '',
            'is_deleted' => false,
        ]);

        $this->syncPresent($review, $present);

        return $review;
    }

    /**
     * Ported from add_record()'s edit branch. added_by and vesID are
     * frozen at creation time.
     */
    public function update(IspsReview $review, array $data, array $present): IspsReview
    {
        unset($data['vessel_id']);

        $review->update([
            ...$data,
            'review_quarter' => "Q{$data['review_quarter']}",
        ]);

        $this->syncPresent($review, $present);

        return $review;
    }

    /** Ported from approve_isps_review(). */
    public function approve(IspsReview $review): IspsReview
    {
        $review->update(['shore_status' => 'APPROVED']);

        return $review;
    }

    /** Ported from disapprove_isps_review(). */
    public function disapprove(IspsReview $review): IspsReview
    {
        $review->update(['shore_status' => 'DISAPPROVED']);

        return $review;
    }

    /** Ported from disregard_isps_review(). */
    public function disregard(IspsReview $review): IspsReview
    {
        $review->update(['shore_status' => 'DISREGARD']);

        return $review;
    }

    /** Ported from recommend_approval_isps_review(). */
    public function recommendApproval(IspsReview $review): IspsReview
    {
        $review->update(['shore_status' => 'RECOMMEND APPROVAL']);

        return $review;
    }

    /** Ported from reopen_isps_review(): clears shore_status back to pending. */
    public function reopen(IspsReview $review): IspsReview
    {
        $review->update(['shore_status' => '']);

        return $review;
    }

    /** Ported from delete_isps_review(): soft delete. */
    public function delete(IspsReview $review): void
    {
        $review->update(['is_deleted' => true, 'shore_status' => 'DELETED']);
    }

    /** @param array<int, array{name:string, position?:string|null}> $rows */
    private function syncPresent(IspsReview $review, array $rows): void
    {
        $review->present()->delete();

        foreach (array_values($rows) as $index => $row) {
            IspsReviewPresent::create([
                'isps_review_id' => $review->id,
                'arrangement' => $index + 1,
                'name' => $row['name'],
                'position' => $row['position'] ?? null,
            ]);
        }
    }
}
