<?php

namespace App\Mail;

use App\Models\Simulation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SimulationCorrectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $simulation;

    public function __construct(Simulation $simulation)
    {
        $this->simulation = $simulation;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu Simulado foi Corrigido - AprovaAI',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.simulation_corrected',
        );
    }
}
