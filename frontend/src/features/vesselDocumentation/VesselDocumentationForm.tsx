import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildVesselDocumentationSchema, type VesselDocumentationFormValues } from "./vesselDocumentationSchema";
import { vesselDocumentationService } from "./vesselDocumentationService";
import type { VesselDocumentationDetail, VesselDocumentationOption, VesselDocumentationOptions } from "./vesselDocumentation";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface VesselDocumentationFormProps {
  record?: VesselDocumentationDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: VesselDocumentationOptions = { vessels: [], can_create_record: true };

function emptyValues(): VesselDocumentationFormValues {
  return {
    vessel_id: null,
    vessel_document_id: null,
    doc_number: "",
    issuing_body: "",
    date_issued: new Date().toISOString().slice(0, 10),
    date_expired: "",
    date_range_from: "",
    date_range_to: "",
    is_printer_friendly: false,
    shore_remarks: "",
    vessel_remarks: "",
  };
}

function detailToFormValues(r: VesselDocumentationDetail): VesselDocumentationFormValues {
  return {
    ...emptyValues(),
    // vessel_id/vessel_document_id are always numeric here: this form only opens for can_edit records (local-only).
    vessel_id: r.vessel_id as number,
    vessel_document_id: r.vessel_document_id as number,
    doc_number: r.doc_number ?? "",
    issuing_body: r.issuing_body ?? "",
    date_issued: r.date_issued ?? "",
    date_expired: r.date_expired ?? "",
    date_range_from: r.date_range_from ?? "",
    date_range_to: r.date_range_to ?? "",
    is_printer_friendly: r.is_printer_friendly,
    shore_remarks: r.shore_remarks ?? "",
    vessel_remarks: r.vessel_remarks ?? "",
  };
}

/** Ported from admin/vessel_documentation/add_vessel_documentation_v.php. */
export function VesselDocumentationForm({ record, onSuccess, onCancel }: VesselDocumentationFormProps) {
  const isCreate = !record;
  const [options, setOptions] = useState<VesselDocumentationOptions>(emptyOptions);
  const [documentOptions, setDocumentOptions] = useState<VesselDocumentationOption[]>([]);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<VesselDocumentationFormValues>({
    resolver: zodResolver(buildVesselDocumentationSchema(isCreate)),
    defaultValues: record ? detailToFormValues(record) : emptyValues(),
  });

  const selectedVesselId = watch("vessel_id");

  useEffect(() => {
    vesselDocumentationService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (options.vessels.length > 0) {
      reset(record ? detailToFormValues(record) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  // The Document dropdown only makes sense once a Vessel is picked — it
  // lists catalog documents that vessel doesn't already have a record
  // for (see VesselDocumentationRepository::catalogOptionsForVessel).
  useEffect(() => {
    if (!isCreate || !selectedVesselId) {
      setDocumentOptions([]);
      return;
    }
    vesselDocumentationService.documentOptions(selectedVesselId).then(setDocumentOptions).catch(() => undefined);
  }, [isCreate, selectedVesselId]);

  const onSubmit = async (values: VesselDocumentationFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await vesselDocumentationService.create(values);
      } else {
        // record.id is always numeric here: this form only opens for can_edit records (local-only).
        await vesselDocumentationService.update(record.id as number, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof VesselDocumentationFormValues, { message: messages[0] });
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
        {/* Frozen after creation, same convention as FlagStateReportForm's vessel field. */}
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

        <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
          {isCreate ? (
            <SelectField
              label="Document"
              placeholder={selectedVesselId ? "Select document..." : "Select a vessel first"}
              options={documentOptions.map((d) => ({ value: String(d.id), label: d.label }))}
              error={errors.vessel_document_id?.message}
              {...register("vessel_document_id", { setValueAs: (v) => (v ? Number(v) : null) })}
            />
          ) : (
            <TextField label="Document" value={`${record.document_type} — ${record.document}`} disabled readOnly tabIndex={-1} />
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Certificate No." error={errors.doc_number?.message} {...register("doc_number")} />
        <TextField label="Issuing Body" error={errors.issuing_body?.message} {...register("issuing_body")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Date Issued" type="date" error={errors.date_issued?.message} {...register("date_issued")} />
        <TextField label="Date Expired" type="date" error={errors.date_expired?.message} {...register("date_expired")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Date Range From" type="date" error={errors.date_range_from?.message} {...register("date_range_from")} />
        <TextField label="Date Range To" type="date" error={errors.date_range_to?.message} {...register("date_range_to")} />
      </div>

      <label className="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" {...register("is_printer_friendly")} />
        Printer-Friendly
      </label>

      <TextareaField label="Shore Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />
      <TextareaField label="Vessel Remarks" error={errors.vessel_remarks?.message} {...register("vessel_remarks")} />

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
