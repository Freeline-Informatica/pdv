<?php

namespace Freeline\Pdv\Support;

final class Gtin
{
    private const LENGTHS = [
        'GTIN-13' => 13,
        'GTIN-14' => 14,
        'EAN-8' => 8,
    ];

    public static function sanitize(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function allowedTypes(): array
    {
        return array_keys(self::LENGTHS);
    }

    public static function expectedLength(?string $type): ?int
    {
        return self::LENGTHS[self::normalizeType($type)] ?? null;
    }

    public static function normalizeType(?string $type): string
    {
        $type = strtoupper(trim((string) $type));

        return $type !== '' ? $type : 'GTIN-13';
    }

    public static function validationMessage(mixed $value, ?string $type): ?string
    {
        $type = self::normalizeType($type);
        $digits = self::sanitize($value);
        $expectedLength = self::expectedLength($type);

        if ($expectedLength === null) {
            return 'Tipo de código de barras inválido.';
        }

        if (strlen($digits) !== $expectedLength) {
            return "{$type} deve conter exatamente {$expectedLength} dígitos.";
        }

        return self::hasValidCheckDigit($digits) ? null : 'Código de barras inválido. Verifique o dígito verificador.';
    }

    public static function isValid(mixed $value, ?string $type): bool
    {
        return self::validationMessage($value, $type) === null;
    }

    private static function hasValidCheckDigit(string $digits): bool
    {
        $sum = 0;
        $factor = 3;

        for ($index = strlen($digits) - 2; $index >= 0; $index--) {
            $sum += (int) $digits[$index] * $factor;
            $factor = $factor === 3 ? 1 : 3;
        }

        $calculated = (10 - ($sum % 10)) % 10;

        return (int) substr($digits, -1) === $calculated;
    }
}
