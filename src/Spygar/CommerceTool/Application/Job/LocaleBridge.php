<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

final class LocaleBridge
{
    /**
     * @param array<string, string> $ctToAkeneo
     * @param list<string>          $ctLocales
     * @param list<string>          $akeneoLocales
     *
     * @return array<string, string>
     */
    public function pairs(array $ctToAkeneo, array $ctLocales, array $akeneoLocales): array
    {
        $activated = array_fill_keys($akeneoLocales, true);
        $pairs = [];
        foreach ($ctToAkeneo as $ct => $akeneo) {
            if (!\is_string($ct) || !\is_string($akeneo) || '' === $ct || '' === $akeneo) {
                continue;
            }
            if ([] !== $ctLocales && !\in_array($ct, $ctLocales, true)) {
                continue;
            }
            if ([] !== $activated && !isset($activated[$akeneo])) {
                continue;
            }
            $pairs[$ct] = $akeneo;
        }
        if ([] !== $pairs) {
            return $pairs;
        }

        foreach ($ctLocales as $ct) {
            $akeneo = $this->match($ct, $akeneoLocales);
            if (null !== $akeneo) {
                $pairs[$ct] = $akeneo;
            }
        }

        return $pairs;
    }

    /**
     * @param list<string> $akeneoLocales
     */
    public function match(string $ct, array $akeneoLocales): ?string
    {
        $normalized = str_replace('-', '_', $ct);
        foreach ($akeneoLocales as $locale) {
            if (0 === strcasecmp($locale, $normalized)) {
                return $locale;
            }
        }
        $language = explode('_', $normalized)[0];
        foreach ($akeneoLocales as $locale) {
            if (str_starts_with(strtolower($locale), strtolower($language).'_')) {
                return $locale;
            }
        }

        return $akeneoLocales[0] ?? null;
    }

    /**
     * @param array<string, string> $labels akeneo locale => label
     * @param array<string, string> $pairs  ct locale => akeneo locale
     * @param list<string>          $ctLocales
     *
     * @return array<string, string>
     */
    public function toCommerceTool(array $labels, array $pairs, array $ctLocales, string $fallback): array
    {
        $localized = [];
        $locales = [] !== $ctLocales ? $ctLocales : array_keys($pairs);
        foreach ($locales as $ct) {
            $akeneo = $pairs[$ct] ?? null;
            $label = \is_string($akeneo) ? trim((string) ($labels[$akeneo] ?? '')) : '';
            $localized[$ct] = '' !== $label ? $label : $fallback;
        }
        if ([] === $localized && '' !== $fallback) {
            $localized['en'] = $fallback;
        }

        return $localized;
    }
}
