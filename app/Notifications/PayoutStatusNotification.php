<?php

namespace App\Notifications;

use App\Models\SellerPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SellerPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(translate('Payout completed'))
            ->greeting(translate('Hello').' '.$notifiable->name.',')
            ->line(translate('Your payout has been completed.'))
            ->line(translate('Settlement reference').': '.$this->payout->txn_code)
            ->action(translate('View payout history'), route('seller.payouts.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_completed',
            'payout_id' => $this->payout->id,
            'amount' => (string) $this->payout->paid_amount,
            'txn_code' => $this->payout->txn_code,
        ];
    }
}
