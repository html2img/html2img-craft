<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use html2img\ogimages\support\ApiRenderer;

function mockedRenderer(array &$history, array $body): ApiRenderer
{
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode($body)),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new ApiRenderer('test-key', new Client(['handler' => $stack]));
}

it('posts the resolved render inputs to the html endpoint', function () {
    $history = [];
    $renderer = mockedRenderer($history, [
        'success' => true,
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'url' => 'https://i.html2img.com/abc123.png',
        'credits_remaining' => 4999,
    ]);

    $response = $renderer->render('<html>card</html>', 1200, 630, 2);

    expect($response->success)->toBeTrue()
        ->and($response->url)->toBe('https://i.html2img.com/abc123.png');

    $request = $history[0]['request'];
    expect($request->getMethod())->toBe('POST')
        ->and((string)$request->getUri())->toContain('/html');

    $payload = json_decode((string)$request->getBody(), true);
    expect($payload)->toMatchArray([
        'html' => '<html>card</html>',
        'width' => 1200,
        'height' => 630,
        'dpi' => 2,
        'fullpage' => false,
        'format' => 'png',
    ])->and($payload)->not->toHaveKeys(['webhook_url', 'css']);
});

it('authenticates with the api key header', function () {
    $history = [];
    $renderer = mockedRenderer($history, ['success' => true, 'id' => 'x', 'url' => 'https://i.html2img.com/x.png']);

    $renderer->render('<html></html>', 1200, 630, 1);

    expect($history[0]['request']->getHeaderLine('X-API-Key'))->toBe('test-key');
});
