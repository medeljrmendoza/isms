<?php

namespace App\Repositories\PendingItems;

use App\Models\Vessel;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Repositories\Defects\DefectRepository;
use App\Repositories\ExternalAudits\ExternalAuditReportRepository;
use App\Repositories\FlagState\FlagStateReportRepository;
use App\Repositories\IncidentReports\IncidentReportRepository;
use App\Repositories\InternalAudits\InternalAuditReportRepository;
use App\Repositories\IspsReview\IspsReviewRepository;
use App\Repositories\MasterReview\MasterReviewRepository;
use App\Repositories\NonSire\NonSireReportRepository;
use App\Repositories\Nonconformities\NonconformityRepository;
use App\Repositories\PscReports\PscReportRepository;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Repositories\Sire\SireReportRepository;
use App\Support\LegacyDb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard_summary.php's index() + the 13
 * count_summary_* endpoints — the dashboard's "Pending Items" dashlet, a
 * per-vessel matrix of pending counts across every other module. Legacy
 * computes each cell with its own per-vessel AJAX call; this computes
 * every vessel's count for a category in one grouped query instead.
 *
 * Several legacy WHERE clauses correlate a subquery back to the outer
 * row only to immediately test IN-membership on that same value (e.g.
 * "audit_ref IN (SELECT source_of_nc_ref_no ... WHERE source_of_nc_ref_no
 * = audit_ref AND ...)") — that self-correlation is redundant: for a
 * specific outer value X, "X IN (SELECT col WHERE col = X AND cond)" and
 * "X IN (SELECT col WHERE cond)" are the same truth value, since col is
 * a primary/unique key. Dropped throughout below, and External/Flag
 * State's WHERE (which OR's the same added_by/is_published/is_approved
 * branch into itself twice) is likewise flattened — see each method's
 * inline note.
 */
class PendingItemsRepository
{
    public function __construct(
        private readonly IncidentReportRepository $incidentReports,
        private readonly AuditReportRepository $companyInspections,
        private readonly InternalAuditReportRepository $internalAudits,
        private readonly ExternalAuditReportRepository $externalAudits,
        private readonly PscReportRepository $pscReports,
        private readonly RiskAssessmentRepository $riskAssessments,
        private readonly SireReportRepository $sireReports,
        private readonly NonSireReportRepository $nonSireReports,
        private readonly FlagStateReportRepository $flagStateReports,
        private readonly NonconformityRepository $nonconformities,
        private readonly DefectRepository $defects,
        private readonly MasterReviewRepository $masterReviews,
        private readonly IspsReviewRepository $ispsReviews,
    ) {}

    /** Local seed-data path: every vessel, no per-user scoping (same as every other dashlet's local path). */
    public function table(): array
    {
        $vessels = Vessel::query()->orderBy('name')->get();

        $counts = [
            'incident' => $this->countsByVessel($this->incidentReports->pendingQuery()),
            'company' => $this->countsByVessel($this->companyInspections->pendingQuery()),
            'internal' => $this->countsByVessel($this->internalAudits->pendingQuery()),
            'external' => $this->countsByVessel($this->externalAudits->pendingQuery()),
            'psc' => $this->countsByVessel($this->pscReports->pendingQuery()),
            'risk_assessment' => $this->countsByVessel($this->riskAssessments->pendingQuery()),
            'sire' => $this->countsByVessel($this->sireReports->pendingQuery()),
            'non_sire' => $this->countsByVessel($this->nonSireReports->pendingQuery()),
            'flag_state' => $this->countsByVessel($this->flagStateReports->pendingQuery()),
            'nc' => $this->countsByVessel($this->nonconformities->pendingQuery()),
            'defect' => $this->countsByVessel($this->defects->pendingQuery()),
            'master_review' => $this->countsByVessel($this->masterReviews->pendingQuery()),
            'isps_review' => $this->countsByVessel($this->ispsReviews->pendingQuery()),
        ];

        return $vessels->map(fn (Vessel $v) => $this->row((string) $v->id, $v->display_name, $counts))->all();
    }

