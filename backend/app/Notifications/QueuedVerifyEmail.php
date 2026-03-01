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
}
