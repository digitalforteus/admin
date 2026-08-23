<?php

namespace App\Routes;

use App\Helpers\RendersRoute;

/**
 * The paths behind session authentication.
 *
 * Which index a path belongs to follows what guards it, never how the url reads:
 * these are the ones bound inside the authenticated group, and the pages an
 * authenticated pattern gates. There is no sitemap here and nothing to add one, so
 * a path declared here is never advertised — which is the point for a private page,
 * and an unnoticed omission for a public one.
 */
enum Auth: string
{
    use RendersRoute;

    public const string credentialParameter = 'credential';
    public const string passkeyParameter = 'passkey';
    public const string sessionParameter = 'session';
    public const string organizationParameter = 'organization_id';

    case dashboard = '/dashboard';
    case confirmPassword = '/confirm-password';
    case settings = '/settings';
    case settingsProfile = '/settings/profile';
    case settingsProfilePicture = '/settings/profile/picture';
    case settingsSessions = '/settings/sessions';
    case settingsSession = '/settings/sessions/{'.self::sessionParameter.'}';
    case settingsSecurity = '/settings/security';
    case settingsCredentials = '/settings/credentials';
    case settingsCredential = '/settings/credentials/{'.self::credentialParameter.'}';
    case settingsAppearance = '/settings/appearance';
    case settingsOrganizations = '/settings/organizations';
    case settingsOrganizationCreate = '/settings/organizations/new';
    case settingsOrganization = '/settings/organizations/{'.self::organizationParameter.'}';
    case settingsOrganizationIcon = '/settings/organizations/{'.self::organizationParameter.'}/icon';
    case verificationNotice = '/email/verify';
    case verificationVerify = '/email/verify/{id}/{hash}';
    case verificationSend = '/email/verification-notification';
    case passkeyManagementConfirm = '/settings/security/passkeys/confirm';
    case passkeyConfirmOptions = '/passkeys/confirm/options';
    case passkeyConfirm = '/passkeys/confirm';
    case twoFactorAuthentication = '/user/two-factor-authentication';
    case confirmedTwoFactorAuthentication = '/user/confirmed-two-factor-authentication';
    case twoFactorRecoveryCodes = '/user/two-factor-recovery-codes';
    case passkeyRegistrationOptions = '/user/passkeys/options';
    case passkeys = '/user/passkeys';
    case passkey = '/user/passkeys/{'.self::passkeyParameter.'}';
}
