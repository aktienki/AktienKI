<?php

namespace App\Http\Controllers;

use App\Models\BrokerConnection;
use App\Models\BrokerOrder;
use App\Models\MessagingConnection;
use App\Services\Broker\CTraderBroker;
use App\Services\Broker\CTraderFixBroker;
use App\Services\Broker\EToroBroker;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Throwable;

final class TradingIntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $connections = BrokerConnection::query()->where('user_id', $request->user()->id)->with(['orders' => fn ($q) => $q->latest()->limit(10)])->orderBy('provider')->orderBy('environment')->get();
        $whatsapp = MessagingConnection::query()->firstOrNew(['user_id' => $request->user()->id, 'provider' => 'whatsapp_cloud']);
        return view('integrations.index', compact('connections', 'whatsapp'));
    }

    public function accounts(Request $request): View
    {
        $accounts = BrokerConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'pepperstone_ctrader')
            ->withCount([
                'orders',
                'orders as submitted_orders_count' => fn ($query) => $query->where('status', 'submitted'),
                'orders as failed_orders_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->with(['orders' => fn ($query) => $query->latest()->limit(8)])
            ->orderByRaw("case when environment = 'live' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return view('accounts.index', compact('accounts'));
    }

    public function accountPositions(Request $request, BrokerConnection $connection, CTraderFixBroker $fix, CTraderBroker $ctrader): JsonResponse
    {
        $this->owned($request, $connection);
        abort_unless($connection->provider === 'pepperstone_ctrader', 404);
        abort_unless(data_get($connection->credentials, 'connection_type', 'openapi') === 'fix', 422, 'Live-Positionen sind für dieses Konto noch nicht eingerichtet.');

        $snapshot = ['positions' => [], 'updated_at' => now()->toIso8601String()];
        try { $snapshot = array_merge($snapshot, $fix->positions($connection)); }
        catch (Throwable $positionException) { report($positionException); $snapshot['positions_error'] = $positionException->getMessage(); }
        if ($ctrader->hasAccountAccess($connection)) {
            try { $snapshot['account'] = $ctrader->accountSnapshot($connection); }
            catch (Throwable $accountException) { report($accountException); $snapshot['account_error'] = $accountException->getMessage(); }
        } else {
            $snapshot['account_error'] = 'Open API noch nicht autorisiert.';
        }

        return response()->json($snapshot);
    }

    public function storeBroker(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'connection_id' => ['nullable', 'integer'],
            'provider' => ['required', 'in:etoro,pepperstone_ctrader'], 'environment' => ['required', 'in:demo,live'],
            'name' => ['required', 'string', 'max:80'], 'external_account_id' => ['nullable', 'string', 'max:80'],
            'api_key' => ['nullable', 'string', 'max:500'], 'user_key' => ['nullable', 'string', 'max:500'],
            'client_id' => ['nullable', 'string', 'max:500'], 'client_secret' => ['nullable', 'string', 'max:500'],
            'connection_type' => ['nullable', 'in:openapi,fix'],
            'fix_host' => ['nullable', 'string', 'max:255'],
            'fix_quote_port' => ['nullable', 'integer', 'between:1,65535'], 'fix_trade_port' => ['nullable', 'integer', 'between:1,65535'],
            'fix_sender_comp_id' => ['nullable', 'string', 'max:255'], 'fix_target_comp_id' => ['nullable', 'string', 'max:255'],
            'fix_quote_sender_sub_id' => ['nullable', 'string', 'max:40'], 'fix_trade_sender_sub_id' => ['nullable', 'string', 'max:40'],
            'fix_password' => ['nullable', 'string', 'max:500'],
            'access_token' => ['nullable', 'string', 'max:2000'], 'refresh_token' => ['nullable', 'string', 'max:2000'],
            'max_order_value' => ['required', 'numeric', 'min:1', 'max:10000000'], 'daily_loss_limit' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'trading_enabled' => ['nullable', 'boolean'], 'emergency_stop' => ['nullable', 'boolean'],
        ]);
        $connection = filled($data['connection_id'] ?? null)
            ? BrokerConnection::query()->where('user_id', $request->user()->id)->findOrFail($data['connection_id'])
            : new BrokerConnection(['user_id' => $request->user()->id]);
        $isFix = $data['provider'] === 'pepperstone_ctrader' && ($data['connection_type'] ?? 'openapi') === 'fix';
        if ($isFix) {
            foreach (['external_account_id', 'fix_host', 'fix_quote_port', 'fix_trade_port', 'fix_sender_comp_id', 'fix_target_comp_id', 'fix_quote_sender_sub_id', 'fix_trade_sender_sub_id'] as $key) {
                abort_unless(filled($data[$key] ?? null), 422, "Das FIX-Feld '{$key}' ist erforderlich.");
            }
            abort_unless(filled($data['fix_password'] ?? null) || filled(data_get($connection->credentials, 'fix_password')), 422, 'Das FIX-Kontopasswort ist erforderlich.');
        }
        $credentials = $connection->credentials ?? [];
        foreach (['api_key', 'user_key', 'client_id', 'client_secret', 'access_token', 'refresh_token', 'fix_password'] as $key) if (filled($data[$key] ?? null)) $credentials[$key] = $data[$key];
        foreach (['connection_type', 'fix_host', 'fix_quote_port', 'fix_trade_port', 'fix_sender_comp_id', 'fix_target_comp_id', 'fix_quote_sender_sub_id', 'fix_trade_sender_sub_id'] as $key) {
            if (array_key_exists($key, $data) && filled($data[$key])) $credentials[$key] = $data[$key];
        }
        $connection->fill([
            'provider' => $data['provider'], 'environment' => $data['environment'],
            'name' => $data['name'], 'external_account_id' => $data['external_account_id'] ?? $connection->external_account_id,
            'credentials' => $credentials, 'max_order_value' => $data['max_order_value'], 'daily_loss_limit' => $data['daily_loss_limit'],
            'trading_enabled' => $request->boolean('trading_enabled'), 'emergency_stop' => $request->boolean('emergency_stop', true),
        ])->save();
        return back()->with('status', __('Brokerverbindung gespeichert. Der Not-Aus bleibt maßgeblich.'));
    }

    public function destroyBroker(Request $request, BrokerConnection $connection): RedirectResponse
    {
        $this->owned($request, $connection);
        abort_unless($request->string('confirmation')->upper()->toString() === 'KONTO LÖSCHEN', 422, "Zur Bestätigung 'KONTO LÖSCHEN' eingeben.");
        $name = $connection->name;
        $connection->delete();

        return back()->with('status', __('Kontoverbindung „:name“ wurde gelöscht.', ['name' => $name]));
    }

    public function testBroker(Request $request, BrokerConnection $connection, EToroBroker $etoro, CTraderBroker $ctrader, CTraderFixBroker $fix): RedirectResponse
    {
        $this->owned($request, $connection);
        try {
            if ($connection->provider === 'etoro') $etoro->test($connection);
            elseif (data_get($connection->credentials, 'connection_type', 'openapi') === 'fix') $fix->test($connection);
            else $ctrader->test($connection);
            $connection->update(['last_connected_at' => now()]);
            return back()->with('status', __(':broker-Verbindung erfolgreich geprüft.', ['broker' => $connection->name]));
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['broker' => __('Verbindung fehlgeschlagen: :message', ['message' => $e->getMessage()])]);
        }
    }

    public function ctraderAuthorize(Request $request, BrokerConnection $connection): RedirectResponse
    {
        $this->owned($request, $connection);
        abort_unless($connection->provider === 'pepperstone_ctrader', 404);
        $clientId = data_get($connection->credentials, 'client_id');
        $clientSecret = data_get($connection->credentials, 'client_secret');
        if (! filled($clientId) || ! filled($clientSecret)) {
            return redirect()->route('integrations.index')->withErrors([
                'ctrader_oauth' => __('Für die Live-Kontodaten zuerst unter „Konto verwalten“ die cTrader Open API Client-ID und das Client Secret speichern.'),
            ]);
        }
        $request->session()->put('ctrader_oauth_connection', $connection->id);
        $url = 'https://id.ctrader.com/my/settings/openapi/grantingaccess/?'.http_build_query([
            'client_id' => $clientId, 'redirect_uri' => route('integrations.ctrader.callback'), 'scope' => 'trading', 'product' => 'web',
        ]);
        return redirect()->away($url);
    }

    public function ctraderCallback(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $connection = BrokerConnection::query()->where('user_id', $request->user()->id)->findOrFail($request->session()->pull('ctrader_oauth_connection'));
        $credentials = $connection->credentials ?? [];
        $token = Http::acceptJson()->timeout(15)->get('https://openapi.ctrader.com/apps/token', [
            'grant_type' => 'authorization_code', 'code' => $request->string('code')->toString(),
            'redirect_uri' => route('integrations.ctrader.callback'), 'client_id' => $credentials['client_id'], 'client_secret' => $credentials['client_secret'],
        ])->throw()->json();
        $connection->update(['credentials' => array_merge($credentials, ['access_token' => $token['accessToken'], 'refresh_token' => $token['refreshToken']])]);
        return redirect()->route('integrations.index')->with('status', __('cTrader wurde autorisiert. Jetzt Konto-ID eintragen und Verbindung testen.'));
    }

    public function placeOrder(Request $request, BrokerConnection $connection, EToroBroker $etoro, CTraderBroker $ctrader, CTraderFixBroker $fix, WhatsAppCloudService $whatsapp): RedirectResponse
    {
        $this->owned($request, $connection);
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:40'], 'side' => ['required', 'in:buy,sell'], 'order_type' => ['required', 'in:market,limit,stop'],
            'amount' => ['nullable', 'numeric', 'min:0.01'], 'quantity' => ['nullable', 'numeric', 'min:0.01'], 'currency' => ['nullable', 'string', 'size:3'],
            'limit_price' => ['nullable', 'numeric', 'min:0.00000001'], 'stop_loss' => ['nullable', 'numeric', 'min:0.00000001'], 'take_profit' => ['nullable', 'numeric', 'min:0.00000001'],
            'confirmation' => ['required', 'string'],
        ]);
        abort_unless($connection->trading_enabled && ! $connection->emergency_stop, 423, 'Trading ist deaktiviert oder der Not-Aus ist aktiv.');
        $expected = $connection->environment === 'live' ? 'LIVE ORDER' : 'DEMO ORDER';
        abort_unless(hash_equals($expected, strtoupper(trim($data['confirmation']))), 422, "Zur Bestätigung '{$expected}' eingeben.");
        $orderValue = (float) ($data['amount'] ?? 0);
        $isFix = $connection->provider === 'pepperstone_ctrader' && data_get($connection->credentials, 'connection_type', 'openapi') === 'fix';
        abort_if($connection->provider === 'etoro' && $orderValue <= 0, 422, 'eToro benötigt einen Orderbetrag.');
        abort_if($isFix && $orderValue <= 0, 422, 'FIX benötigt einen geschätzten Orderwert zur Prüfung des Orderlimits.');
        abort_if($connection->provider === 'pepperstone_ctrader' && (float) ($data['quantity'] ?? 0) <= 0, 422, 'cTrader benötigt eine Stückzahl bzw. Einheitenzahl.');
        abort_if($orderValue > (float) $connection->max_order_value, 422, 'Das Orderlimit dieser Verbindung wurde überschritten.');

        $idempotency = hash('sha256', $request->user()->id.'|'.$connection->id.'|'.json_encode($data).'|'.now()->format('Y-m-d H:i'));
        $order = BrokerOrder::query()->firstOrCreate(['broker_connection_id' => $connection->id, 'idempotency_key' => $idempotency], [
            'public_id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'symbol' => strtoupper($data['symbol']), 'side' => $data['side'],
            'order_type' => $data['order_type'], 'quantity' => $data['quantity'] ?? null, 'amount' => $data['amount'] ?? null,
            'limit_price' => $data['limit_price'] ?? null, 'stop_loss' => $data['stop_loss'] ?? null, 'take_profit' => $data['take_profit'] ?? null,
            'request_payload' => collect($data)->except('confirmation')->all(),
        ]);
        if (! $order->wasRecentlyCreated) return back()->withErrors(['order' => __('Doppelte Order wurde blockiert.')]);

        try {
            $response = DB::transaction(function () use ($connection, $data, $etoro, $ctrader, $fix): array {
                if ($connection->provider === 'etoro') return $etoro->place($connection, $data);
                return data_get($connection->credentials, 'connection_type', 'openapi') === 'fix'
                    ? $fix->place($connection, $data)
                    : $ctrader->place($connection, $data);
            });
            $brokerOrderId = data_get($response, 'orderId') ?? data_get($response, 'order.orderId') ?? data_get($response, 'position.positionId');
            $order->update(['status' => 'submitted', 'broker_order_id' => $brokerOrderId, 'response_payload' => $response, 'submitted_at' => now()]);
            $message = "AktienKI: {$connection->name} {$connection->environment} · ".strtoupper($data['side'])." {$data['symbol']} · Order übermittelt";
            $messaging = MessagingConnection::query()->where('user_id', $request->user()->id)->where('provider', 'whatsapp_cloud')->first();
            if ($messaging?->enabled) try { $whatsapp->send($messaging, $message); } catch (Throwable $notifyError) { report($notifyError); }
            return back()->with('status', __('Order wurde an :broker übermittelt.', ['broker' => $connection->name]));
        } catch (Throwable $e) {
            $order->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            report($e);
            return back()->withErrors(['order' => __('Order abgelehnt: :message', ['message' => $e->getMessage()])]);
        }
    }

    public function storeWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate(['access_token' => ['nullable', 'string', 'max:3000'], 'phone_number_id' => ['nullable', 'string', 'max:80'], 'recipient' => ['required', 'string', 'max:40'], 'enabled' => ['nullable', 'boolean']]);
        $connection = MessagingConnection::query()->firstOrNew(['user_id' => $request->user()->id, 'provider' => 'whatsapp_cloud']);
        $credentials = $connection->credentials ?? [];
        foreach (['access_token', 'phone_number_id'] as $key) if (filled($data[$key] ?? null)) $credentials[$key] = $data[$key];
        $connection->fill(['credentials' => $credentials, 'recipient' => preg_replace('/[^0-9]/', '', $data['recipient']), 'enabled' => $request->boolean('enabled')])->save();
        return back()->with('status', __('WhatsApp-Einstellungen gespeichert.'));
    }

    public function testWhatsApp(Request $request, WhatsAppCloudService $whatsapp): RedirectResponse
    {
        $connection = MessagingConnection::query()->where('user_id', $request->user()->id)->where('provider', 'whatsapp_cloud')->firstOrFail();
        try { $whatsapp->send($connection, __('AktienKI: WhatsApp-Verbindung erfolgreich eingerichtet.')); }
        catch (Throwable $e) { report($e); return back()->withErrors(['whatsapp' => $e->getMessage()]); }
        return back()->with('status', __('WhatsApp-Testnachricht wurde versendet.'));
    }

    private function owned(Request $request, BrokerConnection $connection): void { abort_unless((int) $connection->user_id === (int) $request->user()->id, 404); }
}
