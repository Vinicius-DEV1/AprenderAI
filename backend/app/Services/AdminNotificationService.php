<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AdminNotificationService
 *
 * Provides a centralized way to alert administrators of critical system events,
 * exceptions, and operational failures.
 *
 * Implements deduplication using Cache to avoid spamming the same error
 * across multiple admin notifications, ensuring the notification bell is actionable.
 */
class AdminNotificationService
{
    public const SEVERITY_GRAVE = 'grave';
    public const SEVERITY_MEDIO = 'médio';
    public const SEVERITY_LEVE  = 'leve';

    /**
     * General notification method.
     *
     * @param string $severity      grave, médio, leve
     * @param string $title         Short distinctive title
     * @param string $body          Detailed message/context
     * @param string $dedupKey      Unique string to identify this exact event (e.g., Exception class + line)
     * @param int    $dedupMinutes  How long to suppress identical notifications (default 60 mins)
     * @param string|null $actionUrl Optional deep link to admin panel
     */
    public function notify(
        string $severity,
        string $title,
        string $body,
        string $dedupKey,
        int $dedupMinutes = 60,
        ?string $actionUrl = null
    ): void {
        try {
            $cacheKey = "admin_alert_" . md5($dedupKey);

            if (Cache::has($cacheKey)) {
                // Deduplicated: identical alert was sent recently.
                return;
            }

            // Lock this alert for $dedupMinutes
            Cache::put($cacheKey, true, now()->addMinutes($dedupMinutes));

            // Map custom severities to internal visual types of UserNotification
            $visualType = match ($severity) {
                self::SEVERITY_GRAVE => 'warning',  // Frontend will handle 'warning' (or grave in meta)
                self::SEVERITY_MEDIO => 'info',     // Standard info/orange depending on UI
                self::SEVERITY_LEVE  => 'tip',      // Routine
                default => 'info',
            };

            // Get all admins
            $adminIds = User::where('role', 'admin')->pluck('id');

            if ($adminIds->isEmpty()) {
                Log::warning('[AdminNotificationService] No admins found to notify.');
                return;
            }

            $now = now();
            $notifications = [];

            foreach ($adminIds as $adminId) {
                $notifications[] = [
                    'user_id'    => $adminId,
                    'title'      => $title,
                    'body'       => substr($body, 0, 1000), // Max 1000 chars
                    'type'       => $visualType,
                    'action_url' => $actionUrl,
                    // Using meta to pass explicit severity to the frontend
                    'meta'       => json_encode(['severity' => $severity]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            UserNotification::insert($notifications);

            Log::info("[AdminNotificationService] Sent {$severity} alert: {$title}");

        } catch (\Throwable $e) {
            // Failsafe: if the notification system itself fails, log it but don't crash.
            Log::error('[AdminNotificationService] Failed to send notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify about critical system-wide unhandled exceptions (500 errors).
     */
    public function notifyException(\Throwable $exception): void
    {
        $class = get_class($exception);
        $file  = basename($exception->getFile());
        $line  = $exception->getLine();
        
        $title = "🚨 Erro Crítico do Sistema 500";
        $body  = "{$class} em {$file}:{$line}\n" . $exception->getMessage();
        
        // Specific deduplication key: combine class, file, line. Prevents same error
        // throwing 100 notifications in a row, but allows different errors.
        $dedupKey = "ex_{$class}_{$file}_{$line}";

        // Send 'grave', lock for 30 minutes.
        $this->notify(self::SEVERITY_GRAVE, $title, $body, $dedupKey, 30, '/admin/monitor/logs');
    }

    /**
     * Notify about a failing job in the queue framework.
     */
    public function notifyJobFailure(string $jobName, \Throwable $exception): void
    {
        $shortJob = class_basename($jobName);
        $title = "⚠️ Falha em Rotina/Job: {$shortJob}";
        $body  = "O job {$shortJob} falhou.\nErro: " . $exception->getMessage();
        
        $dedupKey = "job_{$shortJob}";

        // Jobs failing are usually "Medio", but if it's a batch it might need checking.
        $this->notify(self::SEVERITY_MEDIO, $title, $body, $dedupKey, 60, '/admin/monitor/failed-jobs');
    }

    /**
     * Notify about critical AI failures (Offline, Quota Exceeded).
     */
    public function notifyAiError(string $provider, string $model, string $reason, string $errorDetail): void
    {
        $title = "🤖 Alerta de IA: {$provider} ({$reason})";
        $body  = "Modelo afetado: {$model}.\nDetalhes: {$errorDetail}";
        
        $dedupKey = "ai_{$provider}_{$reason}";

        // AI Quota limits block core functionality -> Grave
        $this->notify(self::SEVERITY_GRAVE, $title, $body, $dedupKey, 60, '/admin/api-keys');
    }

    /**
     * Webhook/Integration payment failures.
     */
    public function notifyPaymentError(string $gateway, string $reason, string $detail): void
    {
        $title = "💸 Erro de Pagamento/Gateway: {$gateway}";
        $body  = "Falha crítica: {$reason}.\nDetalhes: {$detail}";
        
        $dedupKey = "pay_{$gateway}_{$reason}";

        // Blocks revenue -> Grave
        $this->notify(self::SEVERITY_GRAVE, $title, $body, $dedupKey, 120, '/admin/checkout/errors');
    }
}
