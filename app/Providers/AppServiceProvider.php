<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

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
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $name = trim((string) (($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? '')));
            if ($name === '') {
                $name = 'there';
            }

            return (new MailMessage)
                ->subject('Verify your email')
                ->view('emails.verify-email', [
                    'name' => $name,
                    'url' => $url,
                    'email' => $notifiable->email ?? null,
                ]);
        });
    }
}
