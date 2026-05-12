<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        // Eager load necessary relationships to ensure fully populated context
        $this->order = $order->load(['company', 'user', 'items.product']);
        $this->settings = AppSetting::first();
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Generate the robust PDF buffer on-the-fly
        $pdf = Pdf::loadView('pdf.quotation', [
            'order' => $this->order,
            'settings' => $this->settings
        ]);
        
        $pdfBuffer = $pdf->output();
        $filename = 'Quotation_' . str_pad($this->order->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        $subject = ($this->settings->name ?? config('app.name')) . ' - Official Quotation #'. str_pad($this->order->id, 6, '0', STR_PAD_LEFT);

        return $this->view('mail.quotation')
            ->subject($subject)
            ->attachData($pdfBuffer, $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
