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
            if (! $event->message instanceof Email) {
                return;
            }

            $html = (string) $event->message->getHtmlBody();
            if ($html === '') {
                return;
            }

            if (! str_contains($html, 'data-aktienki-mail-header="standard"')) {
                $header = view('vendor.mail.html.header', [
                    'url' => config('app.url'),
                ])->render();
                $html = preg_match('/<body\b[^>]*>/i', $html)
                    ? preg_replace('/(<body\b[^>]*>)/i', '$1'.$header, $html, 1)
                    : $header.$html;
                $event->message->html($html);
            }

            if (! str_contains($html, 'cid:aktienki-logo.png')) {
                return;
            }

            $alreadyEmbedded = collect($event->message->getAttachments())
                ->contains(fn ($part): bool => method_exists($part, 'getName') && $part->getName() === 'aktienki-logo.png');
            if (! $alreadyEmbedded) {
                $logo = (new DataPart(new File(public_path('brand/generated/bull-logo-dark.png')), 'aktienki-logo.png', 'image/png'))->asInline();
                $event->message->addPart($logo);
            }
        });
    }
}
