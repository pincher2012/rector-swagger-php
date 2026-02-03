<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\OpenApi\AddMissingOAAnnotationImportRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AddMissingOAAnnotationImportRector::class);
    $rectorConfig->importNames();
};
