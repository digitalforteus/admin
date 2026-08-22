<?php

namespace App\Providers;

use App\Helpers\Theme;
use App\Models\PersonalAccessToken;
use App\Modules\Api\Support\SchemaController;
use App\Modules\PasswordReset\PasswordResetLinkResponse;
use App\Modules\PasswordReset\ResetUserPassword;
use App\Modules\Verification\EmailVerificationNotificationSentResponse;
use App\Modules\Verification\VerifyEmailResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Head\Enums\Media;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(EmailVerificationNotificationSentResponseContract::class, EmailVerificationNotificationSentResponse::class);
        $this->app->bind(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
        View::addLocation(dirname(__DIR__).'/View/Components');
        View::addLocation(dirname(__DIR__).'/Modules/Connections');
        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function boot(): void
    {
        $this->registerOpenApiSchemas();

        $name = Config::string('app.name');

        Head::defaults(static function (HeadBuilder $head) use ($name): HeadBuilder {
            return $head
                ->title($name, suffix: " - $name")
                ->description(Config::string('brand.description'))
                ->applicationName($name)
                ->canonical()
                ->viewport('width=device-width, initial-scale=1.0')
                ->colorScheme('light dark')
                ->referrer('strict-origin-when-cross-origin')
                ->themeColor(Theme::light->color(), media: Media::Light)
                ->themeColor(Theme::dark->color(), media: Media::Dark)
                ->og(type: OgType::Website, siteName: $name)
                ->ogImage('/android-chrome-512x512.png', width: 512, height: 512)
                ->twitter(card: TwitterCard::Summary)
                ->searchableByRobots();
        });
    }

    private function registerOpenApiSchemas(): void
    {
        /** @var array<string, array{route?: array{uri?: string, name?: string, middleware?: list<string>}}> $schemas */
        $schemas = Config::array('openapi.schemas', []);

        foreach ($schemas as $schema => $configuration) {
            $route = $configuration['route'] ?? [];

            Route::middleware($route['middleware'] ?? [])
                ->get($route['uri'] ?? "$schema/openapi.json", SchemaController::class)
                ->defaults('schema', $schema)
                ->name($route['name'] ?? "openapi.$schema");
        }
    }
}
