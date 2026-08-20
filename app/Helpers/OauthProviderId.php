<?php

namespace App\Helpers;

/** Stores the OAuth provider IDs used in the application. */
enum OauthProviderId: string
{
    case google = 'google';
    case github = 'github';
}
