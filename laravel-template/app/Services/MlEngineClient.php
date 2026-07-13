<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class MlEngineClient
{
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('aktienki.ml_engine.url'))
            ->acceptJson()
            ->withToken((string) config('aktienki.ml_engine.token'))
            ->timeout((int) config('aktienki.ml_engine.timeout_seconds', 30));
    }

    public function health(): array
    {
        return $this->client()->get('/health')->throw()->json();
    }
}
