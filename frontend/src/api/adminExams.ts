import axios from './axios';
import { Question } from '../types';

export interface AdminExamSummary {
    arquivo_origem: string | null;
    year: number | null;
    organization: string | null;
    institution: string | null;
    role: string | null;
    total_questions: number;
}

export interface AdminExamsResponse {
    data: AdminExamSummary[];
    current_page: number;
    last_page: number;
    total: number;
}

export const getAdminExamsList = async (page: number = 1, filters?: any): Promise<AdminExamsResponse> => {
    let url = `/admin/exams?page=${page}`;
    if (filters) {
        if (filters.year) url += `&year=${filters.year}`;
        if (filters.organization) url += `&organization=${filters.organization}`;
        if (filters.institution) url += `&institution=${filters.institution}`;
    }
    const response = await axios.get(url);
    return response.data;
};

export const getAdminExamDetails = async (encodedId: string, filters?: any): Promise<{ data: Question[] }> => {
    let url = `/admin/exams/${encodedId}`;
    if (filters) {
        url += '?';
        const params = new URLSearchParams();
        if (filters.year) params.append('year', filters.year);
        if (filters.organization) params.append('organization', filters.organization);
        if (filters.institution) params.append('institution', filters.institution);
        if (filters.role) params.append('role', filters.role);
        url += params.toString();
    }
    const response = await axios.get(url);
    return response.data;
};
