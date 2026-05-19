<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PoSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load(['company', 'user', 'items.product']);
        $this->settings = AppSetting::first();
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $appName = $this->settings->name ?? config('app.name');
        $subject = $appName . ' - Purchase Order Submitted for Order #' . str_pad($this->order->id, 6, '0', STR_PAD_LEFT);

        $mail = $this->view('mail.po_submitted')
            ->subject($subject);

        // Attach the uploaded PO document if it exists
        if ($this->order->po_attachment) {
            // Convert /storage/... path back to public disk path or relative storage path
            $relativePath = str_replace('/storage/', '', $this->order->po_attachment);
            if (Storage::disk('public')->exists($relativePath)) {
                $filename = basename($this->order->po_attachment);
                $mail->attachFromStorageDisk('public', $relativePath, $filename, [
                    'mime' => Storage::disk('public')->mimeType($relativePath) ?? 'application/octet-stream',
                ]);
            }
        }

        return $mail;
    }
}
