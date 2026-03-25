<?php

namespace App\Jobs;

use App\Mail\FoundingMemberQueued;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendFoundingMemberQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $email;
    public string $name;
    public string $privateLaunchDate;

    public function __construct(string $email, string $name, string $privateLaunchDate)
    {
        $this->email = $email;
        $this->name = $name;
        $this->privateLaunchDate = $privateLaunchDate;
    }

    public function handle(): void
    {
        Mail::to(
            $this->email
        )
            ->bcc('founding@bonbon.com.my')
            ->send(
                new FoundingMemberQueued($this->name, $this->privateLaunchDate, $this->email)
            );
    }
}
