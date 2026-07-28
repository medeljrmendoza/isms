import { Modal } from "../ui/Modal";

const ACTION_LEGEND: { label: string; meaning: string; color: string }[] = [
  { label: "ADD", meaning: "Add Record", color: "bg-green-600" },
  { label: "EDIT", meaning: "Edit Record", color: "bg-blue-600" },
  { label: "VIEW", meaning: "View Record", color: "bg-sky-500" },
  { label: "DEL", meaning: "Delete Record", color: "bg-red-600" },
  { label: "REOPEN", meaning: "Reopen Record", color: "bg-amber-500" },
  { label: "SEARCH", meaning: "Search for a Record", color: "bg-blue-600" },
  { label: "APPROVE", meaning: "Approve / Publish Record", color: "bg-green-600" },
  { label: "UPLOAD", meaning: "Upload File", color: "bg-blue-600" },
  { label: "RESET", meaning: "Reset", color: "bg-amber-500" },
  { label: "ENABLE", meaning: "Enable / Active Record", color: "bg-green-600" },
  { label: "DISABLE", meaning: "Disable / Inactive Record", color: "bg-amber-500" },
  { label: "LARGER", meaning: "Show Larger", color: "bg-blue-600" },
  { label: "IMPORT", meaning: "Import", color: "bg-green-600" },
  { label: "EXPORT", meaning: "Export", color: "bg-green-600" },
];

const USER_LEVELS: { level: string; description: string }[] = [
  {
    level: "SUPERADMIN",
    description:
      "First level. Can view, add, edit, delete, reset, publish, export, import and approve records, and is responsible for user accounts. Shore and vessel logs are only available at this level.",
  },
  {
    level: "ADMIN",
    description: "Second level. Can view, add, export, import and edit records.",
  },
  {
    level: "MEMBER",
    description: "Third level. Can only view records.",
  },
];

export function GuideModal({ onClose }: { onClose: () => void }) {
  return (
    <Modal title="Guide" onClose={onClose}>
      <div className="space-y-6">
        <div>
          <p className="mb-2 text-sm font-semibold text-blue-600">Necessary Information</p>
          <p className="mb-3 text-sm text-slate-600">Buttons / icons / links to know:</p>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {ACTION_LEGEND.map((item) => (
              <div key={item.label} className="flex items-center gap-2">
                <span
                  className={`inline-flex w-20 shrink-0 items-center justify-center rounded px-2 py-1 text-[10px] font-bold text-white ${item.color}`}
                >
                  {item.label}
                </span>
                <span className="text-sm text-slate-700">{item.meaning}</span>
              </div>
            ))}
          </div>
        </div>

        <div>
          <p className="mb-2 text-sm font-semibold text-blue-600">Level of Users</p>
          <p className="mb-3 text-sm text-slate-600">
            The process / action available depends on the user's level:
          </p>
          <div className="space-y-3">
            {USER_LEVELS.map((item) => (
              <div key={item.level} className="rounded border border-slate-200 p-3">
                <p className="text-sm font-bold text-slate-800">{item.level}</p>
                <p className="mt-1 text-sm text-slate-600">{item.description}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </Modal>
  );
}
