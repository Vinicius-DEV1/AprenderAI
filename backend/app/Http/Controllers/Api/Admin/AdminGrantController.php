<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminGrantController extends Controller
{
    protected PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    /**
     * Retorna lista de assinaturas concedidas manualmente
     */
    public function index()
    {
        $grants = Subscription::with(['user', 'plan', 'grantedBy'])
            ->grants()
            ->latest()
            ->paginate(50);

        return response()->json($grants);
    }

    /**
     * Concede um plano manualmente a um usuário
     */
    public function grant(Request $request, User $user)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'duration_type' => 'required|in:days,months',
            'duration_value' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:1000'
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        $subscription = $this->planService->grantPlanToUser(
            $user,
            $plan,
            Auth::id(),
            $request->duration_type,
            $request->duration_value,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => "Plano {$plan->name} concedido com sucesso para {$user->name}.",
            'subscription' => $subscription
        ]);
    }

    /**
     * Revoga um grant manual previamente estabelecido
     */
    public function revoke(User $user)
    {
        $grants = $user->subscriptions()
            ->where('is_manual_grant', true)
            ->where('status', 'active')
            ->get();

        if ($grants->isEmpty()) {
            return response()->json(['message' => 'Nenhuma assinatura manual ativa encontrada para este usuário.'], 404);
        }

        foreach ($grants as $grant) {
            $grant->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
        }

        // Se o usuário não possui assinatura paga ativa que já estendeu o plan_expires_at, 
        // e ele não tem admin bypass, vamos encurtar o tempo de expiração dele pro agora
        $hasActivePaid = $user->subscriptions()
            ->where('is_manual_grant', false)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->exists();

        if (!$hasActivePaid) {
            $user->update([
                'plan_expires_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Concessão de plano revogada com sucesso.'
        ]);
    }
}
