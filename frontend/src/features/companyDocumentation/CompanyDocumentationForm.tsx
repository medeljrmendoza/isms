import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildCompanyDocumentationSchema, type CompanyDocumentationFormValues } from "./companyDocumentationSchema";
import { companyDocumentationService } from "./companyDocumentationService";
import type { CompanyDocumentationDetail, CompanyDocumentationOption } from "./companyDocumentation";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface CompanyDocumentationFormProps {
  record?: CompanyDocumentationDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

function emptyValues(): CompanyDocumentationFormValues {
  return {
    company_document_id: null,
    doc_number: "",
    issuing_body: "",
    date_issued: new Date().toISOString().slice(0, 10),
    date_expired: "",
    date_range_from: "",
    date_range_to: "",
    is_printer_friendly: false,
    remarks: "",
  };
}

function detailToFormValues(r: CompanyDocumentationDetail): CompanyDocumentationFormValues {
  return {
    ...emptyValues(),
    company_document_id: r.company_document_id,
    doc_number: r.doc_number ?? "",
    issuing_body: r.issuing_body ?? "",
    date_issued: r.date_issued ?? "",
    date_expired: r.date_expired ?? "",
    date_range_from: r.date_range_from ?? "",
    date_range_to: r.date_range_to ?? "",
    is_printer_friendly: r.is_printer_friendly,
    remarks: r.remarks ?? "",
  };
}

/** Ported from admin/company_documentation/add_company_documentation_v.php. */
export function CompanyDocumentationForm({ record, onSuccess, onCancel }: CompanyDocumentationFormProps) {
  const isCreate = !record;
  const [documentOptions, setDocumentOptions] = useState<CompanyDocumentationOption[]>([]);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CompanyDocumentationFormValues>({
    resolver: zodResolver(buildCompanyDocumentationSchema(isCreate)),
    defaultValues: record ? detailToFormValues(record) : emptyValues(),
  });

  useEffect(() => {
    if (isCreate) {
      companyDocumentationService.documentOptions().then(setDocumentOptions).catch(() => undefined);
    }
  }, [isCreate]);

  useEffect(() => {
    if (documentOptions.length > 0) {
      reset(record ? detailToFormValues(record) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [documentOptions]);

  const onSubmit = async (values: CompanyDocumentationFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await companyDocumentationService.create(values);
      } else {
        await companyDocumentationService.update(record.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof CompanyDocumentationFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {/* Frozen after creation, same convention as VesselDocumentationForm's document field. */}
      {isCreate ? (
        <SelectField
          label="Document"
          placeholder="Select document..."
          options={documentOptions.map((d) => ({ value: String(d.id), label: d.label }))}
          error={errors.company_document_id?.message}
          {...register("company_document_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
      ) : (
        <TextField label="Document" value={`${record.document_type} — ${record.document}`} disabled readOnly tabIndex={-1} />
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Document No." error={errors.doc_number?.message} {...register("doc_number")} />
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

      <TextareaField label="Remarks" error={errors.remarks?.message} {...register("remarks")} />

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
