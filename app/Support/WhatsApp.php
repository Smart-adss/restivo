<?php

namespace App\Support;

final class WhatsApp
{
    public static function meUrl(string $phone, string $text): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
