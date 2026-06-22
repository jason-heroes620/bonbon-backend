<?php

namespace App\Jobs;

use App\Mail\TenderSelectedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTenderSelectedNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public array $details;

    public function __construct(string $email, array $details)
    {
        $this->email = $email;
        $this->details = $details;
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new TenderSelectedNotification($this->details)
        );
    }
}
