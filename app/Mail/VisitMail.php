<?php

namespace App\Mail;

use App\Models\Visit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VisitMail extends Mailable
{
    public function __construct(public Visit $visit, public string $headline, public string $line) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->visit->code}] {$this->headline}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.visit');
    }
}
