<?php

namespace App\Jobs;

use App\Models\ExternalBuyReview;
use App\Services\ExternalBuyReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class GenerateExternalBuyReview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 210;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $reviewId) {}

    public function uniqueId(): string
    {
        return (string) $this->reviewId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ExternalBuyReviewService $service): void
    {
        $review = ExternalBuyReview::query()->findOrFail($this->reviewId);
        if ($review->status === 'completed') {
            return;
        }

        $service->review($review);
    }

    public function failed(?Throwable $exception): void
    {
        ExternalBuyReview::query()->whereKey($this->reviewId)->update([
            'status' => 'failed',
            'error_message' => mb_substr((string) ($exception?->getMessage() ?: 'Unbekannter Fehler'), 0, 4000),
            'failed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
