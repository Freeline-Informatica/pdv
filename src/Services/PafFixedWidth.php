<?php

namespace Freeline\Pdv\Services;

use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;

class PafFixedWidth
{
    public function text(mixed $value, int $length, bool $uppercase = false): string
    {
        $value = $this->ascii((string) ($value ?? ''));
        $value = $uppercase ? mb_strtoupper($value) : $value;

        return str_pad(substr($value, 0, $length), $length, ' ');
    }

    public function digits(mixed $value, int $length): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';

        return str_pad(substr($digits, -$length), $length, '0', STR_PAD_LEFT);
    }

    public function number(mixed $value, int $length, int $decimals = 0): string
    {
        $numeric = is_numeric($value) ? (float) $value : 0.0;
        $scaled = (string) (int) round(abs($numeric) * (10 ** $decimals));

        if (strlen($scaled) > $length) {
            throw new InvalidArgumentException("Valor {$value} excede {$length} posicoes no leiaute PAF.");
        }

        return str_pad($scaled, $length, '0', STR_PAD_LEFT);
    }

    public function date(mixed $value): string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('Ymd');
        }

        if (blank($value)) {
            return str_repeat('0', 8);
        }

        return date('Ymd', strtotime((string) $value));
    }

    public function time(mixed $value): string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('His');
        }

        if (blank($value)) {
            return str_repeat('0', 6);
        }

        return date('His', strtotime((string) $value));
    }

    public function line(string ...$fields): string
    {
        return implode('', $fields);
    }

    public function ascii(string $value): string
    {
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ç' => 'c', 'Ç' => 'C',
        ]);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $converted === false ? $value : $converted;

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }
}
