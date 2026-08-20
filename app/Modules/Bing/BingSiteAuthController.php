<?php

namespace App\Modules\Bing;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

readonly class BingSiteAuthController
{
    public function __invoke(): Response
    {
        $contentId = config('microsoft.content_id');
        $contentId = htmlspecialchars(is_string($contentId) ? $contentId : '', ENT_XML1);

        return new Response(
            "<?xml version=\"1.0\"?>\n<users>\n\t<user>{$contentId}</user>\n</users>",
            ResponseAlias::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=utf-8'],
        );
    }
}
