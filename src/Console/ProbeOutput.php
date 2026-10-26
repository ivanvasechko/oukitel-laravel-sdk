<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Laravel\Console;

use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\TslCode;
use IvanVasechko\Oukitel\Enums\TslDataType;
use IvanVasechko\Oukitel\Parsing\Redactor;
use JsonException;
use SensitiveParameter;

final class ProbeOutput
{
    public static function suffix(#[SensitiveParameter] string $deviceKey): string
    {
        $suffix = substr($deviceKey, -4);

        return strlen($deviceKey) > 8 && preg_match('/^[a-zA-Z0-9]{4}$/D', $suffix) === 1
            ? $suffix : substr(hash('sha256', $deviceKey), -4);
    }

    /** @return array<array-key, mixed> */
    public static function telemetry(Telemetry $telemetry): array
    {
        $values = get_object_vars($telemetry);
        unset($values['extra'], $values['rawTsl'], $values['diagnostics']);

        return $values;
    }

    /**
     * @param  list<TslAttribute>  $attributes
     * @return list<array{code: string, value: mixed, type: string, present: bool}>
     */
    public static function raw(#[SensitiveParameter] array $attributes): array
    {
        return array_map(static function (TslAttribute $attribute): array {
            $code = TslCode::tryFrom($attribute->resourceCode);

            return [
                'code' => $code->value ?? '[unknown]',
                'value' => $code === null ? '[redacted]' : self::value((new Redactor)->value($attribute->resourceValue)),
                'type' => TslDataType::tryFrom(strtoupper($attribute->dataType))->value ?? 'UNKNOWN',
                'present' => $attribute->present,
            ];
        }, $attributes);
    }

    private static function value(#[SensitiveParameter] mixed $value): mixed
    {
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[redacted]';
            }

            return is_string($decoded) ? '[redacted]' : self::value($decoded);
        }
        if (! is_array($value)) {
            return '[redacted]';
        }
        $safe = [];
        foreach ($value as $key => $child) {
            if (is_int($key) || in_array($key, ['power', 'voltage', 'current', 'status', 'temperature'], true)) {
                $safe[$key] = self::value($child);
            } else {
                $safe['[field]'] = '[redacted]';
            }
        }

        return $safe;
    }
}
