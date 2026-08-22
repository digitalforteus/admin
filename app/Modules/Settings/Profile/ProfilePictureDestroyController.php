<?php

namespace App\Modules\Settings\Profile;

use App\Helpers\ProfilePicture;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProfilePictureDestroyController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        ProfilePicture::of(User::authenticated($Request))->clear();

        return back()->with('status', 'Profile picture removed.');
    }
}
