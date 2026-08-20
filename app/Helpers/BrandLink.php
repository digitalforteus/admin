<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * The outward links this application's brand configuration provides, and the vocabulary a
 * template names them by.
 *
 * Every destination here is assembled from the environment rather than written down: the
 * address, the scheme it is reached over and the campaign a click is attributed to all
 * travel with the configuration, not with the markup. A template that spells one out
 * instead sends people wherever the last environment pointed, and nothing fails until
 * someone follows the link.
 */
enum BrandLink: string
{
    case footer_attribution = 'footer_attribution';
    case header_lockup = 'header_lockup';
    case showcase = 'showcase';
    case support_email = 'support_email';

    /** @return string The url the link points at. */
    public function url(): string
    {
        $site = Config::string('brand.digitalforte_url');

        return match ($this) {
            self::support_email => 'mailto:'.Config::string('brand.support_email'),
            self::showcase => $site.'/showcase',
            self::footer_attribution, self::header_lockup => $site.'?'.http_build_query([
                'utm_source' => Str::slug(Config::string('app.name')),
                'utm_medium' => 'referral',
                'utm_campaign' => 'product_branding',
                'utm_content' => $this->value,
            ]),
        };
    }
}
