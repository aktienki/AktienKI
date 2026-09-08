<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\ExternalBuyReviewTrigger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class GenerateBuyTransitionReviews extends Command
{
    protected $signature = 'reports:buy-reviews {--since=1440 : Look-back window in minutes}';

    protected $description = 'Queue missing external research reports for genuine transitions to BUY without sending signal emails';

    public function handle(ExternalBuyReviewTrigger $reviews): int
    {
        $queued = 0;
        $checked = 0;

        Prediction::query()
            ->with('instrument')
            ->where('prediction_time', '>=', now()->subMinutes(max(1, (int) $this->option('since'))))
            ->whereRaw('UPPER(signal) = ?', ['BUY'])
            ->whereHas('instrument', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('id')
            ->chunkById(250, function ($predictions) use ($reviews, &$queued, &$checked): void {
                foreach ($predictions as $prediction) {
                    $checked++;
                    $previous = Prediction::query()
                        ->where('instrument_id', $prediction->instrument_id)
                        ->where('prediction_time', '<', $prediction->prediction_time)
                        ->orderByDesc('prediction_time')
                        ->orderByDesc('id')
                        ->first();

                    if ($previous && $reviews->queueForTransition($prediction, $previous)) $queued++;
                }
            });

        $this->info(sprintf('Checked %d BUY predictions; queued %d missing research reports.', $checked, $queued));

        return self::SUCCESS;
    }
}
