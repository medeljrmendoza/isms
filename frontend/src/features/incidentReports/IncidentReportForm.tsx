import { useEffect, useState } from "react";
import { Controller, useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildIncidentReportSchema, type IncidentReportFormValues } from "./incidentReportSchema";
import { incidentReportService } from "./incidentReportService";
import type { IncidentReportDetail, IncidentReportOptions } from "./incidentReport";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { CheckboxField } from "../../components/ui/CheckboxField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface IncidentReportFormProps {
  incidentReport?: IncidentReportDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: IncidentReportOptions = {
  vessels: [],
  years: [],
  nature_of_incidents: [],
  incident_locations: [],
  incident_operations: [],
  types_of_injury: [],
  locations_of_injury: [],
  root_cause_categories: [],
};

function emptyValues(): IncidentReportFormValues {
  return {
    vessel_id: null,
    voyage_no: "",
    dateof_report: new Date().toISOString().slice(0, 10),
    report_no: "",
    master_name: "",
    chief_engineer_name: "",
    person_reporting: "",
    nature_type: "accident",
    statementof_work: "",
    nature_of_incident_id: null,
    accident_collision: "",
    others: "",
    bac: "NO",
    vdr: "NO",
    dateof_event: "",
    timeof_event: "",
    zone: "",
    country: "",
    speed: "",
    course: "",
    draft_forward: "",
    draft_alt: "",
    wind_direction: "",
    direction_sea: "",
    direction_swell: "",
    geographical_location: "",
    port_departure: "",
    date_departure: "",
    port_which_bound: "",
    type_cargo: "",
    cargo_quantity: "",
    special_requirement: "",
    atmospheric_clear: false,
    atmospheric_partly_cloudy: false,
    atmospheric_overcast: false,
    atmospheric_fog: false,
    atmospheric_rain: false,
    atmospheric_snow: false,
    atmospheric_other: false,
    atmospheric_other_name: "",
    distance1: false,
    distance2: false,
    distance3: false,
    sea1: false,
    sea2: false,
    sea3: false,
    crew_onboard: null,
    other_onboard: null,
    total_onboard: null,
    crew_dead: null,
    other_dead: null,
    total_dead: null,
    crew_missing: null,
    other_missing: null,
    total_missing: null,
    crew_injured: null,
    other_injured: null,
    total_injured: null,
    fs_ro: "NO",
    hazardous_occurrence_type: null,
    incident_location_id: null,
    location_other: "",
    ship_position: "",
    incident_operation_id: null,
    ship_operation_other: "",
    hazardous_occurrence_ppe_used: "NO",
    hazardous_occurrence_ppe_used_comment: "",
    hazardous_occurrence_severity: null,
    hazardous_occurrence_severity_comment: "",
    hazardous_occurrence_likelihood: null,
    hazardous_occurrence_likelihood_comment: "",
    subject_investigation: "NO",
    evidence_safety_meeting: false,
    evidence_certificate: false,
    evidence_logbook: false,
    evidence_delivery: false,
    evidence_photo: false,
    evidence_company: false,
    evidence_others: false,
    evidence_others_name: "",
    causal_factor: "",
    intermediate_cause: "",
    shore_root_cause_summary: "",
    severity_itp: null,
    comment_itp: "",
    location_of_injury_id: null,
    type_of_injury_id: null,
    other_typeof_injury: "",
    other_affected_area: "",
    severity_itv: null,
    comment_itv: "",
    root_causes: [],
    persons: [],
    signed_by: "",
    date_signed: new Date().toISOString().slice(0, 10),
    vessel_remarks: "",
    date_received: new Date().toISOString().slice(0, 10),
    reviewed_by: "",
    investigator: "",
    dpa: "",
    closing_date: "",
  };
}