    /** @return array<int|string, int> vessel_id => pending count */
    private function countsByVessel(Builder $builder): array
    {
        return $builder->toBase()
            ->whereNotNull('vessel_id')
            ->selectRaw('vessel_id, COUNT(*) as cnt')
            ->groupBy('vessel_id')
            ->pluck('cnt', 'vessel_id')
            ->all();
    }

    /**
     * Legacy path. Ported from index()'s summary_query: vessels the user
     * is assigned to, restricted to ACTIVE vessels under an ACTIVE
     * principal (same rule as LegacyDb::activeVesselIdsWithActivePrincipal(),
     * lifted from Dashboard_pms.php's identically-shaped pms_vessel_query).
     */
    public function legacyTable(?string $legacyUserId): array
    {
        $vesselIds = LegacyDb::assignedVesselIds($legacyUserId)
            ->intersect(LegacyDb::activeVesselIdsWithActivePrincipal())
            ->values();

        if ($vesselIds->isEmpty()) {
            return [];
        }

        $vesselNames = LegacyDb::vesselNames();

        $counts = [
            'incident' => $this->legacyIncidentCounts($vesselIds),
            'company' => $this->legacyAuditCounts('tb_audit_report', 'audit_ref', 'auditID', $vesselIds),
            'internal' => $this->legacyAuditCounts('tb_internal_audit_report', 'audit_ref', 'auditID', $vesselIds),
            'external' => $this->legacyAddedByAuditCounts('tb_external_audit_report', 'externalID', $vesselIds),
            'psc' => $this->legacyAuditCounts('tb_psc_report', 'ref_no', 'pscreportid', $vesselIds, 'vesid'),
            'risk_assessment' => $this->legacyRiskAssessmentCounts($vesselIds),
            'sire' => $this->legacyObservationModuleCounts('tb_sire', 'sireID', $vesselIds),
            'non_sire' => $this->legacyObservationModuleCounts('tb_non_sire', 'nonsireID', $vesselIds),
            'flag_state' => $this->legacyAddedByAuditCounts('tb_flag_state', 'flagID', $vesselIds),
            'nc' => $this->legacyNonconformityCounts($vesselIds),
            'defect' => $this->legacyDefectCounts($vesselIds),
            'master_review' => $this->legacyReviewCounts('tb_master_review', $vesselIds),
            'isps_review' => $this->legacyReviewCounts('tb_isps_review', $vesselIds),
        ];

        return $vesselIds
            ->map(fn ($vesID) => $this->row($vesID, $vesselNames[$vesID] ?? '', $counts))
            ->sortBy('vessel')
            ->values()
            ->all();
    }

    /** Ported from count_summary_incident(). */
    private function legacyIncidentCounts(Collection $vesselIds): array
    {
        return DB::connection('legacy')->table('tb_incident_report')
            ->where(function ($q) {
                $q->whereNull('closing_date')->orWhere('closing_date', '0000-00-00')->orWhere('is_approved', '0');
            })
            ->whereIn('vesid', $vesselIds)
            ->selectRaw('vesid, COUNT(*) as cnt')->groupBy('vesid')->pluck('cnt', 'vesid')->all();
    }

    /**
     * Shared shape for Company/Internal/PSC: is_deleted='0' AND
     * (refColumn has an open, non-inactive nonconformity OR idColumn has
     * a pending, non-completed observation). Ported from
     * count_summary_company()/count_summary_internal()/count_summary_psc().
     */
    private function legacyAuditCounts(string $table, string $refColumn, string $idColumn, Collection $vesselIds, string $vesIdColumn = 'vesID'): array
    {
        return DB::connection('legacy')->table($table)
            ->where('is_deleted', '0')
            ->where(function ($q) use ($refColumn, $idColumn) {
                $q->whereIn($refColumn, $this->openNonconformityRefs())
                    ->orWhereIn($idColumn, $this->pendingObservationIds('!='));
            })
            ->whereIn($vesIdColumn, $vesselIds)
            ->selectRaw("{$vesIdColumn}, COUNT(*) as cnt")->groupBy($vesIdColumn)->pluck('cnt', $vesIdColumn)->all();
    }

