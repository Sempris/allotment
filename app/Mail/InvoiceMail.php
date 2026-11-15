<?php

namespace App\Mail;

use App\Models\Plot;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    public function __construct(
        public Plot $plot,
        public int $year,
        public int $totalCents,
        private readonly string $pdf,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('club.email'), config('club.name'))],
            subject: "Jahresabrechnung {$this->year} – Parzelle {$this->plot->number}",
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invoice', with: ['club' => config('club')]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
