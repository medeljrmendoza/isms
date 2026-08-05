import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { useEffect, useState } from "react";
import { riskAssessmentShoreSchema, type RiskAssessmentShoreFormValues } from "./riskAssessmentShoreSchema";
import { riskAssessmentShoreService } from "./riskAssessmentShoreService";
import type { RiskAssessmentShoreDetail, RiskAssessmentShoreOptions } from "./riskAssessmentShore";
import { computeRisk } from "./riskCalc";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface RiskAssessmentShoreFormProps {
  report: RiskAssessmentShoreDetail | null;
  options: RiskAssessmentShoreOptions;
  onSuccess: () => void;
  onCancel: () => void;
}

function detailToFormValues(r: RiskAssessmentShoreDetail | null): RiskAssessmentShoreFormValues {
  if (!r) {
    return {
      report_type: "SHORE",
      vessel_id: "",
      report_no: "",
      risk_date: "",
      risk_schedule: "",
      port: "",
      department: "",
      activity: "ROUTINE",
      risk_category_shore_id: "",
      other_category_name: "",
      risk_operation_shore_id: "",
      other_operation_name: "",
      overall_risk: "",
      remarks: "",
      date_closed: "",
      approval_from_shore: "NO",
      shore_is_approved: "NO",
      date_approved: "",
      shore_remarks: "",
      approval_from_marine: "NO",
      marine_is_approved: "NO",
      marine_date_approved: "",
      marine_remarks: "",
      hazards: [],
      people: [],
    };
  }

  return {
    report_type: r.report_type,
    vessel_id: r.vessel_id ? String(r.vessel_id) : "",
    report_no: r.report_no,
    risk_date: r.risk_date ?? "",
    risk_schedule: r.risk_schedule ?? "",
    port: r.port ?? "",
    department: r.department ?? "",
    activity: (r.activity as "ROUTINE" | "NON-ROUTINE") ?? "ROUTINE",
    risk_category_shore_id: r.risk_category_shore_id ? String(r.risk_category_shore_id) : "",
    other_category_name: r.other_category_name ?? "",
    risk_operation_shore_id: r.risk_operation_shore_id ? String(r.risk_operation_shore_id) : "",
    other_operation_name: r.other_operation_name ?? "",
    overall_risk: r.overall_risk ?? "",
    remarks: r.remarks ?? "",
    date_closed: r.date_closed ?? "",
    approval_from_shore: r.approval_from_shore ? "YES" : "NO",
    shore_is_approved: r.shore_is_approved ? "YES" : "NO",
    date_approved: r.date_approved ?? "",
    shore_remarks: r.shore_remarks ?? "",
    approval_from_marine: r.approval_from_marine ? "YES" : "NO",
    marine_is_approved: r.marine_is_approved ? "YES" : "NO",
    marine_date_approved: r.marine_date_approved ?? "",
    marine_remarks: r.marine_remarks ?? "",
    hazards: r.hazards.map((h) => ({
      unwanted_consequences: h.unwanted_consequences ?? "",
      underlying_causes: h.underlying_causes ?? "",
      severity: h.severity ?? 1,
      likelihood: h.likelihood ?? 1,
      risk: h.risk ?? "",
      existing_control: h.existing_control ?? "",
      additional_control: h.additional_control ?? "",
      re_severity: h.re_severity ?? h.severity ?? 1,
      re_likelihood: h.re_likelihood ?? 1,
      re_risk: h.re_risk ?? "",
    })),
    people: r.people.map((p) => ({ person_details: p.person_details })),
  };
}

function overallRisk(reRisks: string[]): string {
  if (reRisks.includes("HIGH")) return "HIGH";
  if (reRisks.includes("MID")) return "MID";
  if (reRisks.includes("LOW")) return "LOW";
  return "";
}

