<?php

namespace App\Repositories\IspsReview;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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
     * Ported from Isps_review.php's loadData(): the module's own full
     * list, reading tb_isps_review directly from the legacy connection.
     * Same shape as MasterReviewRepository::legacyFullTable() — see its
     * docblock.
     */
    public function legacyFullTable(
        ?string $vesselId,
        ?int $startQuarter,
        ?int $startYear,
        ?int $endQuarter,
        ?int $endYear,
        ?string $recordStatus,
        ?string $chapterId,
        TableQuery $query,
        ?string $legacyUserId,
    ): array {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_isps_review')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_isps_review.manDocID')
            ->leftJoin('tb_manual_chapter as chapter_from_doc', 'chapter_from_doc.chapterID', '=', 'tb_manual_documents.chapterID')
            ->leftJoin('tb_manual_chapter as chapter_direct', 'chapter_direct.chapterID', '=', 'tb_isps_review.chapterID')
            ->where('tb_isps_review.is_deleted', '0')
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
                'tb_isps_review.manDocID',
                'tb_isps_review.manual_section',
                'tb_isps_review.review_recommendation',
                'tb_isps_review.vessel_remarks',
                'tb_isps_review.shore_remarks',
                'tb_isps_review.shore_status',
                'tb_manual_documents.reference_no',
                'chapter_from_doc.reference_no as chapter_ref_from_doc',
                'chapter_direct.reference_no as chapter_ref_direct',
            ]);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_isps_review.vesID', $vesselId);
        }

        if ($startQuarter !== null && $startYear !== null && $endQuarter !== null && $endYear !== null) {
            $builder->where(function ($q) use ($startQuarter, $startYear, $endQuarter, $endYear) {
                $q->where(function ($q2) use ($startQuarter, $startYear, $endQuarter, $endYear) {
                    $q2->where('tb_isps_review.review_year', $startYear)
                        ->where('tb_isps_review.review_year', $endYear)
                        ->where('tb_isps_review.review_quarter', '>=', "Q{$startQuarter}")
                        ->where('tb_isps_review.review_quarter', '<=', "Q{$endQuarter}");
                })->orWhere(function ($q2) use ($startQuarter, $startYear, $endYear) {
                    $q2->where('tb_isps_review.review_year', $startYear)
                        ->where('tb_isps_review.review_year', '!=', $endYear)
                        ->where('tb_isps_review.review_quarter', '>=', "Q{$startQuarter}");
                })->orWhere(function ($q2) use ($startYear, $endYear) {
                    $q2->where('tb_isps_review.review_year', '>', $startYear)->where('tb_isps_review.review_year', '<', $endYear);
                })->orWhere(function ($q2) use ($endQuarter, $startYear, $endYear) {
                    $q2->where('tb_isps_review.review_year', $endYear)
                        ->where('tb_isps_review.review_year', '!=', $startYear)
                        ->where('tb_isps_review.review_quarter', '<=', "Q{$endQuarter}");
                });
            });
        }

        if ($recordStatus !== null && $recordStatus !== '' && $recordStatus !== 'ALL') {
            $builder->where('tb_isps_review.shore_status', $recordStatus);
        }

        if ($chapterId !== null && $chapterId !== '' && $chapterId !== 'ALL') {
            $builder->where(function ($q) use ($chapterId) {
                $q->where(function ($q2) use ($chapterId) {
                    $q2->where('tb_isps_review.manDocID', '!=', '')->where('tb_manual_documents.chapterID', $chapterId);
                })->orWhere(function ($q2) use ($chapterId) {
                    $q2->where('tb_isps_review.manDocID', '')->where('tb_isps_review.chapterID', $chapterId);
                });
            });
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_isps_review.review_date', 'like', $term)
                    ->orWhere('tb_isps_review.added_by', 'like', $term)
                    ->orWhere('tb_isps_review.review_year', 'like', $term)
                    ->orWhere('tb_isps_review.shore_status', 'like', $term)
                    ->orWhere('tb_isps_review.review_recommendation', 'like', $term)
                    ->orWhere('tb_manual_documents.reference_no', 'like', $term);
            });
        }

        $sortMap = [
            'review_date' => 'tb_isps_review.review_date',
            'added_by' => 'tb_isps_review.added_by',
            'review_quarter' => 'tb_isps_review.review_quarter',
            'review_year' => 'tb_isps_review.review_year',
            'shore_status' => 'tb_isps_review.shore_status',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_isps_review.review_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(function ($r) use ($vessels) {
            $chapterRef = $r->manDocID !== '' ? $r->chapter_ref_from_doc : $r->chapter_ref_direct;
            $docRef = $r->manDocID !== '' ? $r->reference_no : null;
            $sms = trim(implode(' / ', array_filter([$chapterRef, $docRef])));
            if ($r->manual_section) {
                $sms .= " ({$r->manual_section})";
            }

            return [
                'id' => $r->reviewID,
                'vessel' => $vessels[$r->vesID] ?? '',
                'review_date' => $r->review_date,
                'added_by' => $r->added_by,
                'review_quarter' => (int) ltrim((string) $r->review_quarter, 'Q'),
                'review_year' => $r->review_year,
                'sms' => trim($sms),
                'review_recommendation' => $r->review_recommendation,
                'has_vessel_remarks' => filled($r->vessel_remarks),
                'has_shore_remarks' => filled($r->shore_remarks),
                'shore_status' => $r->shore_status,
                'can_edit' => false,
                'can_approve' => false,
                'can_recommend_approval' => false,
                'can_disapprove' => false,
                'can_disregard' => false,
                'can_delete' => false,
                'can_reopen' => false,
            ];
        })->all();

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
     * dashboard's clickable review_date column and this module's own
     * View modal. Reads tb_isps_review directly from the legacy
     * connection.
     */
    public function legacyDetail(string $reviewID): ?array
    {
        $r = DB::connection('legacy')->table('tb_isps_review')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_isps_review.manDocID')
            ->leftJoin('tb_manual_chapter as chapter_from_doc', 'chapter_from_doc.chapterID', '=', 'tb_manual_documents.chapterID')
            ->leftJoin('tb_manual_chapter as chapter_direct', 'chapter_direct.chapterID', '=', 'tb_isps_review.chapterID')
            ->where('tb_isps_review.reviewID', $reviewID)
            ->select(['tb_isps_review.*', 'tb_manual_documents.reference_no', 'chapter_from_doc.reference_no as chapter_ref_from_doc', 'chapter_direct.reference_no as chapter_ref_direct'])
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

        $chapterRef = $r->manDocID !== '' ? $r->chapter_ref_from_doc : $r->chapter_ref_direct;
        $docRef = $r->manDocID !== '' ? $r->reference_no : null;
        $sms = trim(implode(' / ', array_filter([$chapterRef, $docRef])));
        if ($r->manual_section) {
            $sms .= " ({$r->manual_section})";
        }

        return $this->toDetailArray([
            'id' => $r->reviewID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'review_date' => $zeroDateToNull($r->review_date),
            'added_by' => $r->added_by,
            'review_quarter' => (int) ltrim((string) $r->review_quarter, 'Q'),
            'review_year' => $r->review_year,
            'sms' => trim($sms),
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
            'id' => $r['id'],
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyChapterOptions(): array
    {
        return DB::connection('legacy')->table('tb_manual_chapter')->orderBy('reference_no')->get()
            ->map(fn ($c) => ['id' => $c->chapterID, 'label' => "({$c->reference_no}) {$c->chapter_name}"])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyDocumentOptionsForChapter(string $chapterId): array
    {
        return DB::connection('legacy')->table('tb_manual_documents')->where('chapterID', $chapterId)->orderBy('reference_no')->get()
            ->map(fn ($d) => ['id' => $d->manDocID, 'label' => "({$d->reference_no}) {$d->manual_name}"])
            ->all();
    }
}
