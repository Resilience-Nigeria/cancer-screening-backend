<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverdueResultsReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $recipientName, public array $data, public string $link)
    {
    }

    public function envelope(): Envelope
    {
        $n = $this->data['total'];
        return new Envelope(
            subject: "NCSR reminder: {$n} test result" . ($n === 1 ? '' : 's') . " overdue (more than {$this->data['days']} days)",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.overdue-results');
    }
}
