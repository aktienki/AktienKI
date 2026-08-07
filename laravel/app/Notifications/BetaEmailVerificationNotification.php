<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Crypt;

class BetaEmailVerificationNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $message = parent::toMail($notifiable);
        $encryptedCode = data_get($notifiable->meta, 'beta_registration.code_encrypted');
        $code = $encryptedCode ? Crypt::decryptString($encryptedCode) : __('wird nach der Bestätigung angezeigt');

        return $message
            ->subject(__('E-Mail bestätigen und AktienKI-Beta freischalten'))
            ->line(__('Dein persönlicher Beta-Freischaltcode:'))
            ->line('**'.(string) $code.'**')
            ->line(__('Bewahre diesen Code auf. Nach der E-Mail-Bestätigung gibst du ihn einmalig in AktienKI ein.'));
    }
}
