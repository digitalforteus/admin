<?php

namespace App\Routes;

use App\Helpers\RendersRoute;
use ReflectionEnumBackedCase;

/**
 * The paths a guest can reach.
 *
 * Which index a path belongs to follows what guards it, never how the url reads:
 * these are the ones bound without a guard, and the pages no pattern gates. This is
 * the only index with a sitemap, so a case is advertised unless it says otherwise,
 * and every advertised one is held to being reachable and indexable by a stranger —
 * which makes declaring a private path here loud, while declaring a public one
 * elsewhere is silent. Redirects out of a failed request land on cases here too.
 */
enum Web: string
{
    use RendersRoute;

    case home = '/';
    #[ExcludeFromSitemap]
    #[AdminLink(order: 2)]
    case llms = '/llms.txt';
    #[ExcludeFromSitemap]
    #[AdminLink]
    case robots = '/robots.txt';
    #[ExcludeFromSitemap]
    #[AdminLink]
    case sitemap = '/sitemap.xml';
    #[ExcludeFromSitemap]
    case sitemapPage = '/sitemap-{page}.xml';
    #[ExcludeFromSitemap]
    case bingSiteAuth = '/BingSiteAuth.xml';
    #[ExcludeFromSitemap]
    #[AdminLink]
    case openapi = '/openapi.json';
    case mcp = '/mcp';
    case docs = '/docs';
    case docsApi = '/docs/api';
    case docsMcp = '/docs/mcp';
    case contact = '/contact';
    case privacyPolicy = '/privacy-policy';
    case termsOfService = '/terms-of-service';
    case login = '/login';
    #[ExcludeFromSitemap]
    case twoFactorChallenge = '/two-factor-challenge';
    #[ExcludeFromSitemap]
    case passkeyLoginOptions = '/passkeys/login/options';
    #[ExcludeFromSitemap]
    case passkeyLogin = '/passkeys/login';
    #[ExcludeFromSitemap]
    case forgotPassword = '/forgot-password';
    #[ExcludeFromSitemap]
    case forgotPasswordSent = '/forgot-password/sent';
    #[ExcludeFromSitemap]
    case resetPassword = '/reset-password/{token}';
    #[ExcludeFromSitemap]
    case resetPasswordUpdate = '/reset-password';
    #[ExcludeFromSitemap]
    case googleRedirect = '/auth/google/redirect';
    #[ExcludeFromSitemap]
    case googleCallback = '/auth/google/callback';
    #[ExcludeFromSitemap]
    case googleOneTap = '/auth/google/one-tap';
    #[ExcludeFromSitemap]
    case githubRedirect = '/auth/github/redirect';
    #[ExcludeFromSitemap]
    case githubCallback = '/auth/github/callback';
    #[ExcludeFromSitemap]
    case invitation = '/invitations/{token}';
    #[ExcludeFromSitemap]
    case logout = '/logout';
    case register = '/register';

    /** @return list<self> */
    public static function sitemap(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $case): bool => new ReflectionEnumBackedCase(self::class, $case->name)
                ->getAttributes(ExcludeFromSitemap::class) === [],
        ));
    }
}
