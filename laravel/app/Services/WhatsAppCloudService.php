<?php

namespace App\Services;

use App\Models\MessagingConnection;
use Illuminate\Support\Facades\Http;

final class WhatsAppCloudService
{
    public function send(MessagingConnection $connection, string $message): array
    {
        $credentials = $connection->credentials ?? [];
        abort_unless($connection->enabled && filled($credentials['access_token'] ?? null) && filled($credentials['phone_number_id'] ?? null) && filled($connection->recipient), 422, 'WhatsApp ist nicht vollständig eingerichtet.');
        $response = Http::withToken($credentials['access_token'])->asJson()->timeout(15)
            ->post('https://graph.facebook.com/v23.0/'.$credentials['phone_number_id'].'/messages', [
                'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $connection->recipient,
                'type' => 'text', 'text' => ['preview_url' => false, 'body' => $message],
            ])->throw()->json();
        $connection->update(['last_sent_at' => now()]);
        return $response;
    }
}
