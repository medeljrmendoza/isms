import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { NonconformityDetail } from "../nonconformities/nonconformity";
import type { DefectDetail } from "../defects/defects";
import type { SireReportDetail } from "../sire/sire";
import type { NonSireReportDetail } from "../nonSire/nonSire";
import type { FlagStateReportDetail } from "../flagState/flagState";
import type { PscReportDetail } from "../pscReports/pscReport";
import type { ExternalAuditDetail } from "../externalAudits/externalAudit";
import type { CompanyInspectionDetail } from "../companyInspections/companyInspection";
import type { InternalAuditDetail } from "../internalAudits/internalAudit";
import type { IncidentReportDetail } from "../incidentReports/incidentReport";
import type { CommitteeMeetingDetail } from "../committeeMeetings/committeeMeeting";
import type { RiskAssessmentDetail } from "../riskAssessment/riskAssessment";
import type { MasterReviewDetail } from "../masterReview/masterReview";
import type { IspsReviewDetail } from "../ispsReview/ispsReview";
import type { ClaimDetail } from "../claims/claim";
import type { PendingItemsRow, TableResponse } from "./dashboard";

export interface TableParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const dashboardTableService = {
  async fetch(endpoint: string, params: TableParams): Promise<TableResponse> {
    const response = await axiosClient.get<ApiResource<TableResponse>>(endpoint, { params });
    return response.data.data;
  },

  async fetchNonconformityDetail(recordId: string): Promise<NonconformityDetail> {
    const response = await axiosClient.get<ApiResource<NonconformityDetail>>(`/dashboard/nonconformities/${recordId}`);
    return response.data.data;
  },

  async fetchDefectDetail(recordId: string): Promise<DefectDetail> {
    const response = await axiosClient.get<ApiResource<DefectDetail>>(`/dashboard/defects/${recordId}`);
    return response.data.data;
  },

  async fetchSireDetail(recordId: string): Promise<SireReportDetail> {
    const response = await axiosClient.get<ApiResource<SireReportDetail>>(`/dashboard/sire/${recordId}`);
    return response.data.data;
  },

  async fetchNonSireDetail(recordId: string): Promise<NonSireReportDetail> {
    const response = await axiosClient.get<ApiResource<NonSireReportDetail>>(`/dashboard/non-sire/${recordId}`);
    return response.data.data;
  },

  async fetchFlagStateDetail(recordId: string): Promise<FlagStateReportDetail> {
    const response = await axiosClient.get<ApiResource<FlagStateReportDetail>>(`/dashboard/flag-state/${recordId}`);
    return response.data.data;
  },

  async fetchPscDetail(recordId: string): Promise<PscReportDetail> {
    const response = await axiosClient.get<ApiResource<PscReportDetail>>(`/dashboard/psc-inspections/${recordId}`);
    return response.data.data;
  },

  async fetchExternalAuditDetail(recordId: string): Promise<ExternalAuditDetail> {
    const response = await axiosClient.get<ApiResource<ExternalAuditDetail>>(`/dashboard/external-audits/${recordId}`);
    return response.data.data;
  },

  async fetchCompanyInspectionDetail(recordId: string): Promise<CompanyInspectionDetail> {
    const response = await axiosClient.get<ApiResource<CompanyInspectionDetail>>(`/dashboard/company-inspections/${recordId}`);
    return response.data.data;
  },

  async fetchInternalAuditDetail(recordId: string): Promise<InternalAuditDetail> {
    const response = await axiosClient.get<ApiResource<InternalAuditDetail>>(`/dashboard/internal-audits/${recordId}`);
    return response.data.data;
  },

  async fetchIncidentReportDetail(recordId: string): Promise<IncidentReportDetail> {
    const response = await axiosClient.get<ApiResource<IncidentReportDetail>>(`/dashboard/incident-reports/${recordId}`);
    return response.data.data;
  },

  async fetchCommitteeMeetingDetail(recordId: string): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.get<ApiResource<CommitteeMeetingDetail>>(`/dashboard/committee-meetings/${recordId}`);
    return response.data.data;
  },

  async fetchRiskAssessmentDetail(recordId: string): Promise<RiskAssessmentDetail> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentDetail>>(`/dashboard/risk-assessments/${recordId}`);
    return response.data.data;
  },

  async fetchMasterReviewDetail(recordId: string): Promise<MasterReviewDetail> {
    const response = await axiosClient.get<ApiResource<MasterReviewDetail>>(`/dashboard/master-reviews/${recordId}`);
    return response.data.data;
  },

  async fetchIspsReviewDetail(recordId: string): Promise<IspsReviewDetail> {
    const response = await axiosClient.get<ApiResource<IspsReviewDetail>>(`/dashboard/isps-reviews/${recordId}`);
    return response.data.data;
  },

  async fetchClaimDetail(recordId: string): Promise<ClaimDetail> {
    const response = await axiosClient.get<ApiResource<ClaimDetail>>(`/dashboard/claims/${recordId}`);
    return response.data.data;
  },

  async fetchPendingItems(): Promise<PendingItemsRow[]> {
    const response = await axiosClient.get<ApiResource<{ rows: PendingItemsRow[] }>>("/dashboard/pending-items");
    return response.data.data.rows;
  },
};
