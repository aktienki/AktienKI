<?php

namespace App\Jobs;

use App\Enums\PlanLevel;
use App\Models\EasyAccessSubscriber;
use App\Models\ExternalBuyReview;
use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use App\Models\SignalEmailDelivery;
use App\Models\SmartSelectionLabel;
use App\Models\User;
use App\Notifications\SignalChangedNotification;
use App\Notifications\SmartSelectionSignalNotification;
use App\Services\PlanAccessService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SendSignalEmailAfterReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 50;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly User|EasyAccessSubscriber $recipient,
        public readonly Prediction $prediction,
        public readonly SavedPredictionFilter|SmartSelectionLabel $strategy,
        public readonly string $previousSignal,
        public readonly int $deliveryId = 0,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $this->prediction->loadMissing('instrument');

        if ($this->mustWaitForExternalReview()) {
            $this->release(20);

            return;
        }

        $notification = $this->strategy instanceof SmartSelectionLabel
            ? new SmartSelectionSignalNotification($this->prediction, $this->strategy, $this->previousSignal)
            : new SignalChangedNotification(
                $this->prediction,
                $this->strategy,
                $this->previousSignal,
                $this->deliveryId,
            );

        $this->recipient->notifyNow($notification);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->deliveryId <= 0) {
            return;
        }

        SignalEmailDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_message' => mb_substr((string) ($exception?->getMessage() ?: 'Unbekannter Fehler'), 0, 2000),
        ]);
    }

    private function mustWaitForExternalReview(): bool
    {
        if (! ($this->recipient instanceof User)
            || ! app(PlanAccessService::class)->allowsTariff($this->recipient, PlanLevel::Pro)
            || ! config('aktienki.external_buy_review.enabled', false)
            || strtoupper((string) $this->prediction->signal) !== 'BUY'
            || ! Schema::hasTable('external_buy_reviews')) {
            return false;
        }

        $review = ExternalBuyReview::query()
            ->where('prediction_id', $this->prediction->id)
            ->first(['status']);

        if ($review === null) {
            return false;
        }

        if (! in_array($review->status, ['pending', 'running'], true)) {
            return false;
        }

        return $this->attempts() <= 30;
    }
}
