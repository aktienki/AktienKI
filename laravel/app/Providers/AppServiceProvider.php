<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if (! $event->message instanceof Email || ! str_contains((string) $event->message->getHtmlBody(), 'cid:aktienki-logo@aktienki.com')) {
                return;
            }

            $alreadyEmbedded = collect($event->message->getAttachments())
                ->contains(fn ($part): bool => method_exists($part, 'getContentId') && $part->getContentId() === 'aktienki-logo@aktienki.com');
            if (! $alreadyEmbedded) {
                $logo = (new DataPart(new File(public_path('brand/generated/bull-logo-light-clean.png')), 'aktienki-logo.png', 'image/png'))->asInline();
                $logo->setContentId('aktienki-logo@aktienki.com');
                $event->message->addPart($logo);
            }
        });
    }
}