/** Ported from admin/riskassessmentshore/add_risk_assessment_v.php. */
export function RiskAssessmentShoreForm({ report, options, onSuccess, onCancel }: RiskAssessmentShoreFormProps) {
  const isCreate = report === null;
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RiskAssessmentShoreFormValues>({
    resolver: zodResolver(riskAssessmentShoreSchema),
    defaultValues: detailToFormValues(report),
  });

  const hazardArray = useFieldArray({ control, name: "hazards" });
  const peopleArray = useFieldArray({ control, name: "people" });

  const reportType = watch("report_type");
  const categoryId = watch("risk_category_shore_id");
  const operationId = watch("risk_operation_shore_id");
  const approvalFromShore = watch("approval_from_shore");
  const approvalFromMarine = watch("approval_from_marine");
  const hazards = watch("hazards");

  useEffect(() => {
    hazards.forEach((h, index) => {
      const risk = computeRisk(Number(h.severity) || 0, Number(h.likelihood) || 0);
      const reRisk = computeRisk(Number(h.severity) || 0, Number(h.re_likelihood) || 0);
      if (h.risk !== risk) setValue(`hazards.${index}.risk`, risk);
      if (h.re_risk !== reRisk) setValue(`hazards.${index}.re_risk`, reRisk);
      if (Number(h.re_severity) !== Number(h.severity)) setValue(`hazards.${index}.re_severity`, h.severity);
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(hazards.map((h) => [h.severity, h.likelihood, h.re_likelihood]))]);

  useEffect(() => {
    setValue("overall_risk", overallRisk(hazards.map((h) => h.re_risk)));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(hazards.map((h) => h.re_risk))]);

  const onSubmit = async (values: RiskAssessmentShoreFormValues) => {
    setFormError(null);
    try {
      const payload = {
        ...values,
        vessel_id: values.vessel_id || null,
        risk_category_shore_id: values.risk_category_shore_id || null,
        risk_operation_shore_id: values.risk_operation_shore_id || null,
        approval_from_shore: values.approval_from_shore === "YES",
        shore_is_approved: values.shore_is_approved === "YES",
        approval_from_marine: values.approval_from_marine === "YES",
        marine_is_approved: values.marine_is_approved === "YES",
      } as unknown as RiskAssessmentShoreFormValues;

      if (isCreate) {
        await riskAssessmentShoreService.create(payload);
      } else {
        // report.id is always numeric here: the edit form only opens for can_edit rows (local-only).
        await riskAssessmentShoreService.update(report.id as number, payload);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof RiskAssessmentShoreFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      <div className="flex gap-4">
        <label className={`flex items-center gap-2 text-sm ${!isCreate ? "pointer-events-none opacity-60" : ""}`}>
          <input type="radio" value="VESSEL" {...register("report_type")} disabled={!isCreate} /> VESSEL
        </label>
        <label className={`flex items-center gap-2 text-sm ${!isCreate ? "pointer-events-none opacity-60" : ""}`}>
          <input type="radio" value="SHORE" {...register("report_type")} disabled={!isCreate} /> SHORE
        </label>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Report No." error={errors.report_no?.message} {...register("report_no")} />
        <div>
          <span className="text-sm font-medium text-slate-700">Activity</span>
          <div className="mt-1 flex gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="ROUTINE" {...register("activity")} /> ROUTINE
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="NON-ROUTINE" {...register("activity")} /> NON-ROUTINE
            </label>
          </div>
        </div>
      </div>

      {reportType === "VESSEL" && (
        <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
          <SelectField
            label="Vessel"
            placeholder="Select vessel..."
            options={options.vessels.map((v) => ({ value: String(v.id), label: v.label }))}
            error={errors.vessel_id?.message}
            disabled={!isCreate}
            {...register("vessel_id")}
          />
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Assessment Date" type="date" error={errors.risk_date?.message} {...register("risk_date")} />
        <TextField label="Schedule of Carrying Out the Task" type="date" error={errors.risk_schedule?.message} {...register("risk_schedule")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label={reportType === "SHORE" ? "Location" : "Port"} error={errors.port?.message} {...register("port")} />
        <TextField label="Department" error={errors.department?.message} {...register("department")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
          <SelectField
            label="Category"
            placeholder="OTHER"
            options={options.categories.map((c) => ({ value: String(c.id), label: c.label }))}
            disabled={!isCreate}
            {...register("risk_category_shore_id")}
          />
        </div>
        {!categoryId && (
          <TextField label="Other Category" error={errors.other_category_name?.message} {...register("other_category_name")} />
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
          <SelectField
            label="Task"
            placeholder="OTHER"
            options={options.operations.map((o) => ({ value: String(o.id), label: o.label }))}
            disabled={!isCreate}
            {...register("risk_operation_shore_id")}
          />
        </div>
        {!operationId && (
          <TextField label="Other Task" error={errors.other_operation_name?.message} {...register("other_operation_name")} />
        )}
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Assessment Table</legend>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200">
                <th className="px-1 py-1">#</th>
                <th className="px-1 py-1">Unwanted Consequences</th>
                <th className="px-1 py-1">Underlying Causes / Hazards</th>
                <th className="px-1 py-1">S</th>
                <th className="px-1 py-1">L</th>
                <th className="px-1 py-1">Risk</th>
                <th className="px-1 py-1">Existing Controls</th>
                <th className="px-1 py-1">Additional Controls</th>
                <th className="px-1 py-1">Final L</th>
                <th className="px-1 py-1">Final Risk</th>
                <th className="px-1 py-1"></th>
              </tr>
            </thead>
            <tbody>
              {hazardArray.fields.map((field, index) => (
                <tr key={field.id} className="border-b border-slate-100 align-top">
                  <td className="px-1 py-1">{index + 1}</td>
                  <td className="px-1 py-1">
                    <textarea
                      rows={2}
                      className="w-40 rounded border border-slate-300 p-1 text-xs"
                      {...register(`hazards.${index}.unwanted_consequences`)}
                    />
                  </td>
                  <td className="px-1 py-1">
                    <textarea
                      rows={2}
                      className="w-40 rounded border border-slate-300 p-1 text-xs"
                      {...register(`hazards.${index}.underlying_causes`)}
                    />
                  </td>
                  <td className="px-1 py-1">
                    <select className="w-14 rounded border border-slate-300 text-xs" {...register(`hazards.${index}.severity`)}>
                      {[1, 2, 3, 4, 5].map((n) => (
                        <option key={n} value={n}>
                          {n}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-1 py-1">
                    <select className="w-14 rounded border border-slate-300 text-xs" {...register(`hazards.${index}.likelihood`)}>
                      {[1, 2, 3, 4, 5].map((n) => (
                        <option key={n} value={n}>
                          {n}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-1 py-1 font-semibold">{hazards[index]?.risk || "—"}</td>
                  <td className="px-1 py-1">
                    <textarea
                      rows={2}
                      className="w-40 rounded border border-slate-300 p-1 text-xs"
                      {...register(`hazards.${index}.existing_control`)}
                    />
                  </td>
                  <td className="px-1 py-1">
                    <textarea
                      rows={2}
                      className="w-40 rounded border border-slate-300 p-1 text-xs"
                      {...register(`hazards.${index}.additional_control`)}
                    />
                  </td>
                  <td className="px-1 py-1">
                    <select className="w-14 rounded border border-slate-300 text-xs" {...register(`hazards.${index}.re_likelihood`)}>
                      {[1, 2, 3, 4, 5].map((n) => (
                        <option key={n} value={n}>
                          {n}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-1 py-1 font-semibold">{hazards[index]?.re_risk || "—"}</td>
                  <td className="px-1 py-1">
                    <Button
                      type="button"
                      variant="secondary"
                      className="!px-1.5 !py-0.5 text-xs text-red-600"
                      onClick={() => hazardArray.remove(index)}
                    >
                      Remove
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {errors.hazards?.message && <p className="text-sm text-red-600">{errors.hazards.message}</p>}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() =>
            hazardArray.append({
              unwanted_consequences: "",
              underlying_causes: "",
              severity: 1,
              likelihood: 1,
              risk: "",
              existing_control: "",
              additional_control: "",
              re_severity: 1,
              re_likelihood: 1,
              re_risk: "",
            })
          }
        >
          + Add Assessment Item
        </Button>
        <p className="text-sm text-slate-600">
          Overall Risk: <span className="font-semibold">{watch("overall_risk") || "—"}</span>
        </p>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Personnel Involved</legend>
        {peopleArray.fields.map((field, index) => (
          <div key={field.id} className="flex items-end gap-2">
            <div className="flex-1">
              <TextField
                label="Name / Position"
                error={errors.people?.[index]?.person_details?.message}
                {...register(`people.${index}.person_details`)}
              />
            </div>
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => peopleArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => peopleArray.append({ person_details: "" })}
        >
          + Add Personnel Involved
        </Button>
      </fieldset>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <span className="text-sm font-medium text-slate-700">Report needs approval from Technical Superintendent?</span>
          <div className="mt-1 flex gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="YES" {...register("approval_from_shore")} /> YES
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="NO" {...register("approval_from_shore")} /> NO
            </label>
          </div>
        </div>
        <div>
          <span className="text-sm font-medium text-slate-700">Report needs approval from Marine Superintendent?</span>
          <div className="mt-1 flex gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="YES" {...register("approval_from_marine")} /> YES
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" value="NO" {...register("approval_from_marine")} /> NO
            </label>
          </div>
        </div>
      </div>

      {approvalFromShore === "YES" && (
        <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
          <legend className="px-1 text-sm font-semibold text-slate-700">To Be Filled Out By Technical Superintendent</legend>
          <div>
            <span className="text-sm font-medium text-slate-700">Approved?</span>
            <div className="mt-1 flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="YES" {...register("shore_is_approved")} /> YES
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="NO" {...register("shore_is_approved")} /> NO
              </label>
            </div>
          </div>
          <TextField label="Date Approved" type="date" error={errors.date_approved?.message} {...register("date_approved")} />
          <TextareaField label="Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />
        </fieldset>
      )}

      {approvalFromMarine === "YES" && (
        <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
          <legend className="px-1 text-sm font-semibold text-slate-700">To Be Filled Out By Marine Superintendent</legend>
          <div>
            <span className="text-sm font-medium text-slate-700">Approved?</span>
            <div className="mt-1 flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="YES" {...register("marine_is_approved")} /> YES
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="radio" value="NO" {...register("marine_is_approved")} /> NO
              </label>
            </div>
          </div>
          <TextField label="Date Approved" type="date" error={errors.marine_date_approved?.message} {...register("marine_date_approved")} />
          <TextareaField label="Remarks" error={errors.marine_remarks?.message} {...register("marine_remarks")} />
        </fieldset>
      )}

      <TextareaField label="Remarks" error={errors.remarks?.message} {...register("remarks")} />
      <TextField label="Date Closed" type="date" error={errors.date_closed?.message} {...register("date_closed")} />

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
