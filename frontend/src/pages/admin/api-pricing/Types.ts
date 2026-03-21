export interface ApiPricingEntry {
    id: number;
    api_name: string;
    model_key: string;
    input_price_per_1m: number;
    output_price_per_1m: number;
    updated_at: string;
    updated_by: { id: number; name: string } | null;
}

export interface EditForm {
    input_price_per_1m: string;
    output_price_per_1m: string;
}

export interface CreateForm {
    api_name: string;
    model_key: string;
    input_price_per_1m: string;
    output_price_per_1m: string;
}

export interface VaultEntry {
    id: number;
    nickname: string;
    provider: string;
}

export interface DiscoveredModel {
    id: string;
    name: string;
}

export interface ApiPricingLog {
    id: number;
    old_input_price_per_1m: string;
    new_input_price_per_1m: string;
    old_output_price_per_1m: string;
    new_output_price_per_1m: string;
    created_at: string;
    updated_by: { id: number; name: string } | null;
}

export const providerColors: Record<string, string> = {
    'Google Gemini': 'bg-blue-100 text-blue-700',
    'OpenAI': 'bg-emerald-100 text-emerald-700',
    'xAI': 'bg-purple-100 text-purple-700',
    'Genérico': 'bg-slate-100 text-slate-600',
    'gemini': 'bg-blue-100 text-blue-700',
    'openai': 'bg-emerald-100 text-emerald-700',
    'grok': 'bg-purple-100 text-purple-700'
};
