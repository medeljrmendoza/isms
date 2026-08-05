import { useEffect, useState } from "react";
import { manualsService } from "./manualsService";
import type { ManualChapterNode, ManualDocumentNode, ManualFormNode, ManualOption, ManualSearchResult } from "./manuals";
import { Button } from "../../components/ui/Button";

interface SelectedItem {
  kind: "document" | "form";
  label: string;
  dateOfRevision?: string;
}

/** Ported from admin/sms/sms_view.php (the "Manuals" browser). */
export function ManualsPage() {
  const [vessels, setVessels] = useState<ManualOption[]>([]);
  const [smsType, setSmsType] = useState("");
  const [vesselId, setVesselId] = useState("");
  const [appliedSmsType, setAppliedSmsType] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("");

  const [chapters, setChapters] = useState<ManualChapterNode[]>([]);
  const [expanded, setExpanded] = useState<Set<number | string>>(new Set());
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filterError, setFilterError] = useState<string | null>(null);

  const [searchTerm, setSearchTerm] = useState("");
  const [searchResults, setSearchResults] = useState<ManualSearchResult[] | null>(null);
  const [searching, setSearching] = useState(false);

  const [selected, setSelected] = useState<SelectedItem | null>(null);

  useEffect(() => {
    manualsService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!appliedSmsType) {
      setChapters([]);
      return;
    }
    setLoading(true);
    manualsService
      .tree(appliedSmsType, appliedVesselId || undefined)
      .then((data) => {
        setChapters(data);
        setExpanded(new Set(data.map((c) => c.id)));
        setError(null);
      })
      .catch(() => setError("Couldn't load manuals. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedSmsType, appliedVesselId]);

  const applyFilter = () => {
    if (!smsType) {
      setFilterError("Please select Manual.");
      return;
    }
    if (smsType === "VESSEL" && !vesselId) {
      setFilterError("Please select Vessel.");
      return;
    }
    setFilterError(null);
    setSearchResults(null);
    setSelected(null);
    setAppliedSmsType(smsType);
    setAppliedVesselId(smsType === "VESSEL" ? vesselId : "");
  };

  const toggleChapter = (id: number | string) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const selectDocument = (doc: ManualDocumentNode) => {
    setSelected({ kind: "document", label: `(${doc.reference_no}) ${doc.manual_name}`, dateOfRevision: doc.date_of_revision });
  };

  const selectForm = (form: ManualFormNode) => {
    setSelected({ kind: "form", label: `(${form.reference_no}) ${form.file_name}` });
  };

  const runSearch = () => {
    if (!searchTerm.trim()) return;
    setSearching(true);
    manualsService
      .search(searchTerm.trim(), appliedSmsType || "ALL", appliedVesselId || undefined)
      .then((results) => {
        setSearchResults(results);
        setSelected(null);
      })
      .catch(() => setError("Search failed. Please try again."))
      .finally(() => setSearching(false));
  };

  const clearSearch = () => {
    setSearchResults(null);
    setSearchTerm("");
  };

  return (
    <div className="p-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Select Manual</label>
            <select value={smsType} onChange={(e) => setSmsType(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select Manual</option>
              <option value="ALL">ALL</option>
              <option value="VESSEL">BY VESSEL</option>
            </select>
          </div>
          {smsType === "VESSEL" && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-medium text-slate-500">Vessel</label>
              <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Select vessel...</option>
                {vessels.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.label}
                  </option>
                ))}
              </select>
            </div>
          )}
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}
        </div>

        <div className="flex items-end gap-2">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Search</label>
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && runSearch()}
              placeholder="Search for..."
              className="w-56 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <Button type="button" variant="secondary" className="!px-3 !py-1.5 text-sm" onClick={runSearch} isLoading={searching}>
            Search
          </Button>
          {searchResults !== null && (
            <Button type="button" variant="info" className="!px-3 !py-1.5 text-sm" onClick={clearSearch}>
              Clear
            </Button>
          )}
        </div>
      </div>

      {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[320px_1fr]">
        <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-4 py-3">
            <h2 className="text-sm font-semibold text-slate-800">{searchResults !== null ? "Search Results" : "Manuals"}</h2>
          </div>
          <div className="max-h-[70vh] overflow-y-auto p-2">
            {searchResults !== null ? (
              searchResults.length === 0 ? (
                <p className="p-3 text-sm text-slate-400">No results found.</p>
              ) : (
                <ul className="flex flex-col gap-1">
                  {searchResults.map((result) => (
                    <li key={`${result.type}-${result.id}`}>
                      <button
                        type="button"
                        className="w-full rounded px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100"
                        onClick={() => setSelected({ kind: result.type, label: result.label })}
                      >
                        {result.type === "form" ? "▸ " : "▪ "}
                        {result.label}
                      </button>
                    </li>
                  ))}
                </ul>
              )
            ) : loading ? (
              <p className="p-3 text-sm text-slate-400">Loading...</p>
            ) : !appliedSmsType ? (
              <p className="p-3 text-sm text-slate-400">Select a Manual filter and click Filter to browse.</p>
            ) : chapters.length === 0 ? (
              <p className="p-3 text-sm text-slate-400">No manuals found.</p>
            ) : (
              <ul className="flex flex-col gap-1">
                {chapters.map((chapter) => (
                  <li key={chapter.id}>
                    <button
                      type="button"
                      className="w-full rounded bg-slate-800 px-2 py-1.5 text-left text-sm font-medium text-white hover:bg-slate-700"
                      onClick={() => toggleChapter(chapter.id)}
                    >
                      {chapter.label}
                    </button>
                    {expanded.has(chapter.id) && (
                      <ul className="mt-1 flex flex-col gap-0.5 border-l border-slate-200 pl-2">
                        {chapter.documents.map((doc) => (
                          <li key={doc.id}>
                            <button
                              type="button"
                              className="w-full rounded px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100"
                              onClick={() => selectDocument(doc)}
                            >
                              ▪ ({doc.reference_no}) {doc.manual_name}
                            </button>
                            {doc.forms.length > 0 && (
                              <ul className="ml-4 flex flex-col gap-0.5">
                                {doc.forms.map((form) => (
                                  <li key={form.id}>
                                    <button
                                      type="button"
                                      className="w-full rounded px-2 py-1.5 text-left text-sm text-slate-600 hover:bg-slate-100"
                                      onClick={() => selectForm(form)}
                                    >
                                      ▸ ({form.reference_no}) {form.file_name}
                                    </button>
                                  </li>
                                ))}
                              </ul>
                            )}
                          </li>
                        ))}
                      </ul>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-4 py-3">
            <h2 className="text-sm font-semibold text-slate-800">Contents</h2>
          </div>
          <div className="flex min-h-[50vh] items-center justify-center p-6">
            {!selected ? (
              <p className="text-center text-slate-400">Please click on a manual to the left to view.</p>
            ) : (
              <div className="w-full max-w-md">
                <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{selected.kind === "document" ? "Document" : "Form"}</p>
                <h3 className="mt-1 text-lg font-semibold text-slate-800">{selected.label}</h3>
                {selected.dateOfRevision && <p className="mt-2 text-sm text-slate-500">Date of Revision: {selected.dateOfRevision}</p>}
                <p className="mt-4 text-sm text-slate-400">File preview isn't available in this environment.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
