<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefina sua senha | Negozia')
            ->view(
                'emails.reset-password',
                [
                    'user' => $notifiable,

                    'resetUrl' =>
                        $this->resetUrl($notifiable),

                    'expiresIn' =>
                        (int) config(
                            'auth.passwords.'
                            . config('auth.defaults.passwords')
                            . '.expire'
                        ),
                ]
            );
    }
}
