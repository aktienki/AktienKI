<?php

namespace App\Services\Broker;

use App\Models\BrokerConnection;
use Illuminate\Support\Str;
use RuntimeException;
use WebSocket\Client;

final class CTraderBroker
{
    public function hasAccountAccess(BrokerConnection $connection): bool
    {
        $credentials = $connection->credentials ?? [];
        return filled($credentials['client_id'] ?? null)
            && filled($credentials['client_secret'] ?? null)
            && filled($credentials['access_token'] ?? null)
            && is_numeric($connection->external_account_id);
    }

    public function accountSnapshot(BrokerConnection $connection): array
    {
        if (! $this->hasAccountAccess($connection)) {
            throw new RuntimeException('cTrader Open API ist für Live-Kontowerte noch nicht autorisiert.');
        }
        $accountId = (int) $connection->external_account_id;
        $responses = $this->exchangeMany($connection, [
            [2121, ['ctidTraderAccountId' => $accountId], 2122, 'trader'],
            [2124, ['ctidTraderAccountId' => $accountId], 2125, 'reconcile'],
            [2187, ['ctidTraderAccountId' => $accountId], 2188, 'pnl'],
        ]);
        $trader = data_get($responses, 'trader.trader', []);
        $moneyDigits = (int) (data_get($responses, 'pnl.moneyDigits') ?? data_get($trader, 'moneyDigits', 2));
        $factor = 10 ** $moneyDigits;
        $balance = (float) data_get($trader, 'balance', 0) / $factor;
        $pnlRows = collect(data_get($responses, 'pnl.positionUnrealizedPnL', []));
        $netPnl = $pnlRows->sum(fn (array $row): float => (float) ($row['netUnrealizedPnL'] ?? 0)) / $factor;
        $profit = $pnlRows->sum(fn (array $row): float => max(0, (float) ($row['netUnrealizedPnL'] ?? 0))) / $factor;
        $loss = $pnlRows->sum(fn (array $row): float => min(0, (float) ($row['netUnrealizedPnL'] ?? 0))) / $factor;
        $usedMargin = collect(data_get($responses, 'reconcile.position', []))->sum(function (array $position): float {
            $digits = (int) ($position['moneyDigits'] ?? 2);
            return (float) ($position['usedMargin'] ?? 0) / (10 ** $digits);
        });
        $equity = $balance + $netPnl;

        return [
            'balance' => $balance,
            'equity' => $equity,
            'used_margin' => $usedMargin,
            'free_margin' => $equity - $usedMargin,
            'net_pnl' => $netPnl,
            'profit' => $profit,
            'loss' => $loss,
        ];
    }

