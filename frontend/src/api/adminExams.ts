import axios from './axios';
import { Question } from '../types';

export interface AdminExamSummary {
    arquivo_origem: string | null;
    year: number | null;
    organization: string | null;
    institution: string | null;
    role: string | null;
    total_questions: number;
    last_update: string;
}

export interface AdminExamsResponse {
    data: AdminExamSummary[];
    current_page: number;
    last_page: number;
    total: number;
}

export const getAdminExamsList = async (page: number = 1, filters?: any): Promise<AdminExamsResponse> => {
    let url = `/api/v1/admin/exams?page=${page}`;
    if (filters) {
        if (filters.year) url += `&year=${filters.year}`;
        if (filters.organization) url += `&organization=${filters.organization}`;
        if (filters.institution) url += `&institution=${filters.institution}`;
        if (filters.role) url += `&role=${filters.role}`;
        if (filters.sort) url += `&sort=${filters.sort}`;
        if (filters.import_id) url += `&import_id=${filters.import_id}`;
    }
    const response = await axios.get(url);
    return response.data;
};

export const getAdminExamDetails = async (encodedId: string, filters?: any): Promise<{ data: Question[] }> => {
    let url = `/api/v1/admin/exams/${encodedId}`;
    if (filters) {
        url += '?';
        const params = new URLSearchParams();
        if (filters.year) params.append('year', filters.year);
        if (filters.organization) params.append('organization', filters.organization);
        if (filters.institution) params.append('institution', filters.institution);
        if (filters.role) params.append('role', filters.role);
        if (filters.import_id) params.append('import_id', filters.import_id);
        url += params.toString();
    }
    const response = await axios.get(url);
    return response.data;
};
