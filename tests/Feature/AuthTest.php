<?php

use App\Helpers\Directory;
use App\Helpers\Disk;
use App\Helpers\Extension;
use App\Helpers\Gravatar;
use App\Helpers\OauthProviderId;
use App\Helpers\Picture;
use App\Helpers\ProfilePicture;
use App\Helpers\SessionKey;
use App\Models\OauthProvider;
use App\Models\User;
use App\Modules\Fortify\EmailVerificationNotificationController;
use App\Modules\Fortify\NewPasswordController;
use App\Modules\Fortify\PasswordResetLinkController;
use App\Modules\Fortify\RecoveryCodesRegenerateController;
use App\Modules\Fortify\TwoFactorConfirmController;
use App\Modules\Fortify\TwoFactorDisableController;
use App\Modules\Fortify\TwoFactorEnableController;
use App\Modules\Fortify\TwoFactorLoginController;
use App\Modules\Login\GoogleCredential;
use App\Modules\Login\GoogleUser;
use App\Modules\Passkeys\PasskeyConfirmationController;
use App\Modules\Passkeys\PasskeyConfirmationOptionsController;
use App\Modules\Passkeys\PasskeyDestroyController;
use App\Modules\Passkeys\PasskeyLoginController;
use App\Modules\Passkeys\PasskeyLoginOptionsController;
use App\Modules\Passkeys\PasskeyRegistrationController;
use App\Modules\Passkeys\PasskeyRegistrationOptionsController;
use App\Modules\PasswordReset\PasswordResetLinkResponse;
use App\Modules\Register\RegisterForm;
use App\Modules\Register\RegisterFormFactory;
use App\Modules\Verification\EmailVerificationNotificationSentResponse;
use App\Modules\Verification\VerifyEmailResponse;
use App\Routes\Auth;
use App\Sources\Db\App\OauthProviders;
use App\Sources\Db\App\Users;
use App\View\DataModels\AuthCard;
use App\View\DataModels\PictureField;
use Firebase\JWT\JWT;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as FortifyEmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController as LaravelPasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController as LaravelPasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController as LaravelPasskeyRegistrationController;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use Mockery\MockInterface;

/**
 * @template T of object
 *
 * @param  class-string<T>  $class
 * @return T&MockInterface
 */
function typedMock(string $class): MockInterface
{
    $Mock = Mockery::mock($class);

    if (! $Mock instanceof $class) {
        throw new LogicException('Mock does not implement the requested type.');
    }

    return $Mock;
}

/** @param  array<string, mixed>  $overrides */
function pictureField(array $overrides = []): PictureField
{
    return PictureField::from([
        PictureField::field => 'picture',
        PictureField::action => Auth::settingsProfilePicture->value,
        ...$overrides,
    ]);
}

