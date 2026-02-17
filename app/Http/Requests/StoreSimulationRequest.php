<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:enem,concurso',
            'subject_distribution' => 'required|array|min:1',
            'subject_distribution.*' => 'required|integer|min:0',
            'include_essay' => 'boolean',
            'total_questions' => 'required|integer|min:40|max:100',
            'custom_time' => 'nullable|integer|min:600|max:43200',
            'organization' => 'nullable|array',
            'organization.*' => 'string',
            'institution' => 'nullable|array',
            'institution.*' => 'string',
            'role' => 'nullable|array',
            'role.*' => 'string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $total = (int)$this->total_questions;
            $distribution = $this->input('subject_distribution', []);

            // Validate Total Sum
            $sum = collect($distribution)->sum(fn($v) => (int)$v);

            if ($sum !== $total) {
                $validator->errors()->add(
                    'subject_distribution',
                    "A soma da distribuição ({$sum}) deve ser igual ao total de questões ({$total})."
                );
            }

            // Validate Subject Keys
            $subjectNames = array_keys($distribution);
            $validCount = \App\Models\Subject::whereIn('name', $subjectNames)->count();

            if ($validCount !== count($subjectNames)) {
                $validator->errors()->add(
                    'subject_distribution',
                    "Um ou mais assuntos selecionados não foram encontrados no sistema."
                );
            }
        });
    }
}
