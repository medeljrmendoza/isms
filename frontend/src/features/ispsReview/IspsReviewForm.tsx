import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildIspsReviewSchema, type IspsReviewFormValues } from "./ispsReviewSchema";
import { ispsReviewService } from "./ispsReviewService";
import type { IspsReviewDetail, IspsReviewOption, IspsReviewOptions } from "./ispsReview";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface IspsReviewFormProps {
  review?: IspsReviewDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: IspsReviewOptions = { vessels: [], chapters: [] };

const QUARTER_HINTS: Record<string, string[]> = {
  "1": ["Deck Operations – Vol. III", "Navigation Procedures – Vol. IV", "Cargo Operations as per Ship Type – Vol. V"],
  "2": ["Engine Operations – Vol. VI", "Pollution Control & Preventions – Vol. VII", "Maintenance – Vol. VIII"],
  "3": ["Radio Operations – Vol. IX", "Health & Sanitation – Vol. X", "Emergency Preparedness – Vol. XI"],
  "4": ["Training – Vol. XII", "Safe Shipboard Practices – Vol. XIII", "Crewing – Vol. XIV", "Maritime & Cyber Security – Vol. XV"],
};

function emptyValues(): IspsReviewFormValues {
  return {
    manual_chapter_id: null,
    manual_document_id: null,
    manual_section: "",
    review_date: new Date().toISOString().slice(0, 10),
    review_quarter: "",
    review_year: new Date().getFullYear(),
    review_description: "",
    review_recommendation: "",
    shore_reviewed_by: "",
    shore_remarks: "",
    present: [],
  };
}

function detailToFormValues(r: IspsReviewDetail): IspsReviewFormValues {
  return {
    ...emptyValues(),
    manual_chapter_id: r.manual_chapter_id,
    manual_document_id: r.manual_document_id,
    manual_section: r.manual_section ?? "",
    review_date: r.review_date,
    review_quarter: r.review_quarter,
    review_year: r.review_year,
    review_description: r.review_description ?? "",
    review_recommendation: r.review_recommendation ?? "",
    shore_reviewed_by: r.shore_reviewed_by ?? "",
    shore_remarks: r.shore_remarks ?? "",
    present: r.present.map((p) => ({ name: p.name, position: p.position ?? "" })),
  };
}

/** Ported from admin/isps_review/add_isps_review.php. */
export function IspsReviewForm({ review, onSuccess, onCancel }: IspsReviewFormProps) {
  const isCreate = !review;
  const [options, setOptions] = useState<IspsReviewOptions>(emptyOptions);
  const [documentOptions, setDocumentOptions] = useState<IspsReviewOption[]>([]);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setError,
    control,
    formState: { errors, isSubmitting },
  } = useForm<IspsReviewFormValues>({
    resolver: zodResolver(buildIspsReviewSchema()),
    defaultValues: review ? detailToFormValues(review) : emptyValues(),
  });

  const presentArray = useFieldArray({ control, name: "present" });
  const selectedChapterId = watch("manual_chapter_id");
  const selectedQuarter = watch("review_quarter");

  useEffect(() => {
    ispsReviewService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (options.chapters.length > 0) {
      reset(review ? detailToFormValues(review) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  useEffect(() => {
    if (!selectedChapterId) {
      setDocumentOptions([]);
      return;
    }
    ispsReviewService.documentOptions(selectedChapterId).then(setDocumentOptions).catch(() => undefined);
  }, [selectedChapterId]);

  const onSubmit = async (values: IspsReviewFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await ispsReviewService.create(values);
      } else {
        await ispsReviewService.update(review.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof IspsReviewFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  const hints = QUARTER_HINTS[String(selectedQuarter ?? "")];

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Date" type="date" error={errors.review_date?.message} {...register("review_date")} />
        <div className="grid grid-cols-2 gap-4">
          <SelectField
            label="Quarter"
            placeholder="Select..."
            options={[1, 2, 3, 4].map((n) => ({ value: String(n), label: String(n) }))}
            error={errors.review_quarter?.message}
            {...register("review_quarter")}
          />
          <TextField label="Year" type="number" error={errors.review_year?.message} {...register("review_year")} />
        </div>
      </div>

      {hints && (
        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          <p className="font-semibold">Quarter {selectedQuarter} minimum requirement:</p>
          <ul className="mt-1 list-disc pl-5">
            {hints.map((hint) => (
              <li key={hint}>{hint}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="Manual"
          placeholder="Select manual..."
          options={options.chapters.map((c) => ({ value: String(c.id), label: c.label }))}
          error={errors.manual_chapter_id?.message}
          {...register("manual_chapter_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        <SelectField
          label="Procedure"
          placeholder={selectedChapterId ? "Select procedure (optional)..." : "Select a manual first"}
          options={documentOptions.map((d) => ({ value: String(d.id), label: d.label }))}
          error={errors.manual_document_id?.message}
          {...register("manual_document_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
      </div>

      <TextField label="Section" error={errors.manual_section?.message} {...register("manual_section")} />

      <TextareaField label="Description" error={errors.review_description?.message} {...register("review_description")} />
      <TextareaField label="Recommendation" error={errors.review_recommendation?.message} {...register("review_recommendation")} />

      <TextField label="Reviewed By" error={errors.shore_reviewed_by?.message} {...register("shore_reviewed_by")} />

      {!isCreate && review?.added_by === "VESSEL" && (
        <TextareaField label="Vessel Remarks" value={review?.vessel_remarks ?? ""} disabled readOnly />
      )}
      <TextareaField label="Shore Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Present During Review</legend>
        {presentArray.fields.map((field, index) => (
          <div key={field.id} className="flex items-end gap-2">
            <div className="flex-1">
              <TextField
                label="Name"
                error={errors.present?.[index]?.name?.message}
                {...register(`present.${index}.name`)}
              />
            </div>
            <div className="flex-1">
              <TextField label="Position" {...register(`present.${index}.position`)} />
            </div>
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => presentArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => presentArray.append({ name: "", position: "" })}
        >
          + Add
        </Button>
      </fieldset>

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
