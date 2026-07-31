import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildPmsWorkPlanSchema, type PmsWorkPlanFormValues } from "./pmsWorkPlanSchema";
import { pmsWorkPlanService } from "./pmsWorkPlanService";
import type { PmsWorkPlanDetail, PmsWorkPlanOption, PmsWorkPlanOptions } from "./pmsWorkPlan";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface PmsWorkPlanFormProps {
  adhoc?: PmsWorkPlanDetail;
  vessels: PmsWorkPlanOption[];
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: PmsWorkPlanOptions = { vessels: [], departments: [], job_classes: [], job_types: [], components: [] };

function emptyValues(): PmsWorkPlanFormValues {
  return {
    vessel_id: "",
    type: "EQUIPMENT",
    pms_department_id: null,
    pms_equipment_id: null,
    pms_part_id: null,
    location: "",
    sub_location: "",
    activity_name: "",
    pms_job_class_id: null,
    pms_job_type_id: null,
    incharge: "",
    assignee: "",
    work_procedure: "",
    date_of_activity: new Date().toISOString().slice(0, 10),
    description: "",
    remarks: "",
    inventory: [],
  };
}

function detailToFormValues(d: PmsWorkPlanDetail): PmsWorkPlanFormValues {
  return {
    vessel_id: d.vessel_id,
    type: d.type,
    pms_department_id: d.pms_department_id,
    pms_equipment_id: d.pms_equipment_id,
    pms_part_id: d.pms_part_id,
    location: d.location ?? "",
    sub_location: d.sub_location ?? "",
    activity_name: d.activity_name,
    pms_job_class_id: d.pms_job_class_id,
    pms_job_type_id: d.pms_job_type_id,
    incharge: d.incharge,
    assignee: d.assignee ?? "",
    work_procedure: d.work_procedure ?? "",
    date_of_activity: d.date_of_activity,
    description: d.description ?? "",
    remarks: d.remarks ?? "",
    inventory: d.inventory.map((i) => ({
      pms_part_id: i.pms_part_id,
      part_name: i.part_name ?? undefined,
      equipment_name: i.equipment_name ?? undefined,
      new_qty: i.new_qty,
      reconditioned_qty: i.reconditioned_qty,
    })),
  };
}

/** Ported from admin/pms_work_plan/add_pms_work_plan_v.php. Not ported: file attachments (no file storage anywhere in this migration). */
export function PmsWorkPlanForm({ adhoc, vessels, onSuccess, onCancel }: PmsWorkPlanFormProps) {
  const isCreate = !adhoc;
  const [options, setOptions] = useState<PmsWorkPlanOptions>(emptyOptions);
  const [partOptions, setPartOptions] = useState<PmsWorkPlanOption[]>([]);
  const [searchKey, setSearchKey] = useState("");
  const [searchResults, setSearchResults] = useState<{ id: number; part_name: string; equipment_name: string | null }[]>([]);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    setError,
    control,
    formState: { errors, isSubmitting },
  } = useForm<PmsWorkPlanFormValues>({
    resolver: zodResolver(buildPmsWorkPlanSchema(isCreate)),
    defaultValues: adhoc ? detailToFormValues(adhoc) : emptyValues(),
  });

  const inventoryArray = useFieldArray({ control, name: "inventory" });
  const type = watch("type");
  const vesselId = watch("vessel_id");
  const equipmentId = watch("pms_equipment_id");

  useEffect(() => {
    const vId = isCreate ? (vesselId ? Number(vesselId) : undefined) : adhoc?.vessel_id;
    pmsWorkPlanService.options(vId).then(setOptions).catch(() => undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isCreate ? vesselId : adhoc?.vessel_id]);

  // Department/Job Class/Job Type/Component <select>s are uncontrolled (register()),
  // so their initial value can't apply until options — fetched async — actually
  // exist in the DOM. Re-applying defaults once they load fixes edit pre-population.
  useEffect(() => {
    if (adhoc && (options.departments.length > 0 || options.components.length > 0)) {
      reset(detailToFormValues(adhoc));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  useEffect(() => {
    if (!equipmentId) {
      setPartOptions([]);
      return;
    }
    pmsWorkPlanService.parts(Number(equipmentId)).then(setPartOptions).catch(() => undefined);
  }, [equipmentId]);

  // Same async-options issue for the Part <select>, one level further removed.
  useEffect(() => {
    if (adhoc && partOptions.length > 0) {
      setValue("pms_part_id", adhoc.pms_part_id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [partOptions]);

  useEffect(() => {
    if (searchKey.length < 2) {
      setSearchResults([]);
      return;
    }
    const handle = setTimeout(() => {
      pmsWorkPlanService
        .searchParts(searchKey)
        .then((results) => setSearchResults(results.map((r) => ({ id: r.id, part_name: r.part_name, equipment_name: r.equipment_name }))))
        .catch(() => undefined);
    }, 250);
    return () => clearTimeout(handle);
  }, [searchKey]);

  const addInventoryItem = (item: { id: number; part_name: string; equipment_name: string | null }) => {
    if (inventoryArray.fields.some((f) => f.pms_part_id === item.id)) return;
    inventoryArray.append({ pms_part_id: item.id, part_name: item.part_name, equipment_name: item.equipment_name ?? undefined, new_qty: 0, reconditioned_qty: 0 });
    setSearchKey("");
    setSearchResults([]);
  };

  const onSubmit = async (values: PmsWorkPlanFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await pmsWorkPlanService.create(values);
      } else {
        await pmsWorkPlanService.update(adhoc.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        const firstMessage = Object.values(fieldErrors).flat()[0];
        if (fieldErrors.inventory) {
          setFormError(firstMessage ?? "Item's qty on inventory is not enough.");
        } else {
          Object.entries(fieldErrors).forEach(([field, messages]) => {
            setError(field as keyof PmsWorkPlanFormValues, { message: messages[0] });
          });
        }
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {isCreate ? (
        <SelectField
          label="Vessel"
          placeholder="Select vessel..."
          options={vessels.map((v) => ({ value: String(v.id), label: v.label }))}
          error={errors.vessel_id?.message}
          {...register("vessel_id")}
        />
      ) : (
        <TextField label="Vessel" disabled readOnly value={adhoc.vessel} />
      )}

      <div className="flex flex-col gap-2">
        <span className="text-sm font-medium text-slate-700">Type</span>
        <div className="flex gap-4 text-sm text-slate-700">
          <label className="flex items-center gap-1.5">
            <input type="radio" value="EQUIPMENT" {...register("type")} /> Component
          </label>
          <label className="flex items-center gap-1.5">
            <input type="radio" value="LOCATION" {...register("type")} /> Non-Component
          </label>
        </div>
      </div>

      {type === "EQUIPMENT" ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SelectField
            label="Component"
            placeholder="Select component..."
            options={options.components.map((c) => ({ value: String(c.id), label: c.label }))}
            error={errors.pms_equipment_id?.message}
            {...register("pms_equipment_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
          <SelectField
            label="Part"
            placeholder="Select part..."
            options={partOptions.map((p) => ({ value: String(p.id), label: p.label }))}
            error={errors.pms_part_id?.message}
            {...register("pms_part_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
        </div>
      ) : (
        <>
          <SelectField
            label="Department"
            placeholder="Select department..."
            options={options.departments.map((d) => ({ value: String(d.id), label: d.label }))}
            error={errors.pms_department_id?.message}
            {...register("pms_department_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField label="Location" error={errors.location?.message} {...register("location")} />
            <TextField label="Sub-Location" error={errors.sub_location?.message} {...register("sub_location")} />
          </div>
        </>
      )}

      <TextField label="Activity" error={errors.activity_name?.message} {...register("activity_name")} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="Job Class"
          placeholder="Select..."
          options={options.job_classes.map((c) => ({ value: String(c.id), label: c.label }))}
          error={errors.pms_job_class_id?.message}
          {...register("pms_job_class_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        <SelectField
          label="Job Type"
          placeholder="Select..."
          options={options.job_types.map((t) => ({ value: String(t.id), label: t.label }))}
          error={errors.pms_job_type_id?.message}
          {...register("pms_job_type_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="In-charge" error={errors.incharge?.message} {...register("incharge")} />
        <TextField label="Assignee" error={errors.assignee?.message} {...register("assignee")} />
      </div>

      <TextareaField label="Work Procedure" error={errors.work_procedure?.message} {...register("work_procedure")} />
      <TextField
        label="Date of Activity"
        type="date"
        max={new Date().toISOString().slice(0, 10)}
        error={errors.date_of_activity?.message}
        {...register("date_of_activity")}
      />
      <TextareaField label="Details of Activity" error={errors.description?.message} {...register("description")} />
      <TextareaField label="Remarks" error={errors.remarks?.message} {...register("remarks")} />

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Inventory</legend>

        <div className="relative">
          <TextField
            label="Add Item (search component / part)"
            value={searchKey}
            onChange={(e) => setSearchKey(e.target.value)}
            placeholder="Type to search..."
          />
          {searchResults.length > 0 && (
            <ul className="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg">
              {searchResults.map((r) => (
                <li key={r.id}>
                  <button
                    type="button"
                    className="block w-full px-3 py-1.5 text-left text-sm hover:bg-slate-50"
                    onClick={() => addInventoryItem(r)}
                  >
                    {r.equipment_name ? `${r.equipment_name} — ` : ""}
                    {r.part_name}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        {inventoryArray.fields.length > 0 && (
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200">
                <th className="px-2 py-1 font-semibold text-slate-600">Component</th>
                <th className="px-2 py-1 font-semibold text-slate-600">Part</th>
                <th className="px-2 py-1 font-semibold text-slate-600">New Qty</th>
                <th className="px-2 py-1 font-semibold text-slate-600">Reconditioned Qty</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {inventoryArray.fields.map((field, index) => (
                <tr key={field.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{field.equipment_name ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{field.part_name ?? "—"}</td>
                  <td className="px-2 py-1.5">
                    <input
                      type="number"
                      min="0"
                      className="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm"
                      {...register(`inventory.${index}.new_qty`)}
                    />
                    {errors.inventory?.[index]?.new_qty && (
                      <p className="text-xs text-red-600">{errors.inventory[index]?.new_qty?.message}</p>
                    )}
                  </td>
                  <td className="px-2 py-1.5">
                    <input
                      type="number"
                      min="0"
                      className="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm"
                      {...register(`inventory.${index}.reconditioned_qty`)}
                    />
                    {errors.inventory?.[index]?.reconditioned_qty && (
                      <p className="text-xs text-red-600">{errors.inventory[index]?.reconditioned_qty?.message}</p>
                    )}
                  </td>
                  <td className="px-2 py-1.5">
                    <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs text-red-600" onClick={() => inventoryArray.remove(index)}>
                      Remove
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
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
