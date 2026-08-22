<?php

use App\Modules\Organizations\Connections\ConnectionDestroyController;
use App\Modules\Organizations\Connections\ConnectionDisableController;
use App\Modules\Organizations\Connections\ConnectionEnableController;
use App\Modules\Organizations\Connections\ConnectionStoreController;
use App\Modules\Organizations\Connections\ConnectionUpdateController;
use App\Modules\Organizations\Connections\ConnectionVerifyController;
use App\Modules\Organizations\Invitations\InvitationController;
use App\Modules\Organizations\Invitations\InvitationDestroyController;
use App\Modules\Organizations\Members\MemberDestroyController;
use App\Modules\Organizations\Members\MemberUpdateController;
use App\Routes\OrganizationRoute;
use Illuminate\Support\Facades\Route;

Route::post(OrganizationRoute::connections->value, ConnectionStoreController::class);
Route::post(OrganizationRoute::connectionEnabled->value, ConnectionEnableController::class);
Route::delete(OrganizationRoute::connectionEnabled->value, ConnectionDisableController::class);
Route::post(OrganizationRoute::connectionVerify->value, ConnectionVerifyController::class);
Route::post(OrganizationRoute::connectionManage->value, ConnectionUpdateController::class);
Route::delete(OrganizationRoute::connectionManage->value, ConnectionDestroyController::class);
Route::post(OrganizationRoute::invitations->value, InvitationController::class);
Route::delete(OrganizationRoute::invitation->value, InvitationDestroyController::class);
Route::post(OrganizationRoute::member->value, MemberUpdateController::class);
Route::delete(OrganizationRoute::member->value, MemberDestroyController::class);
