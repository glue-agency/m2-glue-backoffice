<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Model;

class Language
{
    /**
     * @param string|null $locale
     * @return string
     */
    public static function fromLocale(?string $locale): string
    {
        $locale = trim((string) $locale);

        return $locale === '' ? 'en-US' : str_replace('_', '-', $locale);
    }
}
