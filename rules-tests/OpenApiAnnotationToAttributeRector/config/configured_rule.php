<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\OpenApi\OpenApiAnnotationToAttributeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(OpenApiAnnotationToAttributeRector::class);
};
