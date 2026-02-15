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
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $total = (int) $this->total_questions;
            $distribution = $this->input('subject_distribution', []);
            
            $sum = collect($distribution)->sum(fn($v) => (int) $v);
            
            if ($sum !== $total) {
                $validator->errors()->add(
                    'subject_distribution',
                    "A soma da distribuição ({$sum}) deve ser igual ao total de questões ({$total})."
                );
            }
        });
    }
}
