<?php

namespace App\Modules\Settings\Profile;

use App\Helpers\Disk;
use App\Helpers\ProfilePicture;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

readonly class ProfilePictureController
{
    public function __invoke(Request $Request): RedirectResponse
    {
        if (! Disk::retains()) {
            return back()->withErrors([
                ProfilePictureRequest::picture => 'Uploading a profile picture needs a storage service that keeps it.',
            ]);
        }

        $ProfilePictureRequest = ProfilePictureRequest::from($Request->all());
        $Validator = Validator::make(...$ProfilePictureRequest->validator());

        if ($Validator->fails()) {
            return back()->withErrors($Validator);
        }

        ProfilePicture::of(User::authenticated($Request))->put($ProfilePictureRequest->picture);

        return back()->with('status', 'Profile picture updated.');
    }
}
