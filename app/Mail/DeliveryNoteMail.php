<?php

namespace App\Mail;

use App\Models\Shipment;
use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class DeliveryNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $shipment;
    public $order;
    public $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(Shipment $shipment)
    {
        // Eager load necessary relationships to ensure fully populated context
        $this->shipment = $shipment->load(['order.user', 'order.company', 'carrier', 'items.orderItem.product']);
        $this->order = $this->shipment->order;
        $this->settings = AppSetting::first();
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Generate the robust PDF buffer on-the-fly
        $pdf = Pdf::loadView('pdf.delivery_note', [
            'shipment' => $this->shipment,
            'order' => $this->order,
            'settings' => $this->settings
        ]);
        
        $pdfBuffer = $pdf->output();
        $filename = 'DeliveryNote_' . str_pad($this->shipment->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        $subject = ($this->settings->name ?? config('app.name')) . ' - Delivery Dispatch Note #DN-' . str_pad($this->shipment->id, 6, '0', STR_PAD_LEFT);

        return $this->view('mail.delivery_note')
            ->subject($subject)
            ->attachData($pdfBuffer, $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
