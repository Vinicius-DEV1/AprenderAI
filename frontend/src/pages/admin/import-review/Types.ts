/**
 * Import Review Types
 * Centralized interfaces for the question triage and review process.
 */

export interface ImportQuestion {
  id: number;
  statement: string;
  image_path: string | null;
  review_status: 'pending' | 'review' | 'approved';
  difficulty: number | null;
  difficulty_reasoning: string | null;
  explanation: string | null;
  subjects: Array<{ id: number; name: string }>;
  topics: Array<{ id: number; name: string }>;
  alternatives: Array<ImportAlternative>;
  images: Array<ImportImage>;
  organization?: string;
  institution?: string;
  role?: string;
  year?: number;
  arquivo_origem?: string;
}

export interface ImportAlternative {
  id: number | string;
  label: string;
  content: string;
  is_correct: boolean;
}

export interface ImportImage {
  id: number;
  path: string;
  image_url: string;
}

export interface ImportItem {
  id: number;
  import?: {
    batch_name: string;
  };
}

export interface TriageLog {
  id: number;
  triage_type: string;
  status: string;
  quality_score: number | null;
  issues_detected: string[] | null;
  processed_by: string;
  created_at: string;
}

export interface QuestionReviewData {
  question: ImportQuestion;
  importItem?: ImportItem;
  prev_id: number | null;
  next_id: number | null;
}
