import { useState, type FormEvent } from "react";
import { pmsConfigurationService } from "./pmsConfigurationService";
import { CONFIGURATION_VALUES } from "./pmsConfiguration";
import type { PmsConfigurationRow } from "./pmsConfiguration";
import { Button } from "../../components/ui/Button";

interface PmsConfigurationFormProps {
  record: PmsConfigurationRow;
  onCancel: () => void;
  onSuccess: (updated: PmsConfigurationRow) => void;
}

/** Ported from add_configuration_v.php. */
export function PmsConfigurationForm({ record, onCancel, onSuccess }: PmsConfigurationFormProps) {
  const [configuration, setConfiguration] = useState(record.configuration ?? "");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!configuration) {
      setError("Required Field");
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const updated = await pmsConfigurationService.update(record.id, configuration);
      onSuccess(updated);
    } catch {
      setError("Data was not saved! An error occured!");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <table className="w-full border border-slate-200 text-sm">
        <tbody>
          <tr>
            <td className="w-1/5 border-r-0 px-2 py-2 align-top text-slate-600">Vessel</td>
            <td className="px-2 py-2">
              <input type="text" value={record.vessel_name} disabled className="w-full rounded-md border border-slate-300 bg-slate-50 px-2 py-1.5 text-sm" />
              <input
                type="text"
                value={record.short_name ?? ""}
                disabled
                className="mt-1 w-full rounded-md border border-slate-300 bg-slate-50 px-2 py-1.5 text-sm"
              />
            </td>
          </tr>
          <tr>
            <td className="border-r-0 px-2 py-2 align-top text-slate-600">Configuration</td>
            <td className="px-2 py-2">
              <label className="mr-4 inline-flex items-center gap-1.5 text-sm text-slate-700">
                <input type="radio" name="config_value" value="SHORE" checked={configuration === "SHORE"} onChange={(e) => setConfiguration(e.target.value)} />
                SHORE
              </label>
              {CONFIGURATION_VALUES.filter((v) => v !== "SHORE").map((v) => (
                <label key={v} className="inline-flex items-center gap-1.5 text-sm text-slate-700">
                  <input type="radio" name="config_value" value={v} checked={configuration === v} onChange={(e) => setConfiguration(e.target.value)} />
                  {v}
                </label>
              ))}
            </td>
          </tr>
        </tbody>
      </table>

      {error && <p className="text-sm text-red-600">{error}</p>}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="secondary" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" variant="success" disabled={submitting}>
          Save
        </Button>
      </div>
    </form>
  );
}
