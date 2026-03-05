<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('subscriptions')->orderBy('price')->get();
        return response()->json($plans);
    }

    private function normalizeInterval(string $interval): string
    {
        $map = ['monthly' => 'month', 'yearly' => 'year'];
        return $map[strtolower(trim($interval))] ?? strtolower(trim($interval));
    }

    public function store(Request $request)
    {
        if ($request->has('interval')) {
            $request->merge(['interval' => $this->normalizeInterval($request->interval)]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'simulations_limit' => 'required|integer|min:0',
            'essays_limit' => 'required|integer|min:0',
            'daily_question_limit' => 'required|integer|min:0',
            'max_ai_questions' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        if (Plan::where('slug', $data['slug'])->exists()) {
            $data['slug'] .= '-' . uniqid();
        }

        $plan = Plan::create($data);

        return response()->json([
            'message' => 'Plano criado com sucesso!',
            'plan' => $plan
        ]);
    }

    public function show(Plan $plan)
    {
        // Normaliza o intervalo na leitura para caso haja dados legados no banco
        $data = $plan->toArray();
        $data['interval'] = $this->normalizeInterval($data['interval'] ?? 'month');
        return response()->json($data);
    }

    public function update(Request $request, Plan $plan)
    {
        if ($request->has('interval')) {
            $request->merge(['interval' => $this->normalizeInterval($request->interval)]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'simulations_limit' => 'required|integer|min:0',
            'essays_limit' => 'required|integer|min:0',
            'daily_question_limit' => 'required|integer|min:0',
            'max_ai_questions' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $plan->update($data);

        return response()->json([
            'message' => 'Plano atualizado com sucesso!',
            'plan' => $plan
        ]);
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->count() > 0) {
            return response()->json(['message' => 'Não é possível excluir um plano com assinaturas ativas.'], 422);
        }
        $plan->delete();
        return response()->json(['message' => 'Plano excluído com sucesso!']);
    }
}