function detailToFormValues(r: IncidentReportDetail): IncidentReportFormValues {
  return {
    ...emptyValues(),
    vessel_id: r.vessel_id,
    voyage_no: r.voyage_no ?? "",
    dateof_report: r.dateof_report,
    report_no: r.report_no ?? "",
    master_name: r.master_name ?? "",
    chief_engineer_name: r.chief_engineer_name ?? "",
    person_reporting: r.person_reporting ?? "",
    nature_type: r.nature_type,
    statementof_work: r.statementof_work ?? "",
    nature_of_incident_id: r.nature_of_incident_id,
    accident_collision: r.accident_collision ?? "",
    others: r.others ?? "",
    bac: r.bac ?? "NO",
    vdr: r.vdr ?? "NO",
    dateof_event: r.dateof_event ?? "",
    timeof_event: r.timeof_event ?? "",
    zone: r.zone ?? "",
    country: r.country ?? "",
    speed: r.speed ?? "",
    course: r.course ?? "",
    draft_forward: r.draft_forward ?? "",
    draft_alt: r.draft_alt ?? "",
    wind_direction: r.wind_direction ?? "",
    direction_sea: r.direction_sea ?? "",
    direction_swell: r.direction_swell ?? "",
    geographical_location: r.geographical_location ?? "",
    port_departure: r.port_departure ?? "",
    date_departure: r.date_departure ?? "",
    port_which_bound: r.port_which_bound ?? "",
    type_cargo: r.type_cargo ?? "",
    cargo_quantity: r.cargo_quantity ?? "",
    special_requirement: r.special_requirement ?? "",
    atmospheric_clear: r.atmospheric_clear,
    atmospheric_partly_cloudy: r.atmospheric_partly_cloudy,
    atmospheric_overcast: r.atmospheric_overcast,
    atmospheric_fog: r.atmospheric_fog,
    atmospheric_rain: r.atmospheric_rain,
    atmospheric_snow: r.atmospheric_snow,
    atmospheric_other: r.atmospheric_other,
    atmospheric_other_name: r.atmospheric_other_name ?? "",
    distance1: r.distance1,
    distance2: r.distance2,
    distance3: r.distance3,
    sea1: r.sea1,
    sea2: r.sea2,
    sea3: r.sea3,
    crew_onboard: r.crew_onboard,
    other_onboard: r.other_onboard,
    total_onboard: r.total_onboard,
    crew_dead: r.crew_dead,
    other_dead: r.other_dead,
    total_dead: r.total_dead,
    crew_missing: r.crew_missing,
    other_missing: r.other_missing,
    total_missing: r.total_missing,
    crew_injured: r.crew_injured,
    other_injured: r.other_injured,
    total_injured: r.total_injured,
    fs_ro: r.fs_ro ?? "NO",
    hazardous_occurrence_type: r.hazardous_occurrence_type,
    incident_location_id: r.incident_location_id,
    location_other: r.location_other ?? "",
    ship_position: r.ship_position ?? "",
    incident_operation_id: r.incident_operation_id,
    ship_operation_other: r.ship_operation_other ?? "",
    hazardous_occurrence_ppe_used: r.hazardous_occurrence_ppe_used ?? "NO",
    hazardous_occurrence_ppe_used_comment: r.hazardous_occurrence_ppe_used_comment ?? "",
    hazardous_occurrence_severity: r.hazardous_occurrence_severity,
    hazardous_occurrence_severity_comment: r.hazardous_occurrence_severity_comment ?? "",
    hazardous_occurrence_likelihood: r.hazardous_occurrence_likelihood,
    hazardous_occurrence_likelihood_comment: r.hazardous_occurrence_likelihood_comment ?? "",
    subject_investigation: r.subject_investigation ?? "NO",
    evidence_safety_meeting: r.evidence_safety_meeting,
    evidence_certificate: r.evidence_certificate,
    evidence_logbook: r.evidence_logbook,
    evidence_delivery: r.evidence_delivery,
    evidence_photo: r.evidence_photo,
    evidence_company: r.evidence_company,
    evidence_others: r.evidence_others,
    evidence_others_name: r.evidence_others_name ?? "",
    causal_factor: r.causal_factor ?? "",
    intermediate_cause: r.intermediate_cause ?? "",
    shore_root_cause_summary: r.shore_root_cause_summary ?? "",
    severity_itp: r.severity_itp,
    comment_itp: r.comment_itp ?? "",
    location_of_injury_id: r.location_of_injury_id,
    type_of_injury_id: r.type_of_injury_id,
    other_typeof_injury: r.other_typeof_injury ?? "",
    other_affected_area: r.other_affected_area ?? "",
    severity_itv: r.severity_itv,
    comment_itv: r.comment_itv ?? "",
    root_causes: r.root_causes.map((rc) => ({
      root_cause_id: rc.root_cause_id,
      root_cause_other: rc.root_cause_other ?? "",
      investigation: rc.investigation ?? "",
      analysis: rc.analysis ?? "",
      corrective_actions: rc.corrective_actions ?? "",
    })),
    persons: r.persons.map((p) => ({ person_name: p.person_name, position: p.position ?? "" })),
    signed_by: r.signed_by ?? "",
    date_signed: r.date_signed ?? "",
    vessel_remarks: r.vessel_remarks ?? "",
    date_received: r.date_received ?? "",
    reviewed_by: r.reviewed_by ?? "",
    investigator: r.investigator ?? "",
    dpa: r.dpa ?? "",
    closing_date: r.closing_date ?? "",
  };
}

