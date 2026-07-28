import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildCommitteeMeetingSchema, type CommitteeMeetingFormValues } from "./committeeMeetingSchema";
import { committeeMeetingService } from "./committeeMeetingService";
import type { CommitteeMeetingDetail, CommitteeMeetingOptions } from "./committeeMeeting";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface CommitteeMeetingFormProps {
  committeeMeeting?: CommitteeMeetingDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: CommitteeMeetingOptions = {
  vessels: [],
  meeting_types: [],
};

function emptyValues(): CommitteeMeetingFormValues {
  return {
    shore_vessel_meeting: "VESSEL",
    vessel_id: null,
    meeting_date: new Date().toISOString().slice(0, 10),
    meeting_position: "",
    meeting_time: "",
    chairman: "",
    incharge: "",
    shore_remarks: "",
    meeting_types: [],
    attendees: [],
    members: [],
    topics: [],
  };
}

function detailToFormValues(m: CommitteeMeetingDetail): CommitteeMeetingFormValues {
  return {
    ...emptyValues(),
    shore_vessel_meeting: m.shore_vessel_meeting,
    vessel_id: m.vessel_id,
    meeting_date: m.meeting_date,
    meeting_position: m.meeting_position ?? "",
    meeting_time: m.meeting_time ?? "",
    chairman: m.chairman ?? "",
    incharge: m.incharge ?? "",
    shore_remarks: m.shore_remarks ?? "",
    meeting_types: m.meeting_types.map((t) => ({
      committee_meeting_type_id: t.committee_meeting_type_id,
      name: t.name,
      type_other: t.type_other ?? "",
    })),
    attendees: m.attendees.map((a) => ({ name: a.name })),
    members: m.members.map((mem) => ({ name: mem.name })),
    topics: m.topics.map((t) => ({
      topic_name: t.topic_name,
      meeting_details: t.meeting_details ?? "",
      meeting_comments: t.meeting_comments ?? "",
    })),
  };
}

/** Ported from admin/commiteemeeting/add_committee_meeting.php. */
export function CommitteeMeetingForm({ committeeMeeting, onSuccess, onCancel }: CommitteeMeetingFormProps) {
  const isCreate = !committeeMeeting;
  const [options, setOptions] = useState<CommitteeMeetingOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    watch,
    control,
    formState: { errors, isSubmitting },
  } = useForm<CommitteeMeetingFormValues>({
    resolver: zodResolver(buildCommitteeMeetingSchema(isCreate)),
    defaultValues: committeeMeeting ? detailToFormValues(committeeMeeting) : emptyValues(),
  });

  useEffect(() => {
    committeeMeetingService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See ExternalAuditForm's identical effect: <option> elements built
    // from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(committeeMeeting ? detailToFormValues(committeeMeeting) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const typeArray = useFieldArray({ control, name: "meeting_types" });
  const attendeeArray = useFieldArray({ control, name: "attendees" });
  const memberArray = useFieldArray({ control, name: "members" });
  const topicArray = useFieldArray({ control, name: "topics" });

  const shoreVesselMeeting = watch("shore_vessel_meeting");
  const isVesselMeeting = shoreVesselMeeting === "VESSEL";
  const selectedTypeIds = new Set(typeArray.fields.map((f) => f.committee_meeting_type_id));

  const toggleType = (typeId: number, name: string) => {
    const index = typeArray.fields.findIndex((f) => f.committee_meeting_type_id === typeId);
    if (index >= 0) {
      typeArray.remove(index);
    } else {
      typeArray.append({ committee_meeting_type_id: typeId, name, type_other: "" });
    }
  };

  const onSubmit = async (values: CommitteeMeetingFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await committeeMeetingService.create(values);
      } else {
        await committeeMeetingService.update(committeeMeeting.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof CommitteeMeetingFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {!isCreate && committeeMeeting?.added_by === "VESSEL" && (
        <Alert variant="info">This meeting was added by the vessel. Vessel remarks are read-only here.</Alert>
      )}

      <div className="flex flex-col gap-2">
        <label className="text-sm font-medium text-slate-700">Meeting Scope</label>
        <div className="flex gap-4">
          <label className={`flex items-center gap-2 text-sm ${!isCreate ? "pointer-events-none opacity-60" : ""}`}>
            <input type="radio" value="SHORE" {...register("shore_vessel_meeting")} disabled={!isCreate} />
            SHORE MEETING
          </label>
          <label className={`flex items-center gap-2 text-sm ${!isCreate ? "pointer-events-none opacity-60" : ""}`}>
            <input type="radio" value="VESSEL" {...register("shore_vessel_meeting")} disabled={!isCreate} />
            VESSEL MEETING
          </label>
        </div>
      </div>

      {isVesselMeeting && (
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
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Position" error={errors.meeting_position?.message} {...register("meeting_position")} />
        <TextField label="Date" type="date" error={errors.meeting_date?.message} {...register("meeting_date")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Time" placeholder="e.g. 10:00 AM" error={errors.meeting_time?.message} {...register("meeting_time")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Type of Meeting</legend>
        {errors.meeting_types?.message && <p className="text-sm text-red-600">{errors.meeting_types.message}</p>}
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          {options.meeting_types.map((type) => (
            <label key={type.id} className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={selectedTypeIds.has(type.id)} onChange={() => toggleType(type.id, type.label)} />
              {type.label}
            </label>
          ))}
        </div>
        {typeArray.fields.map((field, index) =>
          field.name === "OTHERS" ? (
            <TextField
              key={field.id}
              label="Others — please specify"
              error={errors.meeting_types?.[index]?.type_other?.message}
              {...register(`meeting_types.${index}.type_other`)}
            />
          ) : null,
        )}
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Matters Discussed</legend>
        {topicArray.fields.map((field, index) => (
          <div key={field.id} className="flex flex-col gap-2 border-b border-slate-100 pb-3 last:border-0">
            <div className="flex items-end gap-2">
              <div className="flex-1">
                <TextField
                  label="Topic"
                  error={errors.topics?.[index]?.topic_name?.message}
                  {...register(`topics.${index}.topic_name`)}
                />
              </div>
              <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => topicArray.remove(index)}>
                Remove
              </Button>
            </div>
            <TextareaField label="Details" rows={2} {...register(`topics.${index}.meeting_details`)} />
            <TextareaField label="Shore Comments" rows={2} {...register(`topics.${index}.meeting_comments`)} />
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => topicArray.append({ topic_name: "", meeting_details: "", meeting_comments: "" })}
        >
          + Add Topic
        </Button>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Members</legend>
        {memberArray.fields.map((field, index) => (
          <div key={field.id} className="flex items-end gap-2">
            <div className="flex-1">
              <TextField
                label="Name"
                error={errors.members?.[index]?.name?.message}
                {...register(`members.${index}.name`)}
              />
            </div>
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => memberArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button type="button" variant="secondary" className="self-start !px-3 !py-1.5 text-sm" onClick={() => memberArray.append({ name: "" })}>
          + Add Member
        </Button>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">In Attendance</legend>
        {attendeeArray.fields.map((field, index) => (
          <div key={field.id} className="flex items-end gap-2">
            <div className="flex-1">
              <TextField
                label="Name"
                error={errors.attendees?.[index]?.name?.message}
                {...register(`attendees.${index}.name`)}
              />
            </div>
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => attendeeArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => attendeeArray.append({ name: "" })}
        >
          + Add Attendee
        </Button>
      </fieldset>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Chairman" error={errors.chairman?.message} {...register("chairman")} />
        <TextField label="In-charge" error={errors.incharge?.message} {...register("incharge")} />
      </div>

      {!isCreate && (
        <TextareaField
          label="Vessel Remarks"
          value={committeeMeeting?.vessel_remarks ?? ""}
          disabled
          readOnly
        />
      )}
      <TextareaField label="Shore Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />

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
