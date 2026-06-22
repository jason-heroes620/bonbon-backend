<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenderSelectedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $details;

    public function __construct(array $details)
    {
        $this->details = $details;
    }

    public function build()
    {
        return $this
            ->subject('BonBon - Your Bid Is Confirmed')
            ->view('emails.tender-selected')
            ->with([
                'details' => $this->details,
            ]);
    }
}
