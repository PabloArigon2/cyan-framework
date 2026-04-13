<?php

final class Color {
    public static function HexToRgb(string $hexColor): array {
        $hexColor = ltrim($hexColor, '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }
        return [
            'r' => hexdec(substr($hexColor, 0, 2)),
            'g' => hexdec(substr($hexColor, 2, 2)),
            'b' => hexdec(substr($hexColor, 4, 2))
        ];
    }

    public static function Luminance(int $r, int $g, int $b): float {
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
    }

    public static function SmartHover(string $bgColor, string $preferred = '#45a7c4', string $fallback = '#000000'): string {
        $rgbBg = self::HexToRgb($bgColor);
        $lumBg = self::Luminance($rgbBg['r'], $rgbBg['g'], $rgbBg['b']);
        $rgbPref = self::HexToRgb($preferred);
        $lumPref = self::Luminance($rgbPref['r'], $rgbPref['g'], $rgbPref['b']);

        return (abs($lumBg - $lumPref) >= 50.0) ? $preferred : $fallback;
    }

    public static function Contrast(string $hexColor): string {
        $rgb = self::HexToRgb($hexColor);
        return (self::Luminance($rgb['r'], $rgb['g'], $rgb['b']) > 128) ? '#000000' : '#ffffff';
    }
}
