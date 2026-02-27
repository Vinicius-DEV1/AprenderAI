<?php

namespace App\Mail;

use App\Models\Essay;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EssayCorrectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $essay;

    public function __construct(Essay $essay)
    {
        $this->essay = $essay;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sua Redação foi Corrigida - AprovaAI',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.essay_corrected',
        );
    }
}
