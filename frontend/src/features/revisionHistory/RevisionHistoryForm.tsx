import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildRevisionHistorySchema, type RevisionHistoryFormValues } from "./revisionHistorySchema";
import { revisionHistoryService } from "./revisionHistoryService";
import type { RevisionHistoryDetail, RevisionHistoryOption, RevisionHistoryOptions } from "./revisionHistory";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface RevisionHistoryFormProps {
  revision?: RevisionHistoryDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: RevisionHistoryOptions = { chapters: [] };

function emptyValues(): RevisionHistoryFormValues {
  return {
    manual_chapter_id: null,
    manual_document_id: null,
    arrangement: "" as unknown as number,
    revision_no: "",
    date_revised: new Date().toISOString().slice(0, 10),
    section: "",
    reason_revision: "",
    reviewed_by: "",
    approved_by: "",
  };
}

function detailToFormValues(r: RevisionHistoryDetail): RevisionHistoryFormValues {
  return {
    ...emptyValues(),
    manual_chapter_id: r.manual_chapter_id,
    manual_document_id: r.manual_document_id,
    arrangement: r.arrangement,
    revision_no: r.revision_no,
    date_revised: r.date_revised,
    section: r.section ?? "",
    reason_revision: r.reason_revision ?? "",
    reviewed_by: r.reviewed_by,
    approved_by: r.approved_by,
  };
}

/**
 * Ported from admin/revision_history/add_sms_revision.php. The Manual
 * (chapter) and Procedure selects are only editable when creating —
 * legacy freezes both to a disabled text field once a revision exists
 * (see RevisionHistoryRepository::update()).
 */
export function RevisionHistoryForm({ revision, onSuccess, onCancel }: RevisionHistoryFormProps) {
  const isCreate = !revision;
  const [options, setOptions] = useState<RevisionHistoryOptions>(emptyOptions);
  const [documentOptions, setDocumentOptions] = useState<RevisionHistoryOption[]>([]);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RevisionHistoryFormValues>({
    resolver: zodResolver(buildRevisionHistorySchema()),
    defaultValues: revision ? detailToFormValues(revision) : emptyValues(),
  });

  const selectedChapterId = watch("manual_chapter_id");

  useEffect(() => {
    revisionHistoryService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (options.chapters.length > 0) {
      reset(revision ? detailToFormValues(revision) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  useEffect(() => {
    if (!selectedChapterId) {
      setDocumentOptions([]);
      return;
    }
    revisionHistoryService.documentOptions(Number(selectedChapterId)).then(setDocumentOptions).catch(() => undefined);
  }, [selectedChapterId]);

  const onSubmit = async (values: RevisionHistoryFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await revisionHistoryService.create(values);
      } else {
        await revisionHistoryService.update(revision.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof RevisionHistoryFormValues, { message: messages[0] });
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
        <SelectField
          label="Manual"
          placeholder="Select manual..."
          options={options.chapters.map((c) => ({ value: String(c.id), label: c.label }))}
          error={errors.manual_chapter_id?.message}
          disabled={!isCreate}
          {...register("manual_chapter_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        <SelectField
          label="Procedure"
          placeholder={selectedChapterId ? "Select procedure..." : "Select a manual first"}
          options={documentOptions.map((d) => ({ value: String(d.id), label: d.label }))}
          error={errors.manual_document_id?.message}
          disabled={!isCreate}
          {...register("manual_document_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Order" type="number" error={errors.arrangement?.message} {...register("arrangement")} />
        <TextField label="Revision No." error={errors.revision_no?.message} {...register("revision_no")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Section" error={errors.section?.message} {...register("section")} />
        <TextField label="Date of Revision" type="date" error={errors.date_revised?.message} {...register("date_revised")} />
      </div>

      <TextareaField label="Reason for Revision" error={errors.reason_revision?.message} {...register("reason_revision")} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Reviewed By" error={errors.reviewed_by?.message} {...register("reviewed_by")} />
        <TextField label="Approved By" error={errors.approved_by?.message} {...register("approved_by")} />
      </div>

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
