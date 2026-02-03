<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(
        [
            __DIR__ . '/rules',
            __DIR__ . '/rules-tests',
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ],
    );

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x2Migration' => true,
    ])
    ->setFinder($finder);