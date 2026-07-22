<?php

namespace html2img\ogimages\support;

/**
 * Fingerprints the inputs of a render so unchanged saves can skip the API.
 */
final class InputHash
{
    public static function make(string $html, int $width, int $height, int $dpi): string
    {
        return hash('sha256', json_encode([$html, $width, $height, $dpi]));
    }
}
