<?php

namespace App\Services\Broker;

use App\Models\BrokerConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class EToroBroker
{
    private function client(BrokerConnection $connection): PendingRequest
    {
        $credentials = $connection->credentials ?? [];
        abort_unless(filled($credentials['api_key'] ?? null) && filled($credentials['user_key'] ?? null), 422, 'eToro-Zugangsdaten fehlen.');

        return Http::baseUrl('https://public-api.etoro.com')->acceptJson()->asJson()->timeout(15)->withHeaders([
            'x-api-key' => $credentials['api_key'],
            'x-user-key' => $credentials['user_key'],
            'x-request-id' => (string) Str::uuid(),
        ]);
    }

    public function test(BrokerConnection $connection): array
    {
        $scope = $connection->environment === 'live' ? 'real' : 'demo';
        return $this->client($connection)->get("/api/v1/trading/info/{$scope}/pnl")->throw()->json();
    }

    public function place(BrokerConnection $connection, array $order): array
    {
        $search = $this->client($connection)->get('/api/v1/market-data/search', ['internalSymbolFull' => $order['symbol']])->throw()->json();
        $instrumentId = data_get($search, 'items.0.instrumentId') ?? data_get($search, '0.instrumentId') ?? data_get($search, 'InstrumentID');
        if (! $instrumentId) throw new RuntimeException('eToro-Instrument konnte nicht aufgelöst werden.');

        $scope = $connection->environment === 'live' ? '' : '/demo';
        $payload = [
            'action' => 'open', 'transaction' => strtolower($order['side']), 'instrumentId' => (int) $instrumentId,
            'orderType' => $order['order_type'] === 'market' ? 'mkt' : $order['order_type'],
            'amount' => (float) $order['amount'], 'orderCurrency' => strtolower($order['currency'] ?? 'usd'), 'leverage' => 1,
        ];
        if ($order['stop_loss'] ?? null) $payload['stopLossRate'] = (float) $order['stop_loss'];
        if ($order['take_profit'] ?? null) $payload['takeProfitRate'] = (float) $order['take_profit'];

        return $this->client($connection)->post("/api/v2/trading/execution{$scope}/orders", $payload)->throw()->json();
    }
}
