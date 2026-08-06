import { useState } from "react";
import type { Dashlet, TableRow } from "./dashboard";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { DashletTable } from "./DashletTable";
import { dashboardTableService } from "./dashboardTableService";
import { NonconformityViewModal } from "../nonconformities/NonconformityViewModal";
import type { NonconformityDetail } from "../nonconformities/nonconformity";
import { DefectViewModal } from "../defects/DefectViewModal";
import type { DefectDetail } from "../defects/defects";
import { SireReportViewModal } from "../sire/SireReportViewModal";
import type { SireReportDetail } from "../sire/sire";
import { NonSireReportViewModal } from "../nonSire/NonSireReportViewModal";
import type { NonSireReportDetail } from "../nonSire/nonSire";
import { FlagStateReportViewModal } from "../flagState/FlagStateReportViewModal";
import type { FlagStateReportDetail } from "../flagState/flagState";
import { PscReportViewModal } from "../pscReports/PscReportViewModal";
import type { PscReportDetail } from "../pscReports/pscReport";
import { ExternalAuditViewModal } from "../externalAudits/ExternalAuditViewModal";
import type { ExternalAuditDetail } from "../externalAudits/externalAudit";
import { CompanyInspectionViewModal } from "../companyInspections/CompanyInspectionViewModal";
import type { CompanyInspectionDetail } from "../companyInspections/companyInspection";
import { InternalAuditViewModal } from "../internalAudits/InternalAuditViewModal";
import type { InternalAuditDetail } from "../internalAudits/internalAudit";
import { IncidentReportViewModal } from "../incidentReports/IncidentReportViewModal";
import type { IncidentReportDetail } from "../incidentReports/incidentReport";
import { CommitteeMeetingViewModal } from "../committeeMeetings/CommitteeMeetingViewModal";
import type { CommitteeMeetingDetail } from "../committeeMeetings/committeeMeeting";
import { RiskAssessmentViewModal } from "../riskAssessment/RiskAssessmentViewModal";
import type { RiskAssessmentDetail } from "../riskAssessment/riskAssessment";
import { MasterReviewViewModal } from "../masterReview/MasterReviewViewModal";
import type { MasterReviewDetail } from "../masterReview/masterReview";
import { IspsReviewViewModal } from "../ispsReview/IspsReviewViewModal";
import type { IspsReviewDetail } from "../ispsReview/ispsReview";
import { PendingItemsGrid } from "./PendingItemsGrid";

function DashletList({ items }: { items: Dashlet["items"] }) {
  if (items.length === 0) {
    return <p className="py-4 text-sm text-slate-400">No items.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100">
      {items.map((item) => (
        <li key={item.label} className="flex flex-col gap-0.5 py-2">
          <span className="text-sm text-slate-800">{item.label}</span>
          <span className="text-xs text-slate-400">{item.meta}</span>
        </li>
      ))}
    </ul>
  );
}

/** The dashlet key + row column that legacy makes clickable to open a view modal (NCR NO. / SL NO. / etc). */
const LINK_COLUMNS: Record<string, string> = {
  nonconformities: "ncr_no",
  defect_list: "sl_no",
  sire: "vessel",
  non_sire: "vessel",
  flag_state: "ref_no",
  psc: "ref_no",
  external: "ref_no",
  company_inspections: "audit_ref",
  internal_audit_reports: "audit_ref",
  incident: "vessel",
  committee_meeting: "meeting_date",
  risk_assessment: "report_no",
  master_review: "review_date",
  isps_review: "review_date",
};