test('every credential the application accepts is adapted, validated, related to its owner and pictured', function (): void {
    $Responsable = typedMock(Responsable::class);
    $Request = typedMock(Request::class);

    $TwoFactorLoginRequest = typedMock(TwoFactorLoginRequest::class);
    $TwoFactorAuthenticatedSessionController = typedMock(TwoFactorAuthenticatedSessionController::class);
    $TwoFactorAuthenticatedSessionController->shouldReceive('store')->once()->with($TwoFactorLoginRequest)->andReturn($Responsable);
    expect((new TwoFactorLoginController($TwoFactorAuthenticatedSessionController))($TwoFactorLoginRequest))->toBe($Responsable);

    $SendPasswordResetLinkRequest = typedMock(SendPasswordResetLinkRequest::class);
    $FortifyPasswordResetLinkController = typedMock(FortifyPasswordResetLinkController::class);
    $FortifyPasswordResetLinkController->shouldReceive('store')->once()->with($SendPasswordResetLinkRequest)->andReturn($Responsable);
    expect((new PasswordResetLinkController($FortifyPasswordResetLinkController))($SendPasswordResetLinkRequest))->toBe($Responsable);

    $FortifyNewPasswordController = typedMock(FortifyNewPasswordController::class);
    $FortifyNewPasswordController->shouldReceive('store')->once()->with($Request)->andReturn($Responsable);
    expect((new NewPasswordController($FortifyNewPasswordController))($Request))->toBe($Responsable);

    $FortifyEmailVerificationNotificationController = typedMock(FortifyEmailVerificationNotificationController::class);
    $FortifyEmailVerificationNotificationController->shouldReceive('store')->once()->with($Request)->andReturn($Responsable);
    expect((new EmailVerificationNotificationController($FortifyEmailVerificationNotificationController))($Request))->toBe($Responsable);

    $EnableTwoFactorAuthentication = typedMock(EnableTwoFactorAuthentication::class);
    $TwoFactorAuthenticationController = typedMock(TwoFactorAuthenticationController::class);
    $TwoFactorAuthenticationController->shouldReceive('store')->once()->with($Request, $EnableTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorEnableController($TwoFactorAuthenticationController))($Request, $EnableTwoFactorAuthentication))->toBe($Responsable);

    $ConfirmTwoFactorAuthentication = typedMock(ConfirmTwoFactorAuthentication::class);
    $ConfirmedTwoFactorAuthenticationController = typedMock(ConfirmedTwoFactorAuthenticationController::class);
    $ConfirmedTwoFactorAuthenticationController->shouldReceive('store')->once()->with($Request, $ConfirmTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorConfirmController($ConfirmedTwoFactorAuthenticationController))($Request, $ConfirmTwoFactorAuthentication))->toBe($Responsable);

    $DisableTwoFactorAuthentication = typedMock(DisableTwoFactorAuthentication::class);
    $TwoFactorAuthenticationController->shouldReceive('destroy')->once()->with($Request, $DisableTwoFactorAuthentication)->andReturn($Responsable);
    expect((new TwoFactorDisableController($TwoFactorAuthenticationController))($Request, $DisableTwoFactorAuthentication))->toBe($Responsable);

    $GenerateNewRecoveryCodes = typedMock(GenerateNewRecoveryCodes::class);
    $RecoveryCodeController = typedMock(RecoveryCodeController::class);
    $RecoveryCodeController->shouldReceive('store')->once()->with($Request, $GenerateNewRecoveryCodes)->andReturn($Responsable);
    expect((new RecoveryCodesRegenerateController($RecoveryCodeController))($Request, $GenerateNewRecoveryCodes))->toBe($Responsable);

    $Request = typedMock(Request::class);
    $JsonResponse = new JsonResponse;
    $GenerateVerificationOptions = typedMock(GenerateVerificationOptions::class);
    $VerifyPasskey = typedMock(VerifyPasskey::class);
    $PasskeyVerificationRequest = typedMock(PasskeyVerificationRequest::class);

    $LaravelPasskeyLoginController = typedMock(LaravelPasskeyLoginController::class);
    $LaravelPasskeyLoginController->shouldReceive('index')->once()->with($Request, $GenerateVerificationOptions)->andReturn($JsonResponse);
    expect((new PasskeyLoginOptionsController($LaravelPasskeyLoginController))($Request, $GenerateVerificationOptions))->toBe($JsonResponse);

    $PasskeyLoginResponse = typedMock(PasskeyLoginResponse::class);
    $LaravelPasskeyLoginController->shouldReceive('store')->once()->with($PasskeyVerificationRequest, $VerifyPasskey)->andReturn($PasskeyLoginResponse);
    expect((new PasskeyLoginController($LaravelPasskeyLoginController))($PasskeyVerificationRequest, $VerifyPasskey))->toBe($PasskeyLoginResponse);

    $LaravelPasskeyConfirmationController = typedMock(LaravelPasskeyConfirmationController::class);
    $LaravelPasskeyConfirmationController->shouldReceive('index')->once()->with($Request, $GenerateVerificationOptions)->andReturn($JsonResponse);
    expect((new PasskeyConfirmationOptionsController($LaravelPasskeyConfirmationController))($Request, $GenerateVerificationOptions))->toBe($JsonResponse);

    $PasskeyConfirmationResponse = typedMock(PasskeyConfirmationResponse::class);
    $LaravelPasskeyConfirmationController->shouldReceive('store')->once()->with($PasskeyVerificationRequest, $VerifyPasskey)->andReturn($PasskeyConfirmationResponse);
    expect((new PasskeyConfirmationController($LaravelPasskeyConfirmationController))($PasskeyVerificationRequest, $VerifyPasskey))->toBe($PasskeyConfirmationResponse);

    $GenerateRegistrationOptions = typedMock(GenerateRegistrationOptions::class);
    $LaravelPasskeyRegistrationController = typedMock(LaravelPasskeyRegistrationController::class);
    $LaravelPasskeyRegistrationController->shouldReceive('index')->once()->with($Request, $GenerateRegistrationOptions)->andReturn($JsonResponse);
    expect((new PasskeyRegistrationOptionsController($LaravelPasskeyRegistrationController))($Request, $GenerateRegistrationOptions))->toBe($JsonResponse);

    $PasskeyRegistrationRequest = typedMock(PasskeyRegistrationRequest::class);
    $StorePasskey = typedMock(StorePasskey::class);
    $PasskeyRegistrationResponse = typedMock(PasskeyRegistrationResponse::class);
    $LaravelPasskeyRegistrationController->shouldReceive('store')->once()->with($PasskeyRegistrationRequest, $StorePasskey)->andReturn($PasskeyRegistrationResponse);
    expect((new PasskeyRegistrationController($LaravelPasskeyRegistrationController))($PasskeyRegistrationRequest, $StorePasskey))->toBe($PasskeyRegistrationResponse);

    $Passkey = new Passkey;
    $DeletePasskey = typedMock(DeletePasskey::class);
    $PasskeyDeletedResponse = typedMock(PasskeyDeletedResponse::class);
    $LaravelPasskeyRegistrationController->shouldReceive('destroy')->once()->with($Passkey, $DeletePasskey)->andReturn($PasskeyDeletedResponse);
    expect((new PasskeyDestroyController($LaravelPasskeyRegistrationController))($Passkey, $DeletePasskey))->toBe($PasskeyDeletedResponse);

    $Request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);

    expect((new PasswordResetLinkResponse)->toResponse($Request)->getStatusCode())->toBe(200)
        ->and((new EmailVerificationNotificationSentResponse)->toResponse($Request)->getStatusCode())->toBe(202)
        ->and((new VerifyEmailResponse)->toResponse($Request)->getStatusCode())->toBe(204);

    $RegisterForm = RegisterFormFactory::factory()->make();

    expect($RegisterForm)->toBeInstanceOf(RegisterForm::class)
        ->and($RegisterForm->email)->toBe('john@example.com')
        ->and($RegisterForm->phone)->toBe('317-555-0123')
        ->and($RegisterForm->password)->toBe($RegisterForm->password_confirmation);

    $AuthCard = AuthCard::from();

    expect($AuthCard->title)->toBeNull()
        ->and($AuthCard->maxWidth)->toBe('max-w-sm')
        ->and($AuthCard->classname)->toBeEmpty()
        ->and($AuthCard->classes())->toBe('card mx-auto mt-6 lg:mt-24 max-w-sm');

    $Overridden = AuthCard::from([
        AuthCard::title => 'Register',
        AuthCard::maxWidth => 'lg:max-w-lg',
        AuthCard::classname => 'card-compact',
    ]);

    expect($Overridden->title)->toBe('Register')
        ->and($Overridden->classes())->toBe('card mx-auto mt-6 lg:mt-24 lg:max-w-lg card-compact');

    $GoogleUser = GoogleUser::from([
        GoogleUser::sub => '115454882825190401401',
        GoogleUser::name => 'Digital Forte',
        GoogleUser::given_name => 'Digital',
        GoogleUser::family_name => 'Forte',
        GoogleUser::picture => 'https://example.com/avatar.jpg',
        GoogleUser::email => 'admin@digitalforte.us',
        GoogleUser::email_verified => true,
        GoogleUser::hd => 'digitalforte.us',
        GoogleUser::id => '115454882825190401401',
        GoogleUser::verified_email => true,
        GoogleUser::link => null,
    ]);

    $Bare = GoogleUser::from([
        GoogleUser::sub => '123',
        GoogleUser::name => 'Google User',
        GoogleUser::given_name => 'Google',
        GoogleUser::family_name => 'User',
        GoogleUser::picture => 'https://example.com/avatar.jpg',
        GoogleUser::email => 'google@example.com',
        GoogleUser::email_verified => true,
        GoogleUser::id => '123',
        GoogleUser::verified_email => true,
    ]);

    expect($GoogleUser->sub)->toBe('115454882825190401401')
        ->and($GoogleUser->name)->toBe('Digital Forte')
        ->and($GoogleUser->given_name)->toBe('Digital')
        ->and($GoogleUser->family_name)->toBe('Forte')
        ->and($GoogleUser->picture)->toBe('https://example.com/avatar.jpg')
        ->and($GoogleUser->email)->toBe('admin@digitalforte.us')
        ->and($GoogleUser->email_verified)->toBeTrue()
        ->and($GoogleUser->hd)->toBe('digitalforte.us')
        ->and($GoogleUser->id)->toBe('115454882825190401401')
        ->and($GoogleUser->verified_email)->toBeTrue()
        ->and($GoogleUser->link)->toBeNull()
        ->and($Bare->hd)->toBeNull()
        ->and($Bare->link)->toBeNull();

    $User = User::factory()->createOne();
    $OauthProvider = $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => '115454882825190401401',
        OauthProviders::name->value => 'Digital Forte',
        OauthProviders::given_name->value => 'Digital',
        OauthProviders::family_name->value => 'Forte',
        OauthProviders::picture->value => 'https://example.com/avatar.jpg',
        OauthProviders::email->value => 'admin@digitalforte.us',
        OauthProviders::email_verified->value => true,
        OauthProviders::hd->value => 'digitalforte.us',
        OauthProviders::id->value => '115454882825190401401',
        OauthProviders::verified_email->value => true,
        OauthProviders::link->value => null,
    ]);

    expect($OauthProvider)->toBeInstanceOf(OauthProvider::class)
        ->and($OauthProvider->getKey())->toBe('115454882825190401401')
        ->and($OauthProvider->incrementing)->toBeFalse()
        ->and($OauthProvider->timestamps)->toBeFalse()
        ->and($OauthProvider->provider_id)->toBe(OauthProviderId::google)
        ->and($OauthProvider->email_verified)->toBeTrue()
        ->and($OauthProvider->verified_email)->toBeTrue()
        ->and($OauthProvider->user->is($User))->toBeTrue()
        ->and($User->oauthProviders()->sole()->is($OauthProvider))->toBeTrue();

    Http::swap(new Factory);

    expect(Gravatar::url(' MyEmailAddress@example.com '))
        ->toBe('https://www.gravatar.com/avatar/84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee?s=80&d=404&r=g');

    Http::fake([
        '*' => Http::response('image contents', 200, ['Content-Type' => 'image/png']),
    ]);

    expect(Gravatar::image('person@example.com'))
        ->toBe('data:image/png;base64,'.base64_encode('image contents'));

    foreach ([
        Http::failedConnection(),
        Http::response(status: 404, headers: ['Content-Type' => 'image/png']),
        Http::response('not an image', headers: ['Content-Type' => 'text/plain']),
    ] as $Response) {
        // Stubs accumulate and the first match wins, so each case needs its own client.
        Http::swap(new Factory);
        Http::fake(['*' => $Response]);

        expect(Gravatar::image('person@example.com'))->toBeNull();
    }

    $Uploaded = User::factory()->createOne([Users::picture->value => Directory::profile_pictures->value.'/face.jpg']);
    $this->actingAs($Uploaded);
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(ProfilePicture::current())
        ->toBe(Disk::public->url(Directory::profile_pictures->value.'/face.jpg'));

    $this->actingAs(User::factory()->createOne());
    session([SessionKey::user_picture->value => 'https://example.com/avatar.jpg']);

    expect(ProfilePicture::current())->toBe('https://example.com/avatar.jpg');

    $this->actingAs(User::factory()->createOne());
    session([SessionKey::user_picture->value => null]);

    expect(ProfilePicture::current())->toBeNull()
        ->and(ProfilePicture::url(null))->toBeNull();

    $Disk = Storage::fake(Disk::public->value);
    $User = User::factory()->createOne();
    $OauthProvider = $User->oauthProviders()->create([
        OauthProviders::provider_id->value => OauthProviderId::google->value,
        OauthProviders::sub->value => 'column-addressed',
        OauthProviders::name->value => $User->name,
        OauthProviders::given_name->value => 'Given',
        OauthProviders::family_name->value => 'Family',
        OauthProviders::picture->value => '',
        OauthProviders::email->value => $User->email,
        OauthProviders::email_verified->value => true,
        OauthProviders::id->value => 'column-addressed',
        OauthProviders::verified_email->value => true,
    ]);

    $Picture = Picture::of($OauthProvider, OauthProviders::picture, Directory::profile_pictures);
    $Picture->put(UploadedFile::fake()->image('logo.png'));

    $path = $OauthProvider->refresh()->picture;

    expect($path)->toStartWith(Directory::profile_pictures->value.'/')
        ->and($Picture->url())->toBe(Disk::public->url($path))
        ->and($User->refresh()->picture)->toBeNull();
    $Disk->assertExists($path);

    $ProfilePicture = ProfilePicture::of($User);
    $ProfilePicture->put(UploadedFile::fake()->image('face.png'));
    $facePath = (string) $User->refresh()->picture;

    $ProfilePicture->clear();

    expect($User->refresh()->picture)->toBeNull()
        ->and($ProfilePicture->url())->toBeNull();
    $Disk->assertMissing($facePath);

    expect(pictureField([PictureField::label => 'John Doe'])->initials())->toBe('JD')
        ->and(pictureField()->accept)->toBe(Extension::imageFilter())
        ->and(pictureField()->uploads)->toBe(Disk::retains())
        ->and(pictureField()->remove())->toBe('picture-remove')
        ->and(pictureField()->picture)->toBeNull()
        ->and(pictureField([PictureField::picture => '/storage/a.png'])->picture)->toBe('/storage/a.png');

    Config::set('services.google.client_id', 'client-id.apps.googleusercontent.com');
    Cache::forget('google.identity.jwks');

    Config::set('services.google.client_id');

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(InvalidArgumentException::class, 'Google sign-in is not configured.');

    Config::set('services.google.client_id', 'client-id.apps.googleusercontent.com');
    Cache::put('google.identity.jwks', 'not-an-array');

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Cached Google signing keys are invalid.');

    Cache::forget('google.identity.jwks');
    Http::fake([
        'www.googleapis.com/oauth2/v3/certs' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Google signing keys are invalid.');

    Cache::forget('google.identity.jwks');
    Http::shouldReceive('get')
        ->once()
        ->andReturn(mock(PromiseInterface::class));

    expect(fn () => app(GoogleCredential::class)->user('credential'))
        ->toThrow(RuntimeException::class, 'Google signing keys could not be loaded.');

    // Mocking the client and stubbing it are different swaps of the same facade,
    // so the mock is stood down before anything is stubbed over it again.
    Http::swap(new HttpFactory);
    Cache::forget('google.identity.jwks');

    $key = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false || ! openssl_pkey_export($key, $private_key)) {
        throw new RuntimeException('The test private key could not be generated.');
    }

    $details = openssl_pkey_get_details($key);

    if ($details === false
        || ! isset($details['rsa']['n'], $details['rsa']['e'])
        || ! is_string($details['rsa']['n'])
        || ! is_string($details['rsa']['e'])) {
        throw new RuntimeException('The test public key could not be generated.');
    }

    Http::fake([
        'www.googleapis.com/oauth2/v3/certs' => Http::response([
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $details['rsa']['n']
                        |> base64_encode(...)
                        |> (static fn ($x) => strtr($x, '+/', '-_'))
                        |> (static fn ($x) => rtrim($x, '=')),
                'e' => $details['rsa']['e']
                        |> base64_encode(...)
                        |> (static fn ($x) => strtr($x, '+/', '-_'))
                        |> (static fn ($x) => rtrim($x, '=')),
            ]],
        ]),
    ]);

    $claims = [
        'iss' => 'https://accounts.google.com',
        'aud' => 'client-id.apps.googleusercontent.com',
        'sub' => '123456789',
        'email' => 'google@example.com',
        'email_verified' => true,
        'name' => 'Google User',
        'given_name' => 'Google',
        'family_name' => 'User',
        'picture' => 'https://example.com/avatar.jpg',
        'iat' => time(),
        'exp' => time() + 300,
    ];

    $GoogleUser = app(GoogleCredential::class)->user(JWT::encode($claims, $private_key, 'RS256', 'test-key'));

    expect($GoogleUser->sub)->toBe('123456789')
        ->and($GoogleUser->email)->toBe('google@example.com')
        ->and($GoogleUser->hasVerifiedEmail())->toBeTrue()
        ->and(static fn (): GoogleUser => app(GoogleCredential::class)->user(JWT::encode([
            ...$claims,
            'aud' => 'another-client.apps.googleusercontent.com',
        ], $private_key, 'RS256', 'test-key')))->toThrow(InvalidArgumentException::class);
});
