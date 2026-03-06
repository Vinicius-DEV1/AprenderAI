<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Mail\SubscriptionExpiringMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotifyExpiringSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:notify-expiring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users about monthly plans expiring today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();

        $expiringSubscriptions = Subscription::with(['user', 'plan'])
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', $today)
            ->get();

        $this->info("Encontradas " . $expiringSubscriptions->count() . " assinaturas vencendo hoje.");

        foreach ($expiringSubscriptions as $subscription) {
            $user = $subscription->user;
            $plan = $subscription->plan;

            // Only notify for monthly plans as per requirement
            if ($plan && $plan->interval === 'monthly') {
                try {
                    Mail::to($user->email)->send(new SubscriptionExpiringMail($user, $plan));
                    $this->info("Email enviado para: {$user->email}");
                    Log::info("[Scheduler] Aviso de vencimento enviado", ['user_id' => $user->id, 'plan_id' => $plan->id]);
                } catch (\Exception $e) {
                    $this->error("Falha ao enviar para {$user->email}: " . $e->getMessage());
                    Log::error("[Scheduler] Erro ao enviar aviso de vencimento", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return Command::SUCCESS;
    }
}
