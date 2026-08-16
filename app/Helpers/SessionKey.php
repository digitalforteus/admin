<?php

namespace App\Helpers;

/** Stores the session keys used in the application. */
enum SessionKey: string
{
    case sign_up_method = 'sign_up_method';
    case user_picture = 'user_picture';
}
