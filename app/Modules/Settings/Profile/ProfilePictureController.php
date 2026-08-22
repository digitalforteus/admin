<?php

namespace App\Modules\Settings\Profile;

use App\Helpers\ProfilePicture;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProfilePictureController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        $ProfilePictureRequest = ProfilePictureRequest::from($Request->all());
        $Validator = Validator::make(...$ProfilePictureRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        ProfilePicture::put(User::authenticated($Request), $ProfilePictureRequest->picture);

        return back()->with('status', 'Profile picture updated.');
    }
}
