<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\NewUserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendNewUserNotificationToAdmin implements ShouldQueue
{
    public function handle(UserRegistered $UserRegistered): void
    {
        Mail::to('admin@digitalforte.us')->send(
            new NewUserRegistered($UserRegistered->user)
        );
    }
}
