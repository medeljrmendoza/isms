<?php

namespace App\Services;

use App\Repositories\Claims\ClaimRepository;
use App\Repositories\CommitteeMeetings\CommitteeMeetingRepository;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Repositories\Defects\DefectRepository;
use App\Repositories\Drills\DrillRepository;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use App\Repositories\ExternalAudits\ExternalAuditReportRepository;
use App\Repositories\FlagState\FlagStateReportRepository;
use App\Repositories\IncidentReports\IncidentReportRepository;
use App\Repositories\InternalAudits\InternalAuditReportRepository;
use App\Repositories\IspsReview\IspsReviewRepository;
use App\Repositories\ManualPublish\ManualDocumentPublishRepository;
use App\Repositories\ManualPublish\SmsVersionMonitoringRepository;
use App\Repositories\MasterReview\MasterReviewRepository;
use App\Repositories\Nonconformities\NonconformityRepository;
use App\Repositories\NonSire\NonSireReportRepository;
use App\Repositories\Pms\PmsRepository;
use App\Repositories\PscReports\PscReportRepository;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Repositories\Sire\SireReportRepository;
use App\Repositories\Tasks\TaskRepository;
use App\Repositories\VesselDocumentation\VesselDocumentationRepository;
use App\Repositories\VesselExports\VesselExportRepository;

/**
 * TEMPORARY: most dashlets below still return hand-written placeholder
 * data as a flat `items` list. The legacy app's dashboard pulled from
 * ~25 modules (PSC, incidents, risk assessment, SIRE, committee
 * meetings, etc.) that haven't been migrated to Laravel yet.
 *
 * Dashlets backed by a real module (nonconformities, claims,
 * exposure_hours) instead carry `columns` + `endpoint`: the actual row
 * data is fetched separately by DashletTable from a dedicated paginated
 * endpoint (see DashboardController), the same way the legacy dashboard
 * lazy-loaded each dashlet's interactive DataTable independently. The
 * rest should switch from `items` to `columns`/`endpoint` the same way
 * as their modules migrate.
 */
class DashboardService
{
    /**
     * Mirrors the legacy nav bar's countExpired / countExpiring /
     * countUpdate values (document-expiry + system-update counts). Mock
     * until the vessel documentation and system-update modules migrate.
     */
    public function getNotificationCounts(): array
    {
        return [
            'expired' => 2,
            'expiring' => 5,
            'updates' => 1,
        ];
    }

    public function getDashletData(): array
    {
        return [
            $this->matrix('pending_items', 'Pending Items', '/dashboard/pending-items'),
            $this->dashlet('notifications', 'Notifications', items: [
                $this->item('Drill schedule updated for Q3', 'Fleet-wide'),
                $this->item('New SMS manual revision published', 'Rev. 14'),
            ]),
            $this->table('assigned_task', 'Assigned Task', '/dashboard/assigned-tasks', TaskRepository::columns(), extraAction: 'add_task'),
            $this->table('export_import', 'Export / Import', '/dashboard/vessel-exports', VesselExportRepository::columns(), extraAction: 'export_import'),
            $this->table('nonconformities', 'Non Conformities - In Progress / For Approval', '/dashboard/nonconformities', NonconformityRepository::columns()),
            $this->table('incident', 'Incident Report / HOR - In Progress', '/dashboard/incident-reports', IncidentReportRepository::columns()),
            $this->table('company_inspections', 'Company Inspections - Pending', '/dashboard/company-inspections', AuditReportRepository::columns()),
            $this->table('internal_audit_reports', 'Internal Audits - Pending', '/dashboard/internal-audits', InternalAuditReportRepository::columns()),
            $this->table('external', 'External Audits - Pending / For Approval', '/dashboard/external-audits', ExternalAuditReportRepository::columns()),
            $this->table('psc', 'PSC Inspections - Pending', '/dashboard/psc-inspections', PscReportRepository::columns()),
            $this->table('risk_assessment', 'Risk Assessment - For Approval', '/dashboard/risk-assessments', RiskAssessmentRepository::columns()),
            $this->table('sire', 'SIRE - Pending / For Approval', '/dashboard/sire', SireReportRepository::columns()),
            $this->table('non_sire', 'Non-SIRE - Pending / For Approval', '/dashboard/non-sire', NonSireReportRepository::columns()),
            $this->table('flag_state', 'Flag State - Pending / For Approval', '/dashboard/flag-state', FlagStateReportRepository::columns()),
            $this->table('committee_meeting', "Committee Meeting - For Shore's Remarks / For Approval", '/dashboard/committee-meetings', CommitteeMeetingRepository::columns()),
            $this->table('company_documentation', 'Company Documentation - Expiring / Expired', '/dashboard/company-documentation', CompanyDocumentationRepository::columns()),
            $this->table('defect_list', 'Defect List - Not Completed', '/dashboard/defects', DefectRepository::columns()),
            $this->table('claims', 'Claims - Open', '/dashboard/claims', ClaimRepository::columns()),
            $this->table('exposure_hours', 'Exposure Hours - Latest Record', '/dashboard/exposure-hours', ExposureHoursRepository::columns()),
            $this->table('master_review', 'Master Review - Open', '/dashboard/master-reviews', MasterReviewRepository::columns()),
            $this->table('isps_review', 'ISPS Review - Open', '/dashboard/isps-reviews', IspsReviewRepository::columns()),
            $this->table('sms_publish_manual', 'SMS - Publish Manual', '/dashboard/sms-publish-manual', ManualDocumentPublishRepository::columns()),
            $this->table('vessel_documentation', 'Vessel Documentation - Status Monitoring', '/dashboard/vessel-documentation', VesselDocumentationRepository::columns()),
            $this->table('sms_version_monitoring', 'SMS - Version Monitoring (Pending Updates)', '/dashboard/sms-version-monitoring', SmsVersionMonitoringRepository::columns()),
            $this->table('drill', 'Drill / Training Onboard - Upcoming / Overdue', '/dashboard/drills', DrillRepository::columns()),
            $this->table('pms', 'PMS - Maintenance Activities', '/dashboard/pms', PmsRepository::columns()),
        ];
    }

    private function dashlet(
        string $key,
        string $title,
        string $span = 'half',
        bool $manualLoad = false,
        array $items = [],
        ?string $extraAction = null,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'span' => $span,
            'manual_load' => $manualLoad,
            'extra_action' => $extraAction,
            'items' => $items,
            'columns' => null,
            'endpoint' => null,
        ];
    }

    /**
     * A dashlet backed by a real, interactive, paginated table (see
     * class docblock) instead of a static `items` list.
     */
    private function table(string $key, string $title, string $endpoint, array $columns, ?string $extraAction = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'span' => 'half',
            'manual_load' => false,
            'extra_action' => $extraAction,
            'items' => [],
            'columns' => $columns,
            'endpoint' => $endpoint,
        ];
    }

    private function item(string $label, string $meta): array
    {
        return ['label' => $label, 'meta' => $meta];
    }

    /**
     * The "Pending Items" dashlet: a per-vessel matrix of pending counts
     * across every other module, not a row-per-record table — same
     * `endpoint` mechanism as table(), but the frontend renders it with
     * a dedicated grid component instead of DashletTable.
     */
    private function matrix(string $key, string $title, string $endpoint): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'span' => 'full',
            'manual_load' => true,
            'extra_action' => null,
            'items' => [],
            'columns' => null,
            'endpoint' => $endpoint,
        ];
    }
}
