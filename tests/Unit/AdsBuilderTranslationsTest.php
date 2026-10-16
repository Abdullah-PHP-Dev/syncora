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

    /** lang file => the Vue files that read it with t()/tc(). */
    private const FILES = [
        'ads_builder' => 'resources/js/components/ads/facebook/*.{vue,js}',
        'tiktok_builder' => 'resources/js/components/ads/tiktok/*.vue',
        'x_builder' => 'resources/js/components/ads/x/*.vue',
        'snapchat_builder' => 'resources/js/components/ads/snapchat/*.vue',
        'ads_dashboard' => 'resources/js/components/ads/AdsDashboard.vue',
        'media_gallery' => 'resources/js/components/media/MediaGallery.vue',
        'composer' => 'resources/js/components/posts/composer/*.vue',
        'posts_listing' => 'resources/js/components/posts/PostsDashboard.vue',
        'connections' => 'resources/js/components/connections/ConnectionHub.vue',
        'email_designer' => 'resources/js/components/email/{*.vue,designer/*.vue}',
    ];

    /** lang file => the Blade views that read it with __()/trans_choice(). */
    private const BLADE_FILES = [
        'email_marketing' => 'resources/views/{admin/email/*,admin/email/*/*,email-marketing/*}.blade.php',
    ];

    /** Files whose placeholders sit right before a unit (":pxpx" = "1080px"), so names can't be compared as text. */
    private const UNIT_PLACEHOLDERS = ['media_gallery'];

    private function load(string $locale, string $file = 'ads_builder'): array
    {
        return $this->flatten(require dirname(__DIR__, 2) . "/lang/{$locale}/{$file}.php");
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
        foreach (array_keys(self::FILES + self::BLADE_FILES) as $file) {
            $en = $this->load('en', $file);
            $ar = $this->load('ar', $file);

            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))), "{$file}: missing in Arabic");
            $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))), "{$file}: missing in English");
        }
    }

    public function test_every_translation_is_filled_and_keeps_its_placeholders(): void
    {
        foreach (array_diff(array_keys(self::FILES + self::BLADE_FILES), self::UNIT_PLACEHOLDERS) as $file) {
            $this->assertFilledWithSamePlaceholders($this->load('en', $file), $this->load('ar', $file));
        }
    }

    private function assertFilledWithSamePlaceholders(array $en, array $ar): void
    {
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

    public function test_every_key_the_pages_use_exists(): void
    {
        foreach (self::FILES as $langFile => $pattern) {
            $en = $this->load('en', $langFile);
            $used = [];
            foreach (glob(dirname(__DIR__, 2) . '/' . $pattern, GLOB_BRACE) as $file) {
                preg_match_all("/\\bt[c]?\\('([a-z_]+(?:\\.[A-Za-z_]+)*)'/", file_get_contents($file), $m);
                $used = array_merge($used, $m[1]);
            }
            $used = array_unique($used);

            $this->assertGreaterThan(30, count($used), $langFile);
            $this->assertSame([], array_values(array_diff($used, array_keys($en))), "Keys used in the Vue code but missing from lang/en/{$langFile}.php");
        }
    }

    public function test_every_key_the_blade_pages_use_exists(): void
    {
        foreach (self::BLADE_FILES as $langFile => $pattern) {
            $messages = require dirname(__DIR__, 2) . "/lang/en/{$langFile}.php";
            $used = [];
            foreach (glob(dirname(__DIR__, 2) . '/' . $pattern, GLOB_BRACE) as $file) {
                preg_match_all("/(?:__|trans_choice)\\('{$langFile}\\.([a-z_]+(?:\\.[a-z_]+)*)'/", file_get_contents($file), $m);
                $used = array_merge($used, $m[1]);
            }
            $used = array_unique($used);

            $this->assertGreaterThan(100, count($used), $langFile);
            // A key may name a group (eg. __('...form.examples') loops over an array).
            $missing = array_filter($used, fn ($key) => data_get($messages, $key) === null);
            $this->assertSame([], array_values($missing), "Keys used in the Blade views but missing from lang/en/{$langFile}.php");
        }
    }
}
