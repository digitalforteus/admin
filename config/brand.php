<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | What distinguishes this application from every other one built from this
    | template. These are read at render time, so an application states them
    | once here and never edits the code that consumes them: the value travels
    | with the environment, not with a source file. Leaving `description` empty
    | is honest but costly — it is the meta description every page serves.
    |
    | `logo_title` and `logo_description` are the accessible name and long
    | description of the mark. Screen readers announce them, so they describe
    | the artwork rather than repeating the application name.
    |
    */

    'description' => env('BRAND_DESCRIPTION', ''),

    'logo_title' => env('BRAND_LOGO_TITLE', 'Application logo'),

    'logo_description' => env('BRAND_LOGO_DESCRIPTION', 'The application logo.'),

    /*
    |--------------------------------------------------------------------------
    | Contact Details
    |--------------------------------------------------------------------------
    |
    | What the contact page shows. `support_email` is the address it invites
    | people to write to, and is the only required value: the page renders
    | whatever is set here. `address` is optional and is omitted from the page
    | when it is empty.
    |
    */

    'support_email' => env('BRAND_SUPPORT_EMAIL', (string) env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    'response_time' => env('BRAND_RESPONSE_TIME', 'two business days'),

    'address' => env('BRAND_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Attribution
    |--------------------------------------------------------------------------
    |
    | The builder's credit rendered in the header and footer. An application
    | that is itself the builder turns this off rather than deleting the
    | markup, so the shared templates stay identical everywhere and the choice
    | lives in the environment. With it off, nothing links out and the
    | analytics event that tracked those clicks is never registered.
    |
    */

    'attribution' => env('BRAND_ATTRIBUTION', true),

    'digitalforte_url' => env('DIGITALFORTE_URL', 'https://digitalforte.us'),

    /*
    |--------------------------------------------------------------------------
    | Legal
    |--------------------------------------------------------------------------
    |
    | The governing law clause of the terms of service. `jurisdiction` is the
    | body of law the terms are read under, and `venue` is where a dispute is
    | heard. The defaults are placeholders that render verbatim on the page:
    | set both before publishing the terms.
    |
    */

    'jurisdiction' => env('BRAND_JURISDICTION', '[jurisdiction]'),

    'venue' => env('BRAND_VENUE', '[venue]'),

];