    /**
     * Shared shape for External/Flag State: is_deleted='0' AND (P OR
     * ref_no has an open nonconformity OR idColumn has a pending
     * observation), where P = (SHORE, published, unapproved) OR
     * (VESSEL, unapproved). Ported from count_summary_external()/
     * count_summary_flag_state(), which each OR the same P branch into
     * itself twice — flattened here, see class docblock.
     */
    private function legacyAddedByAuditCounts(string $table, string $idColumn, Collection $vesselIds): array
    {
        return DB::connection('legacy')->table($table)
            ->where('is_deleted', '0')
            ->where(function ($q) use ($idColumn) {
                $q->where(function ($shore) {
                    $shore->where('added_by', 'SHORE')->where('is_published', '1')->where('is_approved', '0');
                })->orWhere(function ($vessel) {
                    $vessel->where('added_by', 'VESSEL')->where('is_approved', '0');
                })->orWhereIn('ref_no', $this->openNonconformityRefs())
                    ->orWhereIn($idColumn, $this->pendingObservationIds('!='));
            })
            ->whereIn('vesID', $vesselIds)
            ->selectRaw('vesID, COUNT(*) as cnt')->groupBy('vesID')->pluck('cnt', 'vesID')->all();
    }

    /** Ported from count_summary_risk_assessment(). */
    private function legacyRiskAssessmentCounts(Collection $vesselIds): array
    {
        return DB::connection('legacy')->table('tb_risk_assessment')
            ->where(function ($q) {
                $q->where(function ($shore) {
                    $shore->where('approval_from_shore', '1')->where('shore_is_approved', '0');
                })->orWhere(function ($marine) {
                    $marine->where('approval_from_marine', '1')->where('marine_shore_is_approved', '0');
                });
            })
            ->whereIn('vesid', $vesselIds)
            ->selectRaw('vesid, COUNT(*) as cnt')->groupBy('vesid')->pluck('cnt', 'vesid')->all();
    }

    /**
     * Shared shape for SIRE/Non-SIRE: is_deleted='0' AND ((published,
     * unapproved) OR idColumn has a non-completed, non-deleted
     * observation). Ported from count_summary_sire()/count_summary_non_sire().
     */
    private function legacyObservationModuleCounts(string $table, string $idColumn, Collection $vesselIds): array
    {
        return DB::connection('legacy')->table($table)
            ->where('is_deleted', '0')
            ->where(function ($q) use ($idColumn) {
                $q->where(function ($published) {
                    $published->where('is_published', '1')->where('is_approved', '0');
                })->orWhereIn($idColumn, $this->pendingObservationIds('='));
            })
            ->whereIn('vesID', $vesselIds)
            ->selectRaw('vesID, COUNT(*) as cnt')->groupBy('vesID')->pluck('cnt', 'vesID')->all();
    }

