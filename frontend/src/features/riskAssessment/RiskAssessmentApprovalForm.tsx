import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { useState } from "react";
import { riskAssessmentApprovalSchema, type RiskAssessmentApprovalFormValues } from "./riskAssessmentApprovalSchema";
import { riskAssessmentService } from "./riskAssessmentService";
import type { RiskAssessmentDetail } from "./riskAssessment";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";
import { RiskAssessmentReportSummary } from "./RiskAssessmentReportSummary";

interface RiskAssessmentApprovalFormProps {
  report: RiskAssessmentDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

function detailToFormValues(r: RiskAssessmentDetail): RiskAssessmentApprovalFormValues {
  return {
    shore_approved: r.shore_is_approved ? "YES" : "NO",
    shore_date_approved: r.date_approved ?? "",
    shore_remarks: r.shore_remarks ?? "",
    marine_approved: r.marine_is_approved ? "YES" : "NO",
    marine_date_approved: r.marine_date_approved ?? "",
    marine_remarks: r.marine_remarks ?? "",
  };
}

/**
 * Ported from add_risk_assessment_v.php. Every field on that form is
 * `disabled` except the two approval-track sections — add_report()
 * only ever writes shore_is_approved/date_approved/shore_remarks and
 * marine_is_approved/marine_date_approved/marine_remarks, so those are
 * the only fields submitted here. Which section(s) render — and get
 * submitted — depends on which track this report actually requires
 * (approval_from_shore / approval_from_marine).
 */
export function RiskAssessmentApprovalForm({ report, onSuccess, onCancel }: RiskAssessmentApprovalFormProps) {
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RiskAssessmentApprovalFormValues>({
    resolver: zodResolver(riskAssessmentApprovalSchema),
    defaultValues: detailToFormValues(report),
  });

  const onSubmit = async (values: RiskAssessmentApprovalFormValues) => {
    setFormError(null);
    try {
      const calls: Promise<unknown>[] = [];

      if (report.approval_from_shore) {
        calls.push(
          riskAssessmentService.approveShore(report.id, {
            approved: values.shore_approved === "YES",
            date_approved: values.shore_date_approved || null,
            remarks: values.shore_remarks || null,
          }),
        );
      }

      if (report.approval_from_marine) {
        calls.push(
          riskAssessmentService.approveMarine(report.id, {
            approved: values.marine_approved === "YES",
            date_approved: values.marine_date_approved || null,
            remarks: values.marine_remarks || null,
          }),
        );
      }

      await Promise.all(calls);
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof RiskAssessmentApprovalFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      <RiskAssessmentReportSummary r={report} />

      {report.approval_from_shore && (
        <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
          <legend className="px-1 text-sm font-semibold text-slate-700">To Be Filled Out By Technical Superintendent</legend>

          <div>
            <span className="text-sm font-medium text-slate-700">Approved?</span>
            <div className="mt-1 flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="YES" {...register("shore_approved")} /> YES
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="NO" {...register("shore_approved")} /> NO
              </label>
            </div>
          </div>

          <TextField label="Date Approved" type="date" error={errors.shore_date_approved?.message} {...register("shore_date_approved")} />
          <TextareaField label="Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />
        </fieldset>
      )}

      {report.approval_from_marine && (
        <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
          <legend className="px-1 text-sm font-semibold text-slate-700">To Be Filled Out By Marine Superintendent</legend>

          <div>
            <span className="text-sm font-medium text-slate-700">Approved?</span>
            <div className="mt-1 flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="YES" {...register("marine_approved")} /> YES
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="NO" {...register("marine_approved")} /> NO
              </label>
            </div>
          </div>

          <TextField label="Date Approved" type="date" error={errors.marine_date_approved?.message} {...register("marine_date_approved")} />
          <TextareaField label="Remarks" error={errors.marine_remarks?.message} {...register("marine_remarks")} />
        </fieldset>
      )}

      <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <Button type="button" variant="secondary" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" variant="success" isLoading={isSubmitting}>
          Save
        </Button>
      </div>
    </form>
  );
}
