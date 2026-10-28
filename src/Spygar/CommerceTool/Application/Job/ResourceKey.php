<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

final class ResourceKey
{
    public static function commerceTool(string $code): ?string
    {
        return 1 === preg_match('/^[A-Za-z0-9_-]{2,256}$/', $code) ? $code : null;
    }

    public static function akeneo(string $key): ?string
    {
        $code = str_replace('-', '_', $key);

        return 1 === preg_match('/^[a-zA-Z0-9_]{1,100}$/', $code) ? $code : null;
    }
}
