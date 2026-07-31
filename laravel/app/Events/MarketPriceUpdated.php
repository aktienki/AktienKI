<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketPriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $symbol,
        public float $price,
        public int $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('market-prices')];
    }

    public function broadcastAs(): string
    {
        return 'price.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol' => $this->symbol,
            'price' => $this->price,
            'timestamp' => $this->timestamp,
        ];
    }
}
