<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class FinalEntryCanonicalizer
{
    /** @param array<string, mixed> $source */
    public function sourceEventKey(array $source): string
    {
        $parts = [
            'serving-entry-v1',
            $this->requiredString($source, 'batch_id'),
            (string) $this->positiveInt($source, 'instrument_id'),
            $this->requiredString($source, 'release_id'),
            $this->utcTimestamp($this->dateTime($source['as_of'] ?? null)),
            (string) $this->positiveInt($source, 'horizon'),
            $this->requiredString($source, 'variant'),
        ];

        return hash('sha256', implode(chr(0), $parts));
    }

    /** @param array<string, mixed> $source */
    public function sourcePayloadHash(array $source): string
    {
        foreach (['id', 'source_prediction_id', 'created_at', 'updated_at', 'imported_at'] as $key) {
            unset($source[$key]);
        }

        return $this->sha256($source);
    }

    public function sha256(mixed $value): string
    {
        return hash('sha256', $this->json($value));
    }

    public function json(mixed $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Timestamp is required.');
        }

        return new DateTimeImmutable($value);
    }

    public function databaseTimestamp(DateTimeInterface $value): string
    {
        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.uP');
    }

    public function utcTimestamp(DateTimeInterface $value): string
    {
        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $this->utcTimestamp($value);
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $item) {
                $value[$key] = $this->canonicalize($item);
            }

            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Non-finite snapshot value.');
            }

            return $this->normalizeDecimal(sprintf('%.14F', $value));
        }
        if (is_string($value)
            && preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', $value)) {
            return $this->normalizeDecimal($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $source */
    private function requiredString(array $source, string $key): string
    {
        $value = trim((string) ($source[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Missing source '.$key.'.');
        }

        return $value;
    }

    /** @param array<string, mixed> $source */
    private function positiveInt(array $source, string $key): int
    {
        $value = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            throw new InvalidArgumentException('Invalid source '.$key.'.');
        }

        return (int) $value;
    }

    private function normalizeDecimal(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');
        $normalized = $fraction === '' ? $integer : $integer.'.'.$fraction;

        return $negative && $normalized !== '0' ? '-'.$normalized : $normalized;
    }
}
