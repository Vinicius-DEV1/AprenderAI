<?php

namespace App\Services;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDaily;
use App\Models\AnalyticsPage;
use App\Models\AnalyticsDevice;
use App\Models\AnalyticsHourly;
use App\Notifications\AnalyticsAlertNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

class AnalyticsInsightService
{
    /**
     * Analisa as métricas de ontem vs o dia anterior e gera alertas se necessário.
     */
    public function analyzeDailyMetrics(AnalyticsDaily $today, AnalyticsDaily $yesterday)
    {
        // 1. Queda Abrupta de Sessões (> 30%)
        if ($yesterday->sessions > 0) {
            $dropPercentage = (($yesterday->sessions - $today->sessions) / $yesterday->sessions) * 100;

            if ($dropPercentage > 30) {
                $this->createAlert(
                    'drop',
                    "Alerta de Queda de Tráfego: As sessões caíram " . round($dropPercentage, 1) . "% em relação ao dia anterior.",
                    ['today_sessions' => $today->sessions, 'yesterday_sessions' => $yesterday->sessions]
                );
            }

            // 2. Crescimento Anormal (> 50%)
            $growthPercentage = (($today->sessions - $yesterday->sessions) / $yesterday->sessions) * 100;
            if ($growthPercentage > 50) {
                $this->createAlert(
                    'growth',
                    "Pico de Tráfego Detectado! As sessões cresceram " . round($growthPercentage, 1) . "% em relação ao dia anterior.",
                    ['today_sessions' => $today->sessions, 'yesterday_sessions' => $yesterday->sessions]
                );
            }
        }
    }

    /**
     * Gera insights textuais sobre o comportamento (horários de pico, dispositivos, páginas).
     */
    public function generateGeneralInsights(): array
    {
        $insights = [];

        // 1. Horário de Pico (Últimos 7 dias)
        $peakHour = AnalyticsHourly::selectRaw('hour, SUM(sessions) as total_sessions')
            ->where('date', '>=', now()->subDays(7))
            ->groupBy('hour')
            ->orderByDesc('total_sessions')
            ->first();

        if ($peakHour) {
            $insights[] = "Seu pico de tráfego ocorre às {$peakHour->hour}h.";
        }

        // 2. Dispositivo Dominante (Últimos 7 dias)
        $devices = AnalyticsDevice::selectRaw('device_category, SUM(sessions) as total_sessions')
            ->where('date', '>=', now()->subDays(7))
            ->groupBy('device_category')
            ->get();

        $totalSessions = $devices->sum('total_sessions');
        $mobileSessions = $devices->where('device_category', 'Mobile')->first()->total_sessions ?? 0;

        if ($totalSessions > 0 && $mobileSessions > 0) {
            $mobilePercentage = ($mobileSessions / $totalSessions) * 100;
            if ($mobilePercentage > 50) {
                $insights[] = "Mobile representa " . round($mobilePercentage, 1) . "% do tráfego.";
            }
        }

        // 3. Página com Alta Taxa de Saída
        $worstPage = AnalyticsPage::where('date', '>=', now()->subDays(7))
            ->where('views', '>', 50) // Mínimo de significância
            ->orderByDesc('exit_rate')
            ->first();

        if ($worstPage && $worstPage->exit_rate > 70) {
            $insights[] = "ATENÇÃO: A página '{$worstPage->page_path}' tem altíssima taxa de saída (" . round($worstPage->exit_rate, 1) . "%).";
        }

        return $insights;
    }

    /**
     * Obtém os alertas mais recentes que não foram lidos.
     */
    public function getUnreadAlerts()
    {
        return AnalyticsAlert::whereNull('read_at')->orderByDesc('created_at')->get();
    }

    /**
     * Cria e dispara notificação no sistema
     */
    protected function createAlert(string $type, string $message, array $details = [])
    {
        $alert = AnalyticsAlert::create([
            'type' => $type,
            'message' => $message,
            'details' => $details,
        ]);

        // Dispara Laravel Notification para os Administradores
        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0 && class_exists(AnalyticsAlertNotification::class)) {
            Notification::send($admins, new AnalyticsAlertNotification($alert));
        }

        return $alert;
    }
}