export function DashletCard({ dashlet }: { dashlet: Dashlet }) {
  const [loaded, setLoaded] = useState(!dashlet.manual_load);
  const [loading, setLoading] = useState(false);
  const [showLarger, setShowLarger] = useState(false);
  const [nonconformity, setNonconformity] = useState<NonconformityDetail | null>(null);
  const [defect, setDefect] = useState<DefectDetail | null>(null);
  const [sireReport, setSireReport] = useState<SireReportDetail | null>(null);
  const [nonSireReport, setNonSireReport] = useState<NonSireReportDetail | null>(null);
  const [flagStateReport, setFlagStateReport] = useState<FlagStateReportDetail | null>(null);
  const [pscReport, setPscReport] = useState<PscReportDetail | null>(null);
  const [externalAudit, setExternalAudit] = useState<ExternalAuditDetail | null>(null);
  const [companyInspection, setCompanyInspection] = useState<CompanyInspectionDetail | null>(null);
  const [internalAudit, setInternalAudit] = useState<InternalAuditDetail | null>(null);
  const [incidentReport, setIncidentReport] = useState<IncidentReportDetail | null>(null);
  const [committeeMeeting, setCommitteeMeeting] = useState<CommitteeMeetingDetail | null>(null);
  const [riskAssessment, setRiskAssessment] = useState<RiskAssessmentDetail | null>(null);
  const [masterReview, setMasterReview] = useState<MasterReviewDetail | null>(null);
  const [ispsReview, setIspsReview] = useState<IspsReviewDetail | null>(null);
  const isMatrix = dashlet.key === "pending_items";
  const isTable = !isMatrix && dashlet.columns !== null && dashlet.endpoint !== null;
  const linkColumn = LINK_COLUMNS[dashlet.key];

  const handleLoad = () => {
    setLoading(true);
    // Legacy dashlets fetched this over AJAX per-panel; ours already has
    // the data, so this just preserves the original click-to-load UX.
    window.setTimeout(() => {
      setLoading(false);
      setLoaded(true);
    }, 400);
  };

  const handleLinkClick = (row: TableRow) => {
    const recordId = String(row.record_id);
    if (dashlet.key === "nonconformities") {
      dashboardTableService.fetchNonconformityDetail(recordId).then(setNonconformity);
    } else if (dashlet.key === "defect_list") {
      dashboardTableService.fetchDefectDetail(recordId).then(setDefect);
    } else if (dashlet.key === "sire") {
      dashboardTableService.fetchSireDetail(recordId).then(setSireReport);
    } else if (dashlet.key === "non_sire") {
      dashboardTableService.fetchNonSireDetail(recordId).then(setNonSireReport);
    } else if (dashlet.key === "flag_state") {
      dashboardTableService.fetchFlagStateDetail(recordId).then(setFlagStateReport);
    } else if (dashlet.key === "psc") {
      dashboardTableService.fetchPscDetail(recordId).then(setPscReport);
    } else if (dashlet.key === "external") {
      dashboardTableService.fetchExternalAuditDetail(recordId).then(setExternalAudit);
    } else if (dashlet.key === "company_inspections") {
      dashboardTableService.fetchCompanyInspectionDetail(recordId).then(setCompanyInspection);
    } else if (dashlet.key === "internal_audit_reports") {
      dashboardTableService.fetchInternalAuditDetail(recordId).then(setInternalAudit);
    } else if (dashlet.key === "incident") {
      dashboardTableService.fetchIncidentReportDetail(recordId).then(setIncidentReport);
    } else if (dashlet.key === "committee_meeting") {
      dashboardTableService.fetchCommitteeMeetingDetail(recordId).then(setCommitteeMeeting);
    } else if (dashlet.key === "risk_assessment") {
      dashboardTableService.fetchRiskAssessmentDetail(recordId).then(setRiskAssessment);
    } else if (dashlet.key === "master_review") {
      dashboardTableService.fetchMasterReviewDetail(recordId).then(setMasterReview);
    } else if (dashlet.key === "isps_review") {
      dashboardTableService.fetchIspsReviewDetail(recordId).then(setIspsReview);
    }
  };

  return (
    <div
      className={`flex flex-col rounded-lg border border-slate-200 bg-white shadow-sm ${
        dashlet.span === "full" ? "lg:col-span-2" : ""
      }`}
    >
      <div className="flex items-center justify-between rounded-t-lg border-b border-sky-100 bg-sky-50 px-4 py-3">
        <span className="text-sm font-semibold text-slate-800">{dashlet.title}</span>
        <div className="flex items-center gap-2">
          {dashlet.extra_action === "add_task" && (
            <Button
              type="button"
              variant="success"
              className="!px-2 !py-1 text-xs"
              disabled
              title="Task module not yet migrated"
            >
              + Add Task
            </Button>
          )}
          {dashlet.extra_action === "export_import" && (
            <>
              <Button
                type="button"
                variant="success"
                className="!px-2 !py-1 text-xs"
                disabled
                title="Not available in this migration — depends on the vessel-side sync application, which has no counterpart here"
              >
                Export
              </Button>
              <Button
                type="button"
                variant="success"
                className="!px-2 !py-1 text-xs"
                disabled
                title="Not available in this migration — depends on the vessel-side sync application, which has no counterpart here"
              >
                Import
              </Button>
            </>
          )}
          {loaded && (
            <Button
              type="button"
              variant="info"
              className="!px-2 !py-1 text-xs"
              onClick={() => setShowLarger(true)}
            >
              Show Larger
            </Button>
          )}
        </div>
      </div>

      <div className={`${isTable ? "max-h-96" : "max-h-56"} overflow-y-auto px-4 py-2`}>
        {!loaded && !loading && (
          <div className="flex flex-col items-start gap-2 py-3">
            <p className="text-sm text-amber-700">Please click the load button.</p>
            <Button type="button" variant="secondary" className="!px-2 !py-1 text-xs" onClick={handleLoad}>
              Load
            </Button>
          </div>
        )}

        {loading && (
          <div className="flex items-center gap-2 py-3 text-sm text-amber-700">
            <span className="h-3 w-3 animate-spin rounded-full border-2 border-amber-300 border-t-amber-600" />
            Processing data...
          </div>
        )}

        {loaded &&
          !loading &&
          (isMatrix ? (
            <PendingItemsGrid />
          ) : isTable ? (
            <DashletTable
              endpoint={dashlet.endpoint!}
              columns={dashlet.columns!}
              defaultDirection={dashlet.key === "pms" ? "asc" : undefined}
              linkColumn={linkColumn}
              onLinkClick={linkColumn ? handleLinkClick : undefined}
            />
          ) : (
            <DashletList items={dashlet.items} />
          ))}
      </div>

      {showLarger && (
        <Modal title={dashlet.title} onClose={() => setShowLarger(false)}>
          {isMatrix ? (
            <PendingItemsGrid />
          ) : isTable ? (
            <DashletTable
              endpoint={dashlet.endpoint!}
              columns={dashlet.columns!}
              defaultDirection={dashlet.key === "pms" ? "asc" : undefined}
              linkColumn={linkColumn}
              onLinkClick={linkColumn ? handleLinkClick : undefined}
            />
          ) : (
            <DashletList items={dashlet.items} />
          )}
        </Modal>
      )}

      {nonconformity && <NonconformityViewModal nonconformity={nonconformity} onClose={() => setNonconformity(null)} />}
      {defect && <DefectViewModal defect={defect} onClose={() => setDefect(null)} />}
      {sireReport && <SireReportViewModal sireReport={sireReport} onClose={() => setSireReport(null)} />}
      {nonSireReport && <NonSireReportViewModal nonSireReport={nonSireReport} onClose={() => setNonSireReport(null)} />}
      {flagStateReport && <FlagStateReportViewModal flagStateReport={flagStateReport} onClose={() => setFlagStateReport(null)} />}
      {pscReport && <PscReportViewModal pscReport={pscReport} onClose={() => setPscReport(null)} />}
      {externalAudit && <ExternalAuditViewModal externalAudit={externalAudit} onClose={() => setExternalAudit(null)} />}
      {companyInspection && <CompanyInspectionViewModal companyInspection={companyInspection} onClose={() => setCompanyInspection(null)} />}
      {internalAudit && <InternalAuditViewModal internalAudit={internalAudit} onClose={() => setInternalAudit(null)} />}
      {incidentReport && <IncidentReportViewModal incidentReport={incidentReport} onClose={() => setIncidentReport(null)} />}
      {committeeMeeting && <CommitteeMeetingViewModal committeeMeeting={committeeMeeting} onClose={() => setCommitteeMeeting(null)} />}
      {riskAssessment && <RiskAssessmentViewModal report={riskAssessment} onClose={() => setRiskAssessment(null)} />}
      {masterReview && <MasterReviewViewModal review={masterReview} onClose={() => setMasterReview(null)} />}
      {ispsReview && <IspsReviewViewModal review={ispsReview} onClose={() => setIspsReview(null)} />}
    </div>
  );
}