    /** Ported from count_summary_nc(). Same rule as NonconformityRepository::legacyTable(). */
    private function legacyNonconformityCounts(Collection $vesselIds): array
    {
        $openOrUnapproved = function ($q) {
            $q->where(function ($q2) {
                $q2->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->orWhere(function ($q2) {
                $q2->where('is_approved', '0')->whereNotIn('source_of_nc', ['FLAG STATE', 'PSC INSPECTION', 'COMPANY INSPECTION', 'INTERNAL AUDIT']);
            });
        };

        return DB::connection('legacy')->table('tb_nonconformities')
            ->where('is_inactive', '!=', '1')
            ->where(function ($q) use ($openOrUnapproved) {
                $q->where(function ($vessel) use ($openOrUnapproved) {
                    $vessel->where('added_by', 'VESSEL')->where($openOrUnapproved);
                })->orWhere(function ($shore) use ($openOrUnapproved) {
                    $shore->where('added_by', 'SHORE')->where(function ($publishBranch) use ($openOrUnapproved) {
                        $publishBranch->where(function ($unpublished) {
                            $unpublished->where('is_published', '0')->where(function ($q2) {
                                $q2->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                            });
                        })->orWhere(function ($published) use ($openOrUnapproved) {
                            $published->where('is_published', '1')->where($openOrUnapproved);
                        });
                    });
                });
            })
            ->whereIn('vesID', $vesselIds)
            ->selectRaw('vesID, COUNT(*) as cnt')->groupBy('vesID')->pluck('cnt', 'vesID')->all();
    }

    /** Ported from count_summary_defect(). */
    private function legacyDefectCounts(Collection $vesselIds): array
    {
        return DB::connection('legacy')->table('tb_defect_list')
            ->where('compl_code', '!=', 'C')
            ->whereIn('vesID', $vesselIds)
            ->selectRaw('vesID, COUNT(*) as cnt')->groupBy('vesID')->pluck('cnt', 'vesID')->all();
    }

    /**
     * Shared shape for Master Review/ISPS Review: open (shore_status=''),
     * not deleted, and the VESSEL side has already approved it — i.e.
     * items specifically awaiting shore action for that vessel, unlike
     * the module's own dashlet which also includes fresh SHORE-added
     * items. Ported from count_summary_master_review()/count_summary_isps_review().
     */
    private function legacyReviewCounts(string $table, Collection $vesselIds): array
    {
        return DB::connection('legacy')->table($table)
            ->where('shore_status', '')
            ->where('is_deleted', '0')
            ->where('is_vessel_approved', '1')
            ->whereIn('vesID', $vesselIds)
            ->selectRaw('vesID, COUNT(*) as cnt')->groupBy('vesID')->pluck('cnt', 'vesID')->all();
    }

    private function openNonconformityRefs(): \Closure
    {
        return function ($q) {
            $q->select('source_of_nc_ref_no')->from('tb_nonconformities')
                ->where('is_inactive', '!=', '1')
                ->where(function ($q2) {
                    $q2->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                });
        };
    }

    private function pendingObservationIds(string $deletedOperator): \Closure
    {
        return function ($q) use ($deletedOperator) {
            $q->select('reportID')->from('tb_observations')
                ->where('is_deleted', $deletedOperator, $deletedOperator === '=' ? '0' : '1')
                ->where('status', '!=', 'COMPLETED');
        };
    }

    /** @param array<string, array<int|string, int>> $counts */
    private function row(int|string $vesselKey, string $vesselName, array $counts): array
    {
        return [
            'vessel_id' => (string) $vesselKey,
            'vessel' => $vesselName,
            'incident' => $counts['incident'][$vesselKey] ?? 0,
            'company' => $counts['company'][$vesselKey] ?? 0,
            'internal' => $counts['internal'][$vesselKey] ?? 0,
            'external' => $counts['external'][$vesselKey] ?? 0,
            'psc' => $counts['psc'][$vesselKey] ?? 0,
            'risk_assessment' => $counts['risk_assessment'][$vesselKey] ?? 0,
            'sire' => $counts['sire'][$vesselKey] ?? 0,
            'non_sire' => $counts['non_sire'][$vesselKey] ?? 0,
            'flag_state' => $counts['flag_state'][$vesselKey] ?? 0,
            'nc' => $counts['nc'][$vesselKey] ?? 0,
            'defect' => $counts['defect'][$vesselKey] ?? 0,
            'master_review' => $counts['master_review'][$vesselKey] ?? 0,
            'isps_review' => $counts['isps_review'][$vesselKey] ?? 0,
        ];
    }
}
