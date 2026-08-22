<?php

use App\Modules\Organizations\Connections\OrganizationConnectionController;
use App\Modules\Organizations\Invitations\InvitationController;
use App\Modules\Organizations\Invitations\InvitationDestroyController;
use App\Modules\Organizations\Members\MemberDestroyController;
use App\Modules\Organizations\Members\MemberUpdateController;
use App\Routes\OrganizationRoute;
use Illuminate\Support\Facades\Route;

Route::post(OrganizationRoute::connectionToggle->value, OrganizationConnectionController::class);
Route::post(OrganizationRoute::invitations->value, InvitationController::class);
Route::delete(OrganizationRoute::invitation->value, InvitationDestroyController::class);
Route::post(OrganizationRoute::member->value, MemberUpdateController::class);
Route::delete(OrganizationRoute::member->value, MemberDestroyController::class);
