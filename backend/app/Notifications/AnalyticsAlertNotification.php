<?php

namespace App\Notifications;

use App\Models\AnalyticsAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnalyticsAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected AnalyticsAlert $alert;

    /**
     * Create a new notification instance.
     */
    public function __construct(AnalyticsAlert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Apenas via banco de dados para listar no painel
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Se desejar disparar também e-mail, pode ativar posteriormente.
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'type' => $this->alert->type,
            'message' => $this->alert->message,
            'details' => $this->alert->details,
            'url' => route('admin.analytics.alerts', ['highlight' => $this->alert->id]), // Rota futura para listar alertas
        ];
    }
}
