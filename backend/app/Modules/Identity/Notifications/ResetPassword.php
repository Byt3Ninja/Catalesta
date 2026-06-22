<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = (string) $notifiable->getEmailForPasswordReset();
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/auth/reset-password?token='.$this->token.'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('You requested a password reset. Click below to choose a new password.')
            ->action('Reset password', $url)
            ->line('This link expires in 60 minutes. If you did not request this, ignore this email.');
    }
}
