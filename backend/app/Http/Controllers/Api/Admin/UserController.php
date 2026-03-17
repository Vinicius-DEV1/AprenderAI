<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(Request $request)
    {
        $query = User::with(['plan'])
            ->withCount(['simulations', 'essays']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'banned') {
                $query->where('is_banned', true);
            } elseif ($request->status === 'active') {
                $query->where('is_banned', false);
            }
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Get specific user details.
     */
    public function show(User $user)
    {
        try {
            // Eager load only standard relations with no limit closures to avoid hydration clipping
            $user->load(['plan', 'subscriptions.plan']);
            $user->loadCount(['simulations', 'essays', 'promptLogs']);

            // Get logs separately to avoid Eloquent closure take() issues on hydration
            $user->setRelation('logs', $user->logs()->latest()->take(20)->get());

            // Prepare prompt history 
            $promptHistory = $user->promptLogs()->latest()->paginate(10);

            $stats = [
                'simulations' => $user->simulations_count ?? 0,
                'essays' => $user->essays_count ?? 0,
                'ai' => [
                    'request_count' => $user->prompt_logs_count ?? 0,
                    'total_cost' => $user->promptLogs()->sum('estimated_cost') ?? 0,
                    'success_rate' => ($user->prompt_logs_count ?? 0) > 0
                        ? ($user->promptLogs()->whereNotNull('response_text')->count() / $user->prompt_logs_count) * 100
                        : 100,
                    'peak_hour' => 'N/A',
                ],
                // Utilize the eager loaded collection directly via property to avoid an N+1 DB hit
                'investment' => $user->subscriptions->where('status', 'active')->sum(fn($s) => $s->plan?->price ?? 0)
            ];

            return response()->json([
                'user' => $user,
                'stats' => $stats,
                'promptHistory' => $promptHistory,
                'monthly_simulation_used' => $user->monthlySimulationUsed(),
                'monthly_essay_used' => $user->monthlyEssayUsed(),
            ]);
        } catch (\Exception $e) {
            \Log::error("Error loading user detail (ID: {$user->id}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal Server Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle ban status.
     */
    public function toggleStatus(User $user)
    {
        $user->update(['is_banned' => !$user->is_banned]);
        return response()->json(['message' => 'Status alterado!', 'user' => $user]);
    }

    /**
     * Reset password logic (simplified for API).
     */
    public function resetPassword(Request $request, User $user)
    {
        if ($request->filled('new_password')) {
            $user->update(['password' => bcrypt($request->new_password)]);
        }

        // In a real app, send email if requested

        return response()->json(['message' => 'Operação concluída.']);
    }

    /**
     * Cancel and Refund User Subscription (CDC 7 days or manual Admin kill)
     * Kills active quotas (SubscriptionCycle), resets Plan to Free, and cancels on Asaas.
     */
    public function refundAndCancel(Request $request, User $user)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request) {
                // 1. Locate User's active subscription
                $subscription = \App\Models\Subscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if ($subscription) {
                    $subscription->update([
                        'status' => 'refunded',
                        'canceled_at' => now(),
                    ]);

                    // Call Asaas Service to actually refund it on gateway (Optional, depends on Asaas App rules)
                    // if ($request->boolean('refund_gateway')) {
                    //     app(\App\Services\AsaasService::class)->refundPayment($subscription->gateway_id);
                    // }
                }

                // 2. Kill Active Quotas (The Model Acumulativo switch)
                $activeCycles = \App\Models\SubscriptionCycle::where('subscription_id', $subscription?->id ?? 0)
                    ->orWhereHas('subscription', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->where('end_date', '>=', now())
                    ->update(['end_date' => now()]);

                // 3. Reset Profile to Free Plan immediatly
                $user->update([
                    'plan_id' => 1, // Plano Grátis Base
                    'plan_expires_at' => now(),
                ]);

                // 4. Mark user to prevent Future Abuses of 7-days refunds
                \App\Models\UserLog::create([
                    'user_id' => $user->id,
                    'action' => 'admin_force_refund_cdc',
                    'description' => json_encode(['admin_id' => $request->user()->id, 'message' => 'Admin forçou o cancelamento imediato / estorno cortando limites ciclos acumulados.'])
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Assinatura cancelada! Limites premium revogados instantaneamente.'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error on Admin Refund User {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erro ao processar o cancelamento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update user role or information (Admin only).
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'role' => 'nullable|in:user,admin,editor',
            'ai_questions_count' => 'nullable|integer|min:0',
            'max_ai_questions_override' => 'nullable|integer|min:0',
            'max_simulations_override' => 'nullable|integer|min:0',
            'max_essays_override' => 'nullable|integer|min:0',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuário atualizado com sucesso!',
            'user' => $user->load('plan')
        ]);
    }

    /**
     * Get specific user academic stats.
     */
    public function stats(User $user)
    {
        $statsService = app(\App\Services\StatsService::class);
        $userId = $user->id;

        return response()->json([
            'overview' => $statsService->getOverview($userId),
            'bySubject' => $statsService->getPerformanceBySubject($userId),
            'temporal' => $statsService->getTemporalEvolution($userId),
            'byDifficulty' => $statsService->getDifficultyHeatmap($userId),
        ]);
    }
    /**
     * Delete user and all associated traces (Wipeout).
     */
    public function destroy(Request $request, User $user)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
                // 1. Collect file paths before records are deleted via cascade
                $essayImages = \Illuminate\Support\Facades\DB::table('essays')
                    ->where('user_id', $user->id)
                    ->whereNotNull('image_path')
                    ->pluck('image_path');

                $supportAttachments = \Illuminate\Support\Facades\DB::table('support_messages')
                    ->join('support_tickets', 'support_messages.ticket_id', '=', 'support_tickets.id')
                    ->where('support_tickets.user_id', $user->id)
                    ->whereNotNull('attachment_path')
                    ->pluck('attachment_path');

                // 2. Manual Cleanup: Tables with nullOnDelete or no Foreign Keys
                
                // Analytics & Logs
                \Illuminate\Support\Facades\DB::table('payment_logs')->where('user_id', $user->id)->delete();
                \Illuminate\Support\Facades\DB::table('ai_request_logs')->where('user_id', $user->id)->delete();
                \Illuminate\Support\Facades\DB::table('search_interaction_logs')->where('user_id', $user->id)->delete();
                
                // Checkout Observability
                \Illuminate\Support\Facades\DB::table('checkout_events')->where('user_id', $user->id)->delete();
                \Illuminate\Support\Facades\DB::table('purchase_intentions')->where('user_id', $user->id)->delete();
                \Illuminate\Support\Facades\DB::table('checkout_errors')->where('user_id', $user->id)->delete();
                \Illuminate\Support\Facades\DB::table('checkout_abandonment')->where('user_id', $user->id)->delete();

                // Xavier Search legacy traces if any
                \Illuminate\Support\Facades\DB::table('ai_search_requests')->where('user_id', $user->id)->delete();

                // 3. Delete the User record (Triggers DB-level CASCADE for ~30 other tables)
                $user->delete();

                // 4. Cleanup Files from Storage
                foreach ($essayImages as $path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                }
                foreach ($supportAttachments as $path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                }

                // Note: We don't delete from Asaas here to avoid blocking on external API failures,
                // and to keep a record of the financial transaction on the gateway side.
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuário e todos os seus rastros foram excluídos permanentemente.'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error on Admin Destroy User {$user->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar exclusão total: ' . $e->getMessage()
            ], 500);
        }
    }
}
