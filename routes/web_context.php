<?php

use App\Modules\Enterprises\EnterpriseController;
use App\Modules\Enterprises\EnterpriseUpdateController;
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
use App\Modules\Organizations\Organizations\OrganizationController;
use App\Modules\Organizations\Organizations\OrganizationDestroyController;
use App\Modules\Organizations\Organizations\OrganizationIconController;
use App\Modules\Organizations\Organizations\OrganizationIconDestroyController;
use App\Modules\Organizations\Organizations\OrganizationUpdateController;
use App\Modules\Projects\ProjectController;
use App\Modules\Projects\ProjectDestroyController;
use App\Modules\Projects\ProjectIconController;
use App\Modules\Projects\ProjectIconDestroyController;
use App\Modules\Projects\ProjectUpdateController;
use App\Routes\ContextRoute;
use Illuminate\Support\Facades\Route;

Route::post(ContextRoute::enterpriseIndex->value, EnterpriseController::class);
Route::post(ContextRoute::enterpriseSettings->value, EnterpriseUpdateController::class);

Route::post(ContextRoute::organizationIndex->value, OrganizationController::class);
Route::post(ContextRoute::organizationSettings->value, OrganizationUpdateController::class);
Route::delete(ContextRoute::organizationSettings->value, OrganizationDestroyController::class);
Route::post(ContextRoute::organizationIcon->value, OrganizationIconController::class);
Route::delete(ContextRoute::organizationIcon->value, OrganizationIconDestroyController::class);
Route::post(ContextRoute::invitations->value, InvitationController::class);
Route::delete(ContextRoute::invitation->value, InvitationDestroyController::class);
Route::post(ContextRoute::member->value, MemberUpdateController::class);
Route::delete(ContextRoute::member->value, MemberDestroyController::class);

Route::post(ContextRoute::projectIndex->value, ProjectController::class);
Route::post(ContextRoute::projectSettings->value, ProjectUpdateController::class);
Route::delete(ContextRoute::projectSettings->value, ProjectDestroyController::class);
Route::post(ContextRoute::projectIcon->value, ProjectIconController::class);
Route::delete(ContextRoute::projectIcon->value, ProjectIconDestroyController::class);

Route::post(ContextRoute::connectionIndex->value, ConnectionStoreController::class);
Route::post(ContextRoute::connectionSettings->value, ConnectionUpdateController::class);
Route::delete(ContextRoute::connectionSettings->value, ConnectionDestroyController::class);
Route::post(ContextRoute::connectionEnabled->value, ConnectionEnableController::class);
Route::delete(ContextRoute::connectionEnabled->value, ConnectionDisableController::class);
Route::post(ContextRoute::connectionVerify->value, ConnectionVerifyController::class);