/** Ported from admin/incident/add_incident_report_v.php. */
export function IncidentReportForm({ incidentReport, onSuccess, onCancel }: IncidentReportFormProps) {
  const isCreate = !incidentReport;
  const [options, setOptions] = useState<IncidentReportOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    control,
    handleSubmit,
    watch,
    reset,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<IncidentReportFormValues>({
    resolver: zodResolver(buildIncidentReportSchema(isCreate)),
    defaultValues: incidentReport ? detailToFormValues(incidentReport) : emptyValues(),
  });

  const rootCauseArray = useFieldArray({ control, name: "root_causes" });
  const personArray = useFieldArray({ control, name: "persons" });

  useEffect(() => {
    incidentReportService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // Selects/radios built from the option lists (vessel, nature of
    // incident, etc.) don't exist in the DOM until the fetch above
    // resolves, so react-hook-form's mount-time default-value assignment
    // has nothing to attach to. Re-sync once options have actually
    // rendered — this effect (unlike doing it inside the fetch's .then)
    // runs after that render commits, so the elements are really there.
    if (options.vessels.length > 0) {
      reset(incidentReport ? detailToFormValues(incidentReport) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const natureType = watch("nature_type");
  const natureOfIncidentId = watch("nature_of_incident_id");
  const incidentLocationId = watch("incident_location_id");
  const incidentOperationId = watch("incident_operation_id");
  const typeOfInjuryId = watch("type_of_injury_id");
  const locationOfInjuryId = watch("location_of_injury_id");
  const severityItp = watch("severity_itp");
  const atmosphericOther = watch("atmospheric_other");
  const evidenceOthers = watch("evidence_others");

  useEffect(() => {
    // incident_location_id / incident_operation_id live inside the
    // accident-vs-hazardous conditional block, so they don't exist in the
    // DOM until natureType itself has already updated (from the reset()
    // above) and the resulting re-render has committed — one render tick
    // later than the always-visible fields. Re-apply once that's settled.
    // (nature_of_incident_id is handled separately via Controller, below —
    // plain register()/setValue() on a dynamically-rendered radio group
    // proved unreliable here.)
    if (options.vessels.length > 0 && incidentReport) {
      setValue("incident_location_id", incidentReport.incident_location_id);
      setValue("incident_operation_id", incidentReport.incident_operation_id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [natureType, options]);

  const isAccident = natureType === "accident";
  const isHazardous = natureType === "hazardous_occurrence";
  const selectedNature = options.nature_of_incidents.find((n) => n.id === natureOfIncidentId);
  const isOtherNature = selectedNature?.label === "Other";
  const isCollisionNature = selectedNature?.label === "Collision";
  const isOtherLocation = options.incident_locations.find((l) => l.id === incidentLocationId)?.label === "OTHER";
  const isOtherOperation = options.incident_operations.find((o) => o.id === incidentOperationId)?.label === "OTHER";
  const isOtherTypeOfInjury = options.types_of_injury.find((t) => t.id === typeOfInjuryId)?.label === "Other";
  const isOtherLocationOfInjury = options.locations_of_injury.find((l) => l.id === locationOfInjuryId)?.label === "Other";

  const onSubmit = async (values: IncidentReportFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await incidentReportService.create(values);
      } else {
        // Editing is only ever reachable for local records (can_edit is
        // always false for legacy-sourced rows, so the Edit button that
        // leads here never renders for a legacy string id).
        await incidentReportService.update(incidentReport.id as number, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof IncidentReportFormValues, { message: messages[0] });
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
        {/* Frozen after creation (backend ignores vessel_id on update) — styled
            read-only via pointer-events rather than the native `disabled`
            attribute, since a disabled <select> doesn't reliably pick up its
            react-hook-form default value on mount. */}
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
        <TextField label="Voyage No." error={errors.voyage_no?.message} {...register("voyage_no")} />
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <TextField label="Date of Report" type="date" error={errors.dateof_report?.message} {...register("dateof_report")} />
        <TextField label="Report No." error={errors.report_no?.message} {...register("report_no")} />
        <TextField label="Person Reporting" error={errors.person_reporting?.message} {...register("person_reporting")} />
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Master" error={errors.master_name?.message} {...register("master_name")} />
        <TextField label="Chief Engineer" error={errors.chief_engineer_name?.message} {...register("chief_engineer_name")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Nature</legend>
        <div className="flex gap-4 text-sm text-slate-700">
          <label className="flex items-center gap-1.5">
            <input type="radio" value="accident" {...register("nature_type")} /> Accident
          </label>
          <label className="flex items-center gap-1.5">
            <input type="radio" value="hazardous_occurrence" {...register("nature_type")} /> Hazardous Occurrence
          </label>
        </div>
        {errors.nature_type && <p className="text-sm text-red-600">{errors.nature_type.message}</p>}

        {isAccident && (
          <div className="flex flex-col gap-2">
            <Controller
              control={control}
              name="nature_of_incident_id"
              render={({ field }) => (
                <div className="flex flex-wrap gap-3 text-sm text-slate-700">
                  {options.nature_of_incidents.map((n) => (
                    <label key={n.id} className="flex items-center gap-1.5">
                      <input
                        type="radio"
                        checked={field.value === n.id}
                        onChange={() => field.onChange(n.id)}
                        onBlur={field.onBlur}
                      />
                      {n.label}
                    </label>
                  ))}
                </div>
              )}
            />
            {errors.nature_of_incident_id && <p className="text-sm text-red-600">{errors.nature_of_incident_id.message}</p>}
            {isCollisionNature && (
              <TextField label="Other Vessel(s) Details" error={errors.accident_collision?.message} {...register("accident_collision")} />
            )}
            {isOtherNature && <TextField label="Other Nature" error={errors.others?.message} {...register("others")} />}
          </div>
        )}

        {isHazardous && (
          <div className="flex flex-col gap-2">
            <div className="flex flex-wrap gap-4 text-sm text-slate-700">
              <label className="flex items-center gap-1.5">
                <input type="radio" value="unsafe_act" {...register("hazardous_occurrence_type")} /> Unsafe Act
              </label>
              <label className="flex items-center gap-1.5">
                <input type="radio" value="unsafe_condition" {...register("hazardous_occurrence_type")} /> Unsafe Condition
              </label>
              <label className="flex items-center gap-1.5">
                <input type="radio" value="near_miss" {...register("hazardous_occurrence_type")} /> Near Miss
              </label>
            </div>
            {errors.hazardous_occurrence_type && <p className="text-sm text-red-600">{errors.hazardous_occurrence_type.message}</p>}
          </div>
        )}
      </fieldset>

      <TextareaField label="Statement of Facts" error={errors.statementof_work?.message} {...register("statementof_work")} />

      {isAccident && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">BAC Tested?</span>
              <div className="flex gap-4 text-sm text-slate-700">
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="NO" {...register("bac")} /> No
                </label>
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="YES" {...register("bac")} /> Yes
                </label>
              </div>
            </div>
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">VDR Data Saved?</span>
              <div className="flex gap-4 text-sm text-slate-700">
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="NO" {...register("vdr")} /> No
                </label>
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="YES" {...register("vdr")} /> Yes
                </label>
              </div>
            </div>
          </div>

          <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
            <legend className="px-1 text-sm font-semibold text-slate-700">Particulars of Accident</legend>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <TextField label="Date of Event" type="date" error={errors.dateof_event?.message} {...register("dateof_event")} />
              <TextField label="Time of Event" error={errors.timeof_event?.message} {...register("timeof_event")} />
              <TextField label="Zone" error={errors.zone?.message} {...register("zone")} />
              <TextField label="Country" error={errors.country?.message} {...register("country")} />
              <TextField label="Speed" error={errors.speed?.message} {...register("speed")} />
              <TextField label="Course" error={errors.course?.message} {...register("course")} />
              <TextField label="Draft Forward" error={errors.draft_forward?.message} {...register("draft_forward")} />
              <TextField label="Draft Aft" error={errors.draft_alt?.message} {...register("draft_alt")} />
              <TextField label="Wind Direction" error={errors.wind_direction?.message} {...register("wind_direction")} />
              <TextField label="Direction of Sea" error={errors.direction_sea?.message} {...register("direction_sea")} />
              <TextField label="Direction of Swell" error={errors.direction_swell?.message} {...register("direction_swell")} />
              <TextField label="Geographical Location" error={errors.geographical_location?.message} {...register("geographical_location")} />
              <TextField label="Port of Departure" error={errors.port_departure?.message} {...register("port_departure")} />
              <TextField label="Date of Departure" type="date" error={errors.date_departure?.message} {...register("date_departure")} />
              <TextField label="Port to Which Bound" error={errors.port_which_bound?.message} {...register("port_which_bound")} />
              <TextField label="Type of Cargo" error={errors.type_cargo?.message} {...register("type_cargo")} />
              <TextField label="Cargo Quantity" error={errors.cargo_quantity?.message} {...register("cargo_quantity")} />
              <TextField label="Special Requirement" error={errors.special_requirement?.message} {...register("special_requirement")} />
            </div>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">Atmospheric Conditions</span>
              <div className="flex flex-wrap gap-3">
                <CheckboxField label="Clear" {...register("atmospheric_clear")} />
                <CheckboxField label="Partly Cloudy" {...register("atmospheric_partly_cloudy")} />
                <CheckboxField label="Overcast" {...register("atmospheric_overcast")} />
                <CheckboxField label="Fog" {...register("atmospheric_fog")} />
                <CheckboxField label="Rain" {...register("atmospheric_rain")} />
                <CheckboxField label="Snow" {...register("atmospheric_snow")} />
                <CheckboxField label="Other" {...register("atmospheric_other")} />
              </div>
              {atmosphericOther && (
                <TextField label="Other Condition" error={errors.atmospheric_other_name?.message} {...register("atmospheric_other_name")} />
              )}
            </div>

            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">Distance of Visibility</span>
              <div className="flex flex-wrap gap-3">
                <CheckboxField label="Under 3 Miles" {...register("distance1")} />
                <CheckboxField label="2-5 Miles" {...register("distance2")} />
                <CheckboxField label="Over 5 Miles" {...register("distance3")} />
              </div>
            </div>
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">Sea</span>
              <div className="flex flex-wrap gap-3">
                <CheckboxField label="Smooth to Slight" {...register("sea1")} />
                <CheckboxField label="Moderate to Rough" {...register("sea2")} />
                <CheckboxField label="High" {...register("sea3")} />
              </div>
            </div>
          </fieldset>
        </>
      )}

      {isHazardous && (
        <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
          <legend className="px-1 text-sm font-semibold text-slate-700">Location / Operation</legend>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <SelectField
              label="Location"
              placeholder="Select location..."
              options={options.incident_locations.map((l) => ({ value: String(l.id), label: l.label }))}
              error={errors.incident_location_id?.message}
              {...register("incident_location_id", { setValueAs: (v) => (v ? Number(v) : null) })}
            />
            <TextField label="Ship's Position" error={errors.ship_position?.message} {...register("ship_position")} />
          </div>
          {isOtherLocation && <TextField label="Other Location" error={errors.location_other?.message} {...register("location_other")} />}
          <SelectField
            label="Ship's Operation"
            placeholder="Select operation..."
            options={options.incident_operations.map((o) => ({ value: String(o.id), label: o.label }))}
            error={errors.incident_operation_id?.message}
            {...register("incident_operation_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
          {isOtherOperation && (
            <TextField label="Other Operation" error={errors.ship_operation_other?.message} {...register("ship_operation_other")} />
          )}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-slate-700">PPE Used</span>
              <div className="flex gap-3 text-sm text-slate-700">
                {["NO", "YES", "NA"].map((v) => (
                  <label key={v} className="flex items-center gap-1">
                    <input type="radio" value={v} {...register("hazardous_occurrence_ppe_used")} /> {v}
                  </label>
                ))}
              </div>
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-slate-700">Severity</span>
              <div className="flex gap-3 text-sm text-slate-700">
                {["HIGH", "MEDIUM", "LOW"].map((v) => (
                  <label key={v} className="flex items-center gap-1">
                    <input type="radio" value={v} {...register("hazardous_occurrence_severity")} /> {v}
                  </label>
                ))}
              </div>
              {errors.hazardous_occurrence_severity && <p className="text-sm text-red-600">{errors.hazardous_occurrence_severity.message}</p>}
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-slate-700">Likelihood</span>
              <div className="flex gap-3 text-sm text-slate-700">
                {["HIGH", "MEDIUM", "LOW"].map((v) => (
                  <label key={v} className="flex items-center gap-1">
                    <input type="radio" value={v} {...register("hazardous_occurrence_likelihood")} /> {v}
                  </label>
                ))}
              </div>
              {errors.hazardous_occurrence_likelihood && <p className="text-sm text-red-600">{errors.hazardous_occurrence_likelihood.message}</p>}
            </div>
          </div>
          <TextareaField label="PPE Remarks" {...register("hazardous_occurrence_ppe_used_comment")} />
          <TextareaField label="Severity Remarks" {...register("hazardous_occurrence_severity_comment")} />
          <TextareaField label="Likelihood Remarks" {...register("hazardous_occurrence_likelihood_comment")} />
        </fieldset>
      )}

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Injury to People</legend>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SelectField
            label="Severity"
            placeholder="None"
            options={["FATALITY", "FAC", "LWC", "MTC", "PPD", "PTD", "RWC"].map((v) => ({ value: v, label: v }))}
            {...register("severity_itp")}
          />
          <SelectField
            label="Type of Injury"
            placeholder="Select type..."
            options={options.types_of_injury.map((t) => ({ value: String(t.id), label: t.label }))}
            error={errors.type_of_injury_id?.message}
            {...register("type_of_injury_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
        </div>
        {isOtherTypeOfInjury && (
          <TextField label="Other Type of Injury" error={errors.other_typeof_injury?.message} {...register("other_typeof_injury")} />
        )}
        <SelectField
          label="Affected Area"
          placeholder="Select area..."
          options={options.locations_of_injury.map((l) => ({ value: String(l.id), label: l.label }))}
          error={errors.location_of_injury_id?.message}
          {...register("location_of_injury_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        {isOtherLocationOfInjury && (
          <TextField label="Other Affected Area" error={errors.other_affected_area?.message} {...register("other_affected_area")} />
        )}
        <TextareaField label="Remarks" {...register("comment_itp")} />
        {severityItp && <p className="text-xs text-slate-400">Leave severity unset if there was no injury.</p>}
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Damage to Vessel / Port Facility</legend>
        <SelectField
          label="Severity"
          placeholder="None"
          options={[
            { value: "low", label: "Low" },
            { value: "medium", label: "Medium" },
            { value: "high", label: "High" },
          ]}
          {...register("severity_itv")}
        />
        <TextareaField label="Remarks" {...register("comment_itv")} />
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Root Cause / Investigation / Analysis / Corrective Action</legend>
        {rootCauseArray.fields.map((field, index) => {
          const rowRootCauseId = watch(`root_causes.${index}.root_cause_id`);
          const category = options.root_cause_categories.find((c) => c.root_causes.some((rc) => rc.id === rowRootCauseId));
          const isOtherCategory = category?.label === "OTHER";

          return (
            <div key={field.id} className="flex flex-col gap-2 rounded-md border border-slate-100 p-3">
              <div className="flex items-center justify-between">
                <SelectField
                  label="Root Cause"
                  placeholder="Select root cause..."
                  className="max-w-md"
                  options={options.root_cause_categories.flatMap((c) =>
                    c.root_causes.map((rc) => ({ value: String(rc.id), label: `${c.label}: ${rc.label}` })),
                  )}
                  {...register(`root_causes.${index}.root_cause_id`, { setValueAs: (v) => (v ? Number(v) : null) })}
                />
                <Button
                  type="button"
                  variant="secondary"
                  className="!px-2 !py-1 text-xs text-red-600"
                  onClick={() => rootCauseArray.remove(index)}
                >
                  Remove
                </Button>
              </div>
              {isOtherCategory && (
                <TextField
                  label="Specify"
                  error={errors.root_causes?.[index]?.root_cause_other?.message}
                  {...register(`root_causes.${index}.root_cause_other`)}
                />
              )}
              <TextareaField
                label="Investigation"
                error={errors.root_causes?.[index]?.investigation?.message}
                {...register(`root_causes.${index}.investigation`)}
              />
              <TextareaField
                label="Analysis"
                error={errors.root_causes?.[index]?.analysis?.message}
                {...register(`root_causes.${index}.analysis`)}
              />
              <TextareaField
                label="Corrective Actions"
                error={errors.root_causes?.[index]?.corrective_actions?.message}
                {...register(`root_causes.${index}.corrective_actions`)}
              />
            </div>
          );
        })}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() =>
            rootCauseArray.append({ root_cause_id: null, root_cause_other: "", investigation: "", analysis: "", corrective_actions: "" })
          }
        >
          + Add Root Cause
        </Button>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Participants</legend>
        {personArray.fields.map((field, index) => (
          <div key={field.id} className="grid grid-cols-1 items-end gap-2 sm:grid-cols-[1fr_1fr_auto]">
            <TextField
              label="Name"
              error={errors.persons?.[index]?.person_name?.message}
              {...register(`persons.${index}.person_name`)}
            />
            <TextField
              label="Position"
              error={errors.persons?.[index]?.position?.message}
              {...register(`persons.${index}.position`)}
            />
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => personArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => personArray.append({ person_name: "", position: "" })}
        >
          + Add Person
        </Button>

        {isAccident && (
          <div className="grid grid-cols-1 gap-4 border-t border-slate-100 pt-3 sm:grid-cols-4">
            <TextField label="Crew Onboard" type="number" {...register("crew_onboard", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Other Onboard" type="number" {...register("other_onboard", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Total Onboard" type="number" {...register("total_onboard", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <div />
            <TextField label="Crew Dead" type="number" {...register("crew_dead", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Other Dead" type="number" {...register("other_dead", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Total Dead" type="number" {...register("total_dead", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <div />
            <TextField label="Crew Missing" type="number" {...register("crew_missing", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Other Missing" type="number" {...register("other_missing", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Total Missing" type="number" {...register("total_missing", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <div />
            <TextField label="Crew Injured" type="number" {...register("crew_injured", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Other Injured" type="number" {...register("other_injured", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
            <TextField label="Total Injured" type="number" {...register("total_injured", { setValueAs: (v) => (v === "" ? null : Number(v)) })} />
          </div>
        )}
      </fieldset>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Signed By" error={errors.signed_by?.message} {...register("signed_by")} />
        <TextField label="Date Signed" type="date" error={errors.date_signed?.message} {...register("date_signed")} />
      </div>
      <TextareaField label="Vessel Remarks" {...register("vessel_remarks")} />

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">For Office Use Only</legend>

        {isAccident && (
          <div className="flex flex-col gap-1">
            <span className="text-sm font-medium text-slate-700">Reported to Flag State or RO?</span>
            <div className="flex gap-4 text-sm text-slate-700">
              <label className="flex items-center gap-1.5">
                <input type="radio" value="NO" {...register("fs_ro")} /> No
              </label>
              <label className="flex items-center gap-1.5">
                <input type="radio" value="YES" {...register("fs_ro")} /> Yes
              </label>
            </div>
          </div>
        )}

        {isHazardous && (
          <>
            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-slate-700">Subject for Investigation?</span>
              <div className="flex gap-4 text-sm text-slate-700">
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="NO" {...register("subject_investigation")} /> No
                </label>
                <label className="flex items-center gap-1.5">
                  <input type="radio" value="YES" {...register("subject_investigation")} /> Yes
                </label>
              </div>
            </div>
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">Required Evidence</span>
              <div className="flex flex-wrap gap-3">
                <CheckboxField label="Safety Meeting" {...register("evidence_safety_meeting")} />
                <CheckboxField label="Certificate / Record of Training" {...register("evidence_certificate")} />
                <CheckboxField label="Logbook Entry" {...register("evidence_logbook")} />
                <CheckboxField label="Delivery Note" {...register("evidence_delivery")} />
                <CheckboxField label="Photo" {...register("evidence_photo")} />
                <CheckboxField label="Company Forms" {...register("evidence_company")} />
                <CheckboxField label="Others" {...register("evidence_others")} />
              </div>
              {evidenceOthers && (
                <TextField label="Specify" error={errors.evidence_others_name?.message} {...register("evidence_others_name")} />
              )}
            </div>
            <TextareaField label="Causal Factor" {...register("causal_factor")} />
            <TextareaField label="Intermediate Cause" {...register("intermediate_cause")} />
            <TextareaField label="Root Cause" {...register("shore_root_cause_summary")} />
          </>
        )}

        <div className="grid grid-cols-1 gap-4 border-t border-amber-100 pt-3 sm:grid-cols-2">
          <TextField label="Date Received" type="date" error={errors.date_received?.message} {...register("date_received")} />
          <TextField label="Reviewed By" error={errors.reviewed_by?.message} {...register("reviewed_by")} />
          <TextField label="Investigator" error={errors.investigator?.message} {...register("investigator")} />
          <TextField label="DPA" error={errors.dpa?.message} {...register("dpa")} />
          <TextField label="Closing Date" type="date" error={errors.closing_date?.message} {...register("closing_date")} />
        </div>
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