    private function exchangeMany(BrokerConnection $connection, array $requests): array
    {
        $credentials = $connection->credentials ?? [];
        $host = $connection->environment === 'live' ? 'live.ctraderapi.com' : 'demo.ctraderapi.com';
        $client = new Client("wss://{$host}:5036", ['timeout' => 12]);
        $send = function (int $payloadType, array $payload) use ($client): void {
            $client->text(json_encode(['clientMsgId' => (string) Str::uuid(), 'payloadType' => $payloadType, 'payload' => $payload], JSON_THROW_ON_ERROR));
        };
        $receive = function (int $expected) use ($client): array {
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $response = json_decode((string) $client->receive(), true, 512, JSON_THROW_ON_ERROR);
                if (in_array((int) ($response['payloadType'] ?? 0), [50, 2142], true)) throw new RuntimeException(data_get($response, 'payload.description', 'cTrader hat die Anfrage abgelehnt.'));
                if ((int) ($response['payloadType'] ?? 0) === $expected) return $response['payload'] ?? [];
            }
            throw new RuntimeException('Keine passende cTrader-Antwort empfangen.');
        };
        try {
            $send(2100, ['clientId' => $credentials['client_id'], 'clientSecret' => $credentials['client_secret']]);
            $receive(2101);
            $send(2102, ['ctidTraderAccountId' => (int) $connection->external_account_id, 'accessToken' => $credentials['access_token']]);
            $receive(2103);
            $result = [];
            foreach ($requests as [$requestType, $payload, $responseType, $key]) {
                $send($requestType, $payload);
                $result[$key] = $receive($responseType);
            }
            return $result;
        } finally {
            $client->close();
        }
    }

    private function exchange(BrokerConnection $connection, array $messages, int $expectedPayload): array
    {
        $credentials = $connection->credentials ?? [];
        foreach (['client_id', 'client_secret', 'access_token'] as $key) if (! filled($credentials[$key] ?? null)) throw new RuntimeException("cTrader {$key} fehlt.");
        $host = $connection->environment === 'live' ? 'live.ctraderapi.com' : 'demo.ctraderapi.com';
        $client = new Client("wss://{$host}:5036", ['timeout' => 12]);
        try {
            foreach ($messages as [$payloadType, $payload]) {
                $client->text(json_encode(['clientMsgId' => (string) Str::uuid(), 'payloadType' => $payloadType, 'payload' => $payload], JSON_THROW_ON_ERROR));
                for ($attempt = 0; $attempt < 12; $attempt++) {
                    $response = json_decode((string) $client->receive(), true, 512, JSON_THROW_ON_ERROR);
                    if (in_array((int) ($response['payloadType'] ?? 0), [50, 2142], true)) throw new RuntimeException(data_get($response, 'payload.description', 'cTrader hat die Anfrage abgelehnt.'));
                    if ((int) ($response['payloadType'] ?? 0) === $expectedPayload && $payloadType === array_last($messages)[0]) return $response['payload'] ?? [];
                    if ($payloadType !== array_last($messages)[0]) break;
                }
            }
        } finally { $client->close(); }
        throw new RuntimeException('Keine bestätigte cTrader-Antwort empfangen.');
    }

    private function authMessages(BrokerConnection $connection): array
    {
        $c = $connection->credentials ?? [];
        foreach (['client_id', 'client_secret', 'access_token'] as $key) {
            if (! filled($c[$key] ?? null)) {
                throw new RuntimeException("cTrader {$key} fehlt.");
            }
        }
        if (! filled($connection->external_account_id) || ! is_numeric($connection->external_account_id)) {
            throw new RuntimeException('cTrader Konto-ID fehlt oder ist ungültig.');
        }

        return [
            [2100, ['clientId' => $c['client_id'], 'clientSecret' => $c['client_secret']]],
            [2102, ['ctidTraderAccountId' => (int) $connection->external_account_id, 'accessToken' => $c['access_token']]],
        ];
    }

    public function test(BrokerConnection $connection): array
    {
        return $this->exchange($connection, [...$this->authMessages($connection), [2121, ['ctidTraderAccountId' => (int) $connection->external_account_id]]], 2122);
    }

    public function place(BrokerConnection $connection, array $order): array
    {
        if (! is_numeric($order['symbol'])) throw new RuntimeException('Für cTrader wird zunächst die numerische Symbol-ID aus dem Pepperstone-Konto benötigt.');
        $payload = [
            'ctidTraderAccountId' => (int) $connection->external_account_id,
            'symbolId' => (int) $order['symbol'], 'orderType' => strtoupper($order['order_type']),
            'tradeSide' => strtoupper($order['side']), 'volume' => (int) round((float) $order['quantity'] * 100),
            'label' => 'AktienKI',
        ];
        if ($order['limit_price'] ?? null) $payload['limitPrice'] = (float) $order['limit_price'];
        if ($order['stop_loss'] ?? null) $payload['stopLoss'] = (float) $order['stop_loss'];
        if ($order['take_profit'] ?? null) $payload['takeProfit'] = (float) $order['take_profit'];
        return $this->exchange($connection, [...$this->authMessages($connection), [2106, $payload]], 2126);
    }
}
