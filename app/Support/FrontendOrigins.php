<?php

namespace App\Support;

final class FrontendOrigins
{
    /**
     * @return list<string>
     */
    public static function resolve(
        ?string $configuredOrigins,
        ?string $canonicalFrontendUrl,
        bool $isLocal,
    ): array {
        $origins = self::parse($configuredOrigins);

        if ($origins !== []) {
            return $origins;
        }

        $fallback = self::parse($canonicalFrontendUrl);

        if ($fallback !== []) {
            return $fallback;
        }

        return $isLocal ? ['http://localhost:5173'] : [];
    }

    /**
     * @return list<string>
     */
    public static function parse(?string $origins): array
    {
        if ($origins === null || trim($origins) === '') {
            return [];
        }

        $normalized = array_map(
            static fn (string $origin): string => rtrim(trim($origin), '/'),
            explode(',', $origins),
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $origin): bool => $origin !== '' && ! str_contains($origin, '*'),
        )));
    }
}
