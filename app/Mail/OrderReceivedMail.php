<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        // Eager load relationships for rendering
        $this->order = $order->load(['company', 'user', 'items.product']);
        $this->settings = AppSetting::first();
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $appName = $this->settings->name ?? config('app.name');
        $subject = $appName . ' - Thank you for your order #' . str_pad($this->order->id, 6, '0', STR_PAD_LEFT);

        return $this->view('mail.order_received')
            ->subject($subject);
    }
}
