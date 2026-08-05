export interface ManualOption {
  id: number | string;
  label: string;
}

export interface ManualsOptions {
  vessels: ManualOption[];
}

export interface ManualFormNode {
  id: number | string;
  reference_no: string;
  file_name: string;
}

export interface ManualDocumentNode {
  id: number | string;
  reference_no: string;
  manual_name: string;
  date_of_revision: string;
  forms: ManualFormNode[];
}

export interface ManualChapterNode {
  id: number | string;
  label: string;
  documents: ManualDocumentNode[];
}

export interface ManualSearchResult {
  type: "document" | "form";
  id: number | string;
  label: string;
}
