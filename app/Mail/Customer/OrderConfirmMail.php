<?php

declare(strict_types=1);

namespace App\Mail\Customer;

use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Order $order,
        protected ?string $pdfPath = null,
        protected ?string $pdfData = null,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Order Invoice #'.$this->order->invoice_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.invoice',
            with: [
                'order' => $this->order,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $fileName = 'invoice_'.$this->order->invoice_number.'.pdf';

        if ($this->pdfData !== null) {
            return [
                Attachment::fromData(fn () => $this->pdfData, $fileName)
                    ->withMime('application/pdf'),
            ];
        }

        if ($this->pdfPath !== null && file_exists($this->pdfPath)) {
            return [
                Attachment::fromPath($this->pdfPath)
                    ->as($fileName)
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
