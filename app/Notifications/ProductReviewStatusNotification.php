<?php

namespace App\Notifications;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductReviewStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Product $product,
        private readonly ProductReviewStatus $status,
        private readonly ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(translate('Product review update'))
            ->greeting(translate('Hello').' '.$notifiable->name.',')
            ->line(
                translate('The review status for').' '.$this->product->name.' '.
                translate('is now').' '.str_replace('_', ' ', $this->status->value).'.'
            );

        if ($this->reason) {
            $message->line(translate('Reason').': '.$this->reason);
        }

        return $message->action(translate('View product'), route('seller.products.edit', [
            'id' => $this->product->id,
            'lang' => config('app.locale'),
        ]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_review_status',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'status' => $this->status->value,
            'reason' => $this->reason,
        ];
    }
}
