<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FrontendError;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErrorTrackingController extends Controller
{
    public function logError(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'type' => 'nullable|string',
            'url' => 'nullable|string',
            'stack_trace' => 'nullable|string',
            'user_action' => 'nullable|string',
            'additional_data' => 'nullable|array',
        ]);

        $user = $request->user('sanctum');

        $errorRecord = FrontendError::create([
            'user_id' => $user ? $user->id : null,
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'error',
            'url' => $validated['url'] ?? $request->header('referer'),
            'stack_trace' => $validated['stack_trace'] ?? null,
            'user_action' => $validated['user_action'] ?? null,
            'additional_data' => $validated['additional_data'] ?? null,
        ]);

        // Feature Toggle: Admin Notification Dispatch
        // Currently disabled as requested. Set to true to enable.
        $enableAdminNotification = false;

        if ($enableAdminNotification) {
            try {
                $userContext = $user ? "Usuário: {$user->name} ({$user->email})" : "Usuário: Anônimo";
                
                app(AdminNotificationService::class)->createNotification(
                    'Erro no Frontend (' . ($validated['type'] ?? 'error') . ')',
                    "Uma falha foi capturada no frontend.\n\n" .
                    "Mensagem: {$validated['message']}\n" .
                    "URL: " . ($validated['url'] ?? 'N/A') . "\n" .
                    "{$userContext}\n" .
                    "Action: " . ($validated['user_action'] ?? 'N/A'),
                    'FrontendError', // event type used for deduplication/grouping
                    $errorRecord->id,
                    \App\Models\AdminNotification::GRAVIDADE_MEDIO,
                    route('admin.dashboard') // or any relevant admin route
                );
            } catch (\Exception $e) {
                // Failsafe so the original endpoint still succeeds
                Log::error('Falha ao enviar notificação de erro do frontend', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['status' => 'logged']);
    }
}
