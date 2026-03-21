<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SemanticSearchErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $prompt;
    protected string $error;
    protected ?string $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $prompt, string $error, ?string $userName = null)
    {
        $this->prompt = $prompt;
        $this->error = $error;
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'semantic_search_error',
            'title' => 'Erro na Busca Semântica (Xavier)',
            'message' => "A busca \"{$this->prompt}\" falhou.",
            'error' => $this->error,
            'user' => $this->userName ?? 'System/Guest',
            'prompt' => $this->prompt,
            'action_url' => '/admin/semantic-dashboard',
        ];
    }
}
