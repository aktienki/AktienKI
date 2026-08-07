<?php

namespace App\Services\Broker;

use App\Models\BrokerConnection;
use RuntimeException;

final class CTraderFixBroker
{
    private const SOH = "\x01";

    public function test(BrokerConnection $connection): array
    {
        $quote = $this->logon($connection, 'quote');
        $trade = $this->logon($connection, 'trade');

        return ['protocol' => 'FIX.4.4', 'quote' => $quote, 'trade' => $trade];
    }

    public function place(BrokerConnection $connection, array $order): array
    {
        $session = $this->open($connection, 'trade');
        try {
            $this->authenticate($session);
            $type = match ($order['order_type']) {
                'market' => '1', 'limit' => '2', 'stop' => '3',
                default => throw new RuntimeException('Nicht unterstützter FIX-Ordertyp.'),
            };
            if ($type === '2' && ! is_numeric($order['limit_price'] ?? null)) {
                throw new RuntimeException('Eine Limit-Order benötigt einen Limitpreis.');
            }
            if ($type === '3' && ! is_numeric($order['limit_price'] ?? null)) {
                throw new RuntimeException('Eine Stop-Order benötigt im Feld Limitpreis den Stop-Auslösekurs.');
            }
            $fields = [
                11 => 'AKI-'.now()->format('YmdHisv'),
                21 => '1',
                55 => strtoupper(trim((string) $order['symbol'])),
                54 => $order['side'] === 'buy' ? '1' : '2',
                38 => $this->decimal((float) $order['quantity']),
                40 => $type,
                59 => '1',
                60 => now('UTC')->format('Ymd-H:i:s.v'),
            ];
            if ($type === '2') $fields[44] = $this->decimal((float) $order['limit_price']);
            if ($type === '3') $fields[99] = $this->decimal((float) ($order['limit_price'] ?? 0));
            $this->send($session, 'D', $fields);
            $response = $this->receive($session, ['8', '9']);
            if (($response[35] ?? '') === '8' && in_array(($response[39] ?? ''), ['8'], true)) {
                throw new RuntimeException($response[58] ?? 'FIX-Order wurde abgelehnt.');
            }
            if (($response[35] ?? '') === '3') throw new RuntimeException($response[58] ?? 'FIX Session Reject.');

            return [
                'protocol' => 'FIX.4.4',
                'clientOrderId' => $response[11] ?? $fields[11],
                'orderId' => $response[37] ?? null,
                'executionType' => $response[150] ?? null,
                'orderStatus' => $response[39] ?? null,
                'text' => $response[58] ?? null,
            ];
        } finally {
            $this->close($session);
        }
    }

    public function positions(BrokerConnection $connection): array
    {
        $session = $this->open($connection, 'trade');
        try {
            $this->authenticate($session);
            $requestId = 'AKI-POS-'.now()->format('YmdHisv');
            $this->send($session, 'AN', [710 => $requestId]);

            $positions = [];
            $expected = null;
            do {
                $report = $this->receive($session, ['AP', 'j', '3', '5']);
                if (($report[35] ?? '') !== 'AP') {
                    throw new RuntimeException($report[58] ?? 'cTrader hat die Positionsabfrage abgelehnt.');
                }
                if (($report[728] ?? '0') === '2') break;
                if (($report[728] ?? '0') !== '0') {
                    throw new RuntimeException('cTrader meldet ein ungültiges Ergebnis der Positionsabfrage.');
                }
                $expected ??= (int) ($report[727] ?? 0);
                $long = (float) ($report[704] ?? 0);
                $short = (float) ($report[705] ?? 0);
                $positions[] = [
                    'position_id' => $report[721] ?? null,
                    'symbol_id' => $report[55] ?? null,
                    'side' => $long > 0 ? 'buy' : 'sell',
                    'quantity' => max($long, $short),
                    'average_price' => isset($report[730]) ? (float) $report[730] : null,
                ];
            } while ($expected === null || count($positions) < $expected);

            return ['positions' => $positions, 'updated_at' => now()->toIso8601String()];
        } finally {
            $this->close($session);
        }
    }

    private function logon(BrokerConnection $connection, string $channel): array
    {
        $session = $this->open($connection, $channel);
        try {
            $response = $this->authenticate($session);
            return ['status' => 'connected', 'channel' => $channel, 'message_type' => $response[35] ?? null];
        } finally {
            $this->close($session);
        }
    }

