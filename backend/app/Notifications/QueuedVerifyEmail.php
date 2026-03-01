<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    // Leveraging the parent VerifyEmail behavior for generating the mail message
    // and URL. By implementing ShouldQueue, Laravel will automatically dispatch
    // this notification to the background worker queue.

    /**
     * Get the verification URL for the given notifiable.
     * Overridden to point to 'verification.verify' instead of the default Laravel routing 
     * since our route api.php defines it without the `api.` name prefix here.
     */
    protected function verificationUrl($notifiable)
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.verification.verify',
            \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
