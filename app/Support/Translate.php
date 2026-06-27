<?php

declare(strict_types=1);

namespace App\Support;

final class Translate
{
    /**
     * Resolve a locale-keyed label array to a string for the current locale,
     * falling back to English, then the first available value.
     *
     * @param  array<string, string>|null  $label
     */
    public static function label(?array $label, ?string $locale = null): string
    {
        if ($label === null || $label === []) {
            return '';
        }

        $locale ??= app()->getLocale();

        return $label[$locale] ?? $label['en'] ?? (string) reset($label);
    }
}
