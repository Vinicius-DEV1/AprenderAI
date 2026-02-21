<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('subscriptions')->get(); // Show all plans, including inactive
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'simulations_limit' => 'required|integer|min:-1', // -1 or 0 could denote unlimited logic, but usually 0 in this app
            'essays_limit' => 'required|integer|min:0',
            'max_ai_questions' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Auto-generate slug from name, ensuring uniqueness
        $data['slug'] = Str::slug($data['name']);
        if (Plan::where('slug', $data['slug'])->exists()) {
             $data['slug'] .= '-' . uniqid();
        }

        Plan::create($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plano criado com sucesso!');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'simulations_limit' => 'required|integer|min:0',
            'essays_limit' => 'required|integer|min:0',
            'max_ai_questions' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // We do NOT update slug to avoid breaking code references
        // We do NOT update price for existing subscriptions (Asaas logic handles this safely as analyzed)

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plano atualizado com sucesso!');
    }
}
