<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly bool $passwordResetRequired) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(translate('Your seller account is ready'))
            ->greeting(translate('Welcome to Elite Marketplace'))
            ->line(translate('An administrator created a seller account for you.'));

        if ($this->passwordResetRequired) {
            $message->line(translate('A separate secure password setup link has been sent to this address.'));
        }

        return $message
            ->action(translate('Open seller login'), route('login'))
            ->line(translate('Your shop may remain restricted until approval and verification are complete.'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'seller_invitation',
            'message' => translate('Your seller account has been created.'),
            'route' => route('seller.dashboard'),
        ];
    }
}
