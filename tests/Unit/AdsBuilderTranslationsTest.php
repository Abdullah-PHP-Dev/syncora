<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * lang/{en,ar}/ads_builder.php drive the Facebook campaign builder in both
 * languages: they must have the same keys and placeholders, and every key
 * the Vue components look up must exist.
 */
class AdsBuilderTranslationsTest extends TestCase
{
    private function flatten(array $messages, string $prefix = ''): array
    {
        $out = [];
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                $out += $this->flatten($value, "{$prefix}{$key}.");
            } else {
                $out["{$prefix}{$key}"] = $value;
            }
        }

        return $out;
    }

    private function load(string $locale): array
    {
        return $this->flatten(require dirname(__DIR__, 2) . "/lang/{$locale}/ads_builder.php");
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/:([a-z_]+)/', $text, $m);
        $names = array_values(array_unique($m[1]));
        sort($names);

        return $names;
    }

    public function test_english_and_arabic_have_the_same_keys(): void
    {
        $en = $this->load('en');
        $ar = $this->load('ar');

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))), 'Missing in Arabic');
        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))), 'Missing in English');
    }

    public function test_every_translation_is_filled_and_keeps_its_placeholders(): void
    {
        $en = $this->load('en');
        $ar = $this->load('ar');

        foreach ($en as $key => $text) {
            $this->assertNotSame('', trim((string) $text), "en {$key} is empty");
            $this->assertNotSame('', trim((string) $ar[$key]), "ar {$key} is empty");
            // :count can be dropped in an Arabic plural form ("يوم واحد"), so compare the rest.
            $this->assertSame(
                array_values(array_diff($this->placeholders($text), ['count'])),
                array_values(array_diff($this->placeholders($ar[$key]), ['count'])),
                "Placeholders differ for {$key}"
            );
        }
    }

    public function test_every_key_the_builder_page_uses_exists(): void
    {
        $en = $this->load('en');
        $used = [];
        foreach (glob(dirname(__DIR__, 2) . '/resources/js/components/ads/facebook/*.{vue,js}', GLOB_BRACE) as $file) {
            preg_match_all("/\\bt[c]?\\('([a-z_]+(?:\\.[A-Za-z_]+)+)'/", file_get_contents($file), $m);
            $used = array_merge($used, $m[1]);
        }

        $this->assertGreaterThan(150, count(array_unique($used)));
        $this->assertSame([], array_values(array_diff(array_unique($used), array_keys($en))), 'Keys used in the Vue code but missing from lang/en/ads_builder.php');
    }
}
