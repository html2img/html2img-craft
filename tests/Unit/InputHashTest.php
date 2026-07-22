<?php

use html2img\ogimages\support\InputHash;

it('returns a stable hash for identical inputs', function () {
    $a = InputHash::make('<html>card</html>', 1200, 630, 2);
    $b = InputHash::make('<html>card</html>', 1200, 630, 2);

    expect($a)->toBe($b)->and($a)->toHaveLength(64);
});

it('changes when any input changes', function () {
    $base = InputHash::make('<html>card</html>', 1200, 630, 2);

    expect(InputHash::make('<html>other</html>', 1200, 630, 2))->not->toBe($base)
        ->and(InputHash::make('<html>card</html>', 1000, 630, 2))->not->toBe($base)
        ->and(InputHash::make('<html>card</html>', 1200, 600, 2))->not->toBe($base)
        ->and(InputHash::make('<html>card</html>', 1200, 630, 1))->not->toBe($base);
});

it('does not collide when values shift between fields', function () {
    // Plain concatenation would make these identical.
    $a = InputHash::make('card', 1200, 630, 2);
    $b = InputHash::make('card1', 200, 630, 2);

    expect($a)->not->toBe($b);
});