    private function open(BrokerConnection $connection, string $channel): array
    {
        $credentials = $connection->credentials ?? [];
        if (! filled($connection->external_account_id)) {
            throw new RuntimeException('Die cTrader-Kontonummer fehlt. Sie wird als FIX Username verwendet.');
        }
        foreach (['fix_host', 'fix_sender_comp_id', 'fix_target_comp_id', 'fix_password'] as $key) {
            if (! filled($credentials[$key] ?? null)) throw new RuntimeException("FIX {$key} fehlt.");
        }
        $portKey = $channel === 'quote' ? 'fix_quote_port' : 'fix_trade_port';
        $subKey = $channel === 'quote' ? 'fix_quote_sender_sub_id' : 'fix_trade_sender_sub_id';
        $port = (int) ($credentials[$portKey] ?? 0);
        if ($port < 1) throw new RuntimeException("FIX {$portKey} fehlt.");
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $credentials['fix_host'],
            'SNI_enabled' => true,
        ]]);
        $socket = @stream_socket_client(
            "tls://{$credentials['fix_host']}:{$port}",
            $errorNumber,
            $errorMessage,
            12,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (! is_resource($socket)) throw new RuntimeException("FIX {$channel}-Verbindung fehlgeschlagen: {$errorMessage} ({$errorNumber}).");
        stream_set_timeout($socket, 12);
        return [
            'socket' => $socket, 'sequence' => 1, 'channel' => $channel, 'buffer' => '',
            'sender' => (string) $credentials['fix_sender_comp_id'],
            'target' => trim((string) $credentials['fix_target_comp_id']),
            'sub_id' => (string) ($credentials[$subKey] ?? strtoupper($channel)),
            'username' => (string) $connection->external_account_id,
            'password' => (string) $credentials['fix_password'],
        ];
    }

    private function authenticate(array &$session): array
    {
        $this->send($session, 'A', [98 => '0', 108 => '30', 141 => 'Y', 553 => $session['username'], 554 => $session['password']]);
        $response = $this->receive($session, ['A', '5', '3']);
        if (($response[35] ?? '') !== 'A') throw new RuntimeException($response[58] ?? 'FIX-Logon wurde abgelehnt.');
        return $response;
    }

    private function send(array &$session, string $type, array $fields = []): void
    {
        $standard = [
            // cTrader validates its standard header in this documented order.
            35 => $type,
            49 => $session['sender'],
            56 => $session['target'],
            57 => $session['sub_id'],
            50 => $session['sub_id'],
            34 => $session['sequence']++,
            52 => now('UTC')->format('Ymd-H:i:s.v'),
        ];
        $body = '';
        foreach ($standard + $fields as $tag => $value) $body .= $tag.'='.$value.self::SOH;
        $head = '8=FIX.4.4'.self::SOH.'9='.strlen($body).self::SOH;
        $unsigned = $head.$body;
        $message = $unsigned.'10='.str_pad((string) (array_sum(unpack('C*', $unsigned)) % 256), 3, '0', STR_PAD_LEFT).self::SOH;
        if (fwrite($session['socket'], $message) !== strlen($message)) throw new RuntimeException('FIX-Nachricht konnte nicht vollständig gesendet werden.');
    }

    private function receive(array &$session, array $accepted): array
    {
        while (! feof($session['socket'])) {
            if (! preg_match('/^.*?\x0110=\d{3}\x01/s', $session['buffer'], $match)) {
                $session['buffer'] .= (string) fread($session['socket'], 8192);
            }
            if (preg_match('/^.*?\x0110=\d{3}\x01/s', $session['buffer'], $match)) {
                $message = $match[0];
                $session['buffer'] = substr($session['buffer'], strlen($message));
                $fields = [];
                foreach (explode(self::SOH, $message) as $field) {
                    if (! str_contains($field, '=')) continue;
                    [$tag, $value] = explode('=', $field, 2);
                    $fields[(int) $tag] = $value;
                }
                if (($fields[35] ?? '') === '1') {
                    $this->send($session, '0', isset($fields[112]) ? [112 => $fields[112]] : []);
                    continue;
                }
                if (in_array($fields[35] ?? '', $accepted, true)) return $fields;
            }
            $meta = stream_get_meta_data($session['socket']);
            if ($meta['timed_out'] ?? false) throw new RuntimeException('Zeitüberschreitung bei der FIX-Antwort.');
        }
        throw new RuntimeException('FIX-Verbindung wurde ohne Antwort geschlossen.');
    }

    private function close(array $session): void
    {
        if (is_resource($session['socket'] ?? null)) fclose($session['socket']);
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }
}
