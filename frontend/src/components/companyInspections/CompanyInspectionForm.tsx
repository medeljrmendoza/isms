import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildCompanyInspectionSchema, type CompanyInspectionFormValues } from "../../schemas/companyInspectionSchema";
import { companyInspectionService } from "../../api/companyInspectionService";
import type { CompanyInspectionDetail, CompanyInspectionOptions } from "../../types/companyInspection";
import { isApiValidationError } from "../../types/auth";
import { TextField } from "../ui/TextField";
import { TextareaField } from "../ui/TextareaField";
import { SelectField } from "../ui/SelectField";
import { Button } from "../ui/Button";
import { Alert } from "../ui/Alert";

interface CompanyInspectionFormProps {
  companyInspection?: CompanyInspectionDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: CompanyInspectionOptions = {
  vessels: [],
  audit_types: [],
  audit_kinds: [],
};

function emptyValues(): CompanyInspectionFormValues {
  return {
    audit_ref: "",
    vessel_company: "VESSEL",
    vessel_id: null,
    company: "",
    department: "",
    this_date: new Date().toISOString().slice(0, 10),
    placeof_audit: "",
    audit_type_id: null,
    audit_kind_id: null,
    inspector_name: "",
    master_name: "",
    chief_engineer: "",
    remarks: "",
  };
}

function detailToFormValues(r: CompanyInspectionDetail): CompanyInspectionFormValues {
  return {
    ...emptyValues(),
    audit_ref: r.audit_ref,
    vessel_company: r.vessel_company_raw,
    vessel_id: r.vessel_id,
    company: r.company ?? "",
    department: r.department ?? "",
    this_date: r.this_date,
    placeof_audit: r.placeof_audit ?? "",
    audit_type_id: r.audit_type_id,
    audit_kind_id: r.audit_kind_id,
    inspector_name: r.inspector_name ?? "",
    master_name: r.master_name ?? "",
    chief_engineer: r.chief_engineer ?? "",
    remarks: r.remarks ?? "",
  };
}

/** Ported from admin/companyinspection/add_company_report.php. */
export function CompanyInspectionForm({ companyInspection, onSuccess, onCancel }: CompanyInspectionFormProps) {
  const isCreate = !companyInspection;
  const [options, setOptions] = useState<CompanyInspectionOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CompanyInspectionFormValues>({
    resolver: zodResolver(buildCompanyInspectionSchema(isCreate)),
    defaultValues: companyInspection ? detailToFormValues(companyInspection) : emptyValues(),
  });

  useEffect(() => {
    companyInspectionService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See IncidentReportForm's identical effect: <option> elements built
    // from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(companyInspection ? detailToFormValues(companyInspection) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const vesselCompany = watch("vessel_company");

  useEffect(() => {
    // vessel_id lives inside the VESSEL-vs-COMPANY conditional block, so
    // it isn't in the DOM until vesselCompany itself has updated (from
    // the reset above) and that re-render has committed — one tick later
    // than the always-visible fields. Re-apply once that's settled.
    if (options.vessels.length > 0 && companyInspection) {
      setValue("vessel_id", companyInspection.vessel_id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vesselCompany, options]);

  const onSubmit = async (values: CompanyInspectionFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await companyInspectionService.create(values);
      } else {
        await companyInspectionService.update(companyInspection.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof CompanyInspectionFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Ref. No." error={errors.audit_ref?.message} {...register("audit_ref")} />
        <TextField label="Department" error={errors.department?.message} {...register("department")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {/* Attribution is frozen after creation (backend ignores
            vessel_company/vessel_id on update) — styled read-only via
            pointer-events rather than the native `disabled` attribute,
            since disabled radios/selects don't reliably pick up their
            react-hook-form default value on mount. The company *name*
            stays editable though, matching legacy's edit branch. */}
        <div className="flex flex-col gap-2">
          <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
            <span className="text-sm font-medium text-slate-700">Vessel / Company</span>
            <div className="mt-1 flex gap-4 text-sm text-slate-700">
              <label className="flex items-center gap-1.5">
                <input type="radio" value="VESSEL" tabIndex={!isCreate ? -1 : undefined} {...register("vessel_company")} /> Vessel
              </label>
              <label className="flex items-center gap-1.5">
                <input type="radio" value="COMPANY" tabIndex={!isCreate ? -1 : undefined} {...register("vessel_company")} /> Company
              </label>
            </div>
          </div>
          {errors.vessel_company && <p className="text-sm text-red-600">{errors.vessel_company.message}</p>}

          {vesselCompany === "VESSEL" ? (
            <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
              <SelectField
                label="Vessel"
                placeholder="Select vessel..."
                options={options.vessels.map((v) => ({ value: String(v.id), label: v.label }))}
                error={errors.vessel_id?.message}
                tabIndex={!isCreate ? -1 : undefined}
                {...register("vessel_id", { setValueAs: (v) => (v ? Number(v) : null) })}
              />
            </div>
          ) : (
            <TextField label="Company" error={errors.company?.message} {...register("company")} />
          )}
        </div>

        <div className="flex flex-col gap-4">
          <TextField label="Date of Inspection" type="date" error={errors.this_date?.message} {...register("this_date")} />
          <TextField label="Port of Inspection" error={errors.placeof_audit?.message} {...register("placeof_audit")} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="Type of Inspection"
          placeholder="Select type..."
          options={options.audit_types.map((t) => ({ value: String(t.id), label: t.label }))}
          error={errors.audit_type_id?.message}
          {...register("audit_type_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        <SelectField
          label="Kind of Inspection"
          placeholder="Select kind..."
          options={options.audit_kinds.map((k) => ({ value: String(k.id), label: k.label }))}
          error={errors.audit_kind_id?.message}
          {...register("audit_kind_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
      </div>

      {/* Legacy picks this from the Address Book "office personnel"
          category; that module isn't migrated, so it's free text here. */}
      <TextField label="Inspector" error={errors.inspector_name?.message} {...register("inspector_name")} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Master" error={errors.master_name?.message} {...register("master_name")} />
        <TextField label="Chief Engineer" error={errors.chief_engineer?.message} {...register("chief_engineer")} />
      </div>

      <TextareaField label="Remarks" {...register("remarks")} />

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
