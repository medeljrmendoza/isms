export interface ManualOption {
  id: number;
  label: string;
}

export interface ManualsOptions {
  vessels: ManualOption[];
}

export interface ManualFormNode {
  id: number;
  reference_no: string;
  file_name: string;
}

export interface ManualDocumentNode {
  id: number;
  reference_no: string;
  manual_name: string;
  date_of_revision: string;
  forms: ManualFormNode[];
}

export interface ManualChapterNode {
  id: number;
  label: string;
  documents: ManualDocumentNode[];
}

export interface ManualSearchResult {
  type: "document" | "form";
  id: number;
  label: string;
}
