<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Mail\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('gmail', fn () => new GmailApiTransport(
            (string) config('services.gmail.client_id'),
            (string) config('services.gmail.client_secret'),
            (string) config('services.gmail.refresh_token'),
        ));
    }
}
