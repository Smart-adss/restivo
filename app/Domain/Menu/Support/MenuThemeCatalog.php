<?php

namespace App\Domain\Menu\Support;

final class MenuThemeCatalog
{
    /** @var list<string> */
    public const ALLOWED_TOKEN_KEYS = ['brand', 'canvas', 'ink', 'accent', 'line', 'brand-ink'];

    /**
     * @return array<string, array{name_ar: string, name_en: string, tokens: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'green' => [
                'name_ar' => 'أخضر Restivo',
                'name_en' => 'Restivo green',
                'tokens' => [
                    'brand' => '#145c40',
                    'canvas' => '#e7eeea',
                    'ink' => '#12201b',
                    'accent' => '#f2c14e',
                    'line' => '#d5e0da',
                    'brand-ink' => '#f3faf6',
                ],
            ],
            'sand' => [
                'name_ar' => 'رمل دافئ',
                'name_en' => 'Warm sand',
                'tokens' => [
                    'brand' => '#8a5a2b',
                    'canvas' => '#f3ebe0',
                    'ink' => '#2a2118',
                    'accent' => '#c9852d',
                    'line' => '#e2d3c2',
                    'brand-ink' => '#fff8f0',
                ],
            ],
            'charcoal' => [
                'name_ar' => 'فحمي',
                'name_en' => 'Charcoal',
                'tokens' => [
                    'brand' => '#1f2933',
                    'canvas' => '#eef1f4',
                    'ink' => '#111827',
                    'accent' => '#38bdf8',
                    'line' => '#d5dde5',
                    'brand-ink' => '#f8fafc',
                ],
            ],
            'ocean' => [
                'name_ar' => 'بحري',
                'name_en' => 'Ocean',
                'tokens' => [
                    'brand' => '#0f4c5c',
                    'canvas' => '#e8f3f5',
                    'ink' => '#102a32',
                    'accent' => '#f4a261',
                    'line' => '#cfe0e5',
                    'brand-ink' => '#f2fbfd',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return array<string, string>
     */
    public static function sanitize(array $tokens): array
    {
        $clean = [];

        foreach (self::ALLOWED_TOKEN_KEYS as $key) {
            $value = $tokens[$key] ?? null;

            if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                continue;
            }

            $clean[$key] = strtolower($value);
        }

        return $clean;
    }
}
