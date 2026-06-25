<?php

namespace Freeline\Pdv\Support;

class QuantityNormalizer
{
    public const INTEGER_UNIT_MESSAGE = 'Esta unidade não permite quantidade decimal.';

    public const SCALE_MESSAGE = 'Quantidade deve ter no máximo 3 casas decimais.';

    public const FORMAT_MESSAGE = 'Informe a quantidade no padrão brasileiro. Exemplo: 1.000,500.';

    private const BRAZILIAN_INTEGER_PATTERN = '(?:\d+|\d{1,3}(?:\.\d{3})+)';

    public static function normalize(mixed $value, bool $allowsFractional, int $scale = 3): array
    {
        if ($value === null || $value === '') {
            return ['valid' => true, 'value' => null, 'message' => null];
        }

        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value) || (float) $value < 0) {
                return ['valid' => false, 'value' => null, 'message' => self::FORMAT_MESSAGE];
            }

            $normalized = self::trimNormalized(number_format((float) $value, $scale, '.', ''));

            if (! $allowsFractional && str_contains($normalized, '.')) {
                return ['valid' => false, 'value' => null, 'message' => self::INTEGER_UNIT_MESSAGE];
            }

            return ['valid' => true, 'value' => $normalized, 'message' => null];
        }

        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '-') || preg_match('/\s/', $text)) {
            return ['valid' => false, 'value' => null, 'message' => self::FORMAT_MESSAGE];
        }

        if (str_contains($text, ',')) {
            return self::normalizeBrazilian($text, $allowsFractional, $scale);
        }

        if (preg_match('/^'.self::BRAZILIAN_INTEGER_PATTERN.'$/', $text)) {
            return ['valid' => true, 'value' => str_replace('.', '', $text), 'message' => null];
        }

        return self::normalizeApiDecimal($text, $allowsFractional, $scale);
    }

    public static function formatForDisplay(mixed $value, bool $allowsFractional, int $scale = 3): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;
        $decimals = $allowsFractional && abs($numeric - floor($numeric)) > 0.0000001 ? $scale : 0;

        return rtrim(rtrim(number_format($numeric, $decimals, '.', ''), '0'), '.');
    }

    private static function normalizeBrazilian(string $text, bool $allowsFractional, int $scale): array
    {
        $pattern = '/^'.self::BRAZILIAN_INTEGER_PATTERN.',(\d+)$/';
        if (! preg_match($pattern, $text, $matches)) {
            return ['valid' => false, 'value' => null, 'message' => self::FORMAT_MESSAGE];
        }

        if (! $allowsFractional) {
            return ['valid' => false, 'value' => null, 'message' => self::INTEGER_UNIT_MESSAGE];
        }

        if (strlen($matches[1]) > $scale) {
            return ['valid' => false, 'value' => null, 'message' => self::SCALE_MESSAGE];
        }

        return [
            'valid' => true,
            'value' => self::trimNormalized(str_replace(',', '.', str_replace('.', '', $text))),
            'message' => null,
        ];
    }

    private static function normalizeApiDecimal(string $text, bool $allowsFractional, int $scale): array
    {
        if (! preg_match('/^\d+(?:\.(\d+))?$/', $text, $matches)) {
            return ['valid' => false, 'value' => null, 'message' => self::FORMAT_MESSAGE];
        }

        $decimals = $matches[1] ?? '';
        if ($decimals !== '') {
            if (! $allowsFractional) {
                return ['valid' => false, 'value' => null, 'message' => self::INTEGER_UNIT_MESSAGE];
            }

            if (strlen($decimals) > $scale) {
                return ['valid' => false, 'value' => null, 'message' => self::SCALE_MESSAGE];
            }
        }

        return ['valid' => true, 'value' => self::trimNormalized($text), 'message' => null];
    }

    private static function trimNormalized(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
