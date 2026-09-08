<?php

namespace Tests\Unit;

use App\Services\PumaExitPayloadDecoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PumaExitPayloadDecoderTest extends TestCase
{
    public function test_it_accepts_one_pretty_printed_json_object(): void
    {
        $payloads = PumaExitPayloadDecoder::decode("{\n  \"schema_version\": 1,\n  \"value\": true\n}\n");

        $this->assertSame([['schema_version' => 1, 'value' => true]], $payloads);
    }

    public function test_it_accepts_chronological_ndjson_as_distinct_objects(): void
    {
        $payloads = PumaExitPayloadDecoder::decode(
            "{\"market_session_date\":\"2026-09-01\"}\n"
            ."{\"market_session_date\":\"2026-09-02\"}\n"
        );

        $this->assertCount(2, $payloads);
        $this->assertSame('2026-09-01', $payloads[0]['market_session_date']);
        $this->assertSame('2026-09-02', $payloads[1]['market_session_date']);
    }

    public function test_it_reports_the_exact_malformed_ndjson_line_before_any_import(): void
    {
        try {
            PumaExitPayloadDecoder::decode("{\"ok\":1}\n{not-json}\n{\"never\":\"processed\"}\n");
            $this->fail('Malformed NDJSON should have been rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('NDJSON line 2', $exception->getMessage());
        }
    }

    public function test_it_rejects_json_arrays_and_blank_interior_lines(): void
    {
        try {
            PumaExitPayloadDecoder::decode('[{"not":"ndjson"}]');
            $this->fail('A JSON array should have been rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('one JSON object', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Blank NDJSON line 2');
        PumaExitPayloadDecoder::decode("{\"first\":1}\n\n{\"third\":3}");
    }
}
