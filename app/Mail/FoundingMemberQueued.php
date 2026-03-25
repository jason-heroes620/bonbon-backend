<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FoundingMemberQueued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $name;
    public string $privateLaunchDate;
    public string $registeredEmail;

    public function __construct(string $name, string $privateLaunchDate, string $registeredEmail)
    {
        $this->name = $name;
        $this->privateLaunchDate = $privateLaunchDate;
        $this->registeredEmail = $registeredEmail;
    }

    public function build()
    {
        return $this
            ->subject('You’re in the BonBon founding member queue')
            ->view('emails.founding-queue')
            ->with([
                'name' => $this->name,
                'privateLaunchDate' => $this->privateLaunchDate,
                'registeredEmail' => $this->registeredEmail,
            ]);
    }
}
