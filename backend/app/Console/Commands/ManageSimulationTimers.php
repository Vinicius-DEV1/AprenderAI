<?php

namespace App\Console\Commands;

use App\Models\Simulation;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageSimulationTimers extends Command
{
    protected $signature = 'simulations:manage-timers';
    protected $description = 'Manage simulation timers, auto-finish expired ones and send reminders.';

    public function handle()
    {
        $inProgress = Simulation::where('status', 'in_progress')->with('user')->get();
        
        foreach ($inProgress as $simulation) {
            $remaining = $simulation->getTimeRemaining();
            $lastActivity = $simulation->last_activity_at;
            $inactiveMinutes = $lastActivity ? now()->diffInMinutes($lastActivity) : 0;
            
            // 1. Auto-Finish if expired
            if ($remaining <= 0) {
                $simulation->finishSimulation();
                $this->sendNotification($simulation, 'time_expired', 'Seu tempo acabou! O simulado foi encerrado. Confira seu resultado.');
                Log::info("[Timer] Simulation #{$simulation->id} auto-finished (time expired).");
                continue;
            }

            // 2. Reminders based on remaining time
            $this->checkTimeReminders($simulation, $remaining);

            // 3. Inactivity reminder (if user left the page > 30 min)
            if ($inactiveMinutes >= 30 && !$this->wasNotified($simulation, 'inactivity_30m')) {
                $this->sendNotification($simulation, 'inactivity_30m', 'Parece que você saiu do simulado. Ele ainda está rodando, não esqueça de voltar!');
            }
        }

        return Command::SUCCESS;
    }

    protected function checkTimeReminders(Simulation $simulation, int $remainingSeconds)
    {
        $remainingMinutes = ceil($remainingSeconds / 60);

        // 1 Hour Reminder
        if ($remainingMinutes <= 60 && $remainingMinutes > 55 && !$this->wasNotified($simulation, 'reminder_1h')) {
            $this->sendNotification($simulation, 'reminder_1h', 'Falta apenas 1 hora para o fim do seu simulado.');
        }

        // 30 Minutes Reminder
        if ($remainingMinutes <= 30 && $remainingMinutes > 25 && !$this->wasNotified($simulation, 'reminder_30m')) {
            $this->sendNotification($simulation, 'reminder_30m', 'Atenção: seu simulado encerra em 30 minutos!');
        }
    }

    protected function wasNotified(Simulation $simulation, string $type): bool
    {
        $sent = $simulation->notifications_sent ?? [];
        return in_array($type, $sent);
    }

    protected function sendNotification(Simulation $simulation, string $type, string $message)
    {
        // Only notify if user is NOT currently active (last heartbeat > 2 minutes ago)
        // or if it's the final 'time_expired' notification
        $isUserActive = $simulation->last_activity_at && now()->diffInSeconds($simulation->last_activity_at) < 120;
        
        if ($isUserActive && $type !== 'time_expired') {
            return;
        }

        // Create in-platform notification
        Notification::create([
            'user_id' => $simulation->user_id,
            'type' => 'simulation_timer',
            'title' => 'Simulado',
            'message' => $message,
            'action_url' => "/simulados/{$simulation->id}" . ($type === 'time_expired' ? '/resultado' : ''),
            'is_read' => false
        ]);

        // Update tracking
        $sent = $simulation->notifications_sent ?? [];
        $sent[] = $type;
        $simulation->update(['notifications_sent' => $sent]);
    }
}
