<?php

namespace html2img\ogimages\support;

use GuzzleHttp\ClientInterface;
use Html2img\Enum\Format;
use Html2img\Html2imgClient;
use Html2img\Request\HtmlRequest;
use Html2img\Response\RenderResponse;

/**
 * Thin wrapper around the HTML to Image PHP SDK. Keeping the client behind
 * one seam means the HTTP layer can be swapped for a mock in tests.
 */
final class ApiRenderer
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?ClientInterface $httpClient = null,
    ) {
    }

    public function render(string $html, int $width, int $height, int $dpi): RenderResponse
    {
        $client = new Html2imgClient(
            apiKey: $this->apiKey,
            httpClient: $this->httpClient,
        );

        return $client->html(new HtmlRequest(
            html: $html,
            width: $width,
            height: $height,
            fullpage: false,
            dpi: $dpi,
            format: Format::Png,
        ));
    }
}
