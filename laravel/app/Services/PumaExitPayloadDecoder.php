<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

final class PumaExitPayloadDecoder
{
    /**
     * Decode either one flat JSON object or newline-delimited JSON objects.
     *
     * The complete stream is decoded before the caller can start a database
     * transaction. Blank interior lines and non-object values are rejected so
     * a damaged catch-up file can never be processed only in part.
     */
    public static function decode(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('PUMA exit input is empty.');
        }

        try {
            $single = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($single) || array_is_list($single)) {
                throw new InvalidArgumentException(
                    'PUMA exit input must be one JSON object or chronologically ordered NDJSON objects.'
                );
            }

            return [$single];
        } catch (JsonException) {
            // A valid NDJSON stream is intentionally not one valid JSON value.
        }

        $lines = preg_split('/\R/u', $trimmed);
        if ($lines === false) {
            throw new InvalidArgumentException('PUMA exit input is not valid UTF-8 NDJSON.');
        }

        $payloads = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                throw new InvalidArgumentException('Blank NDJSON line '.($index + 1).' is not allowed.');
            }

            try {
                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException(
                    'Invalid JSON on NDJSON line '.($index + 1).': '.$exception->getMessage(),
                    0,
                    $exception,
                );
            }

            if (! is_array($payload) || array_is_list($payload)) {
                throw new InvalidArgumentException('NDJSON line '.($index + 1).' must be one JSON object.');
            }
            $payloads[] = $payload;
        }

        return $payloads;
    }
}
