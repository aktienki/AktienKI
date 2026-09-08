<?php

namespace App\Services;

use App\Data\FinalEntrySignalDecisionInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Resolves current filter settings from the main database, never from a caller. */
class FinalEntryFilterContextResolver
{
    /** Known optimizer/exit artifacts remain audit evidence, not entry gates. */
    private const NON_ENTRY_METADATA_KEYS = [
        'max_profit_horizon_rules',
        'optimizer_result',
    ];

    /** @var array<string,list<array<string,mixed>>> */
    private array $runtimeCache = [];

    public function __construct(
        private readonly FinalEntryBaseFilterPolicy $basePolicy,
    ) {}

    public function resetRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    /**
     * The mandatory user-profile policy is always evaluated first. A saved
     * strategy is evaluated separately and ANDed with that base policy by the
     * decision service, preventing duplicate rule keys from overwriting one
     * another or weakening the baseline.
     *
     * @return list<array{origin:string, reference:string, settings:array<string,mixed>, metadata?:array<string,mixed>}>
     */
    public function resolve(FinalEntrySignalDecisionInput $input): array
    {
        $cacheKey = implode(':', [
            (string) $input->userId,
            $input->contextType,
            (string) ($input->savedPredictionFilterId ?? 0),
        ]);
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }
        $users = User::query()
            ->where('id', $input->userId)
            ->whereNull('deleted_at')
            ->limit(2)
            ->get(['id', 'meta']);
        if ($users->count() !== 1) {
            throw new LogicException('ENTRY_CONTEXT_USER_NOT_FOUND');
        }
        $user = $users->first();
        if (! $user instanceof User) {
            throw new LogicException('ENTRY_CONTEXT_USER_NOT_FOUND');
        }

        $contexts = [[
            'origin' => 'user_profile',
            'reference' => 'user:'.$input->userId.':'.FinalEntryBaseFilterPolicy::VERSION,
            'settings' => $this->basePolicy->settings($user),
            'metadata' => $this->basePolicy->metadata($user),
        ]];

        if ($input->contextType === 'user_profile') {
            if ($input->savedPredictionFilterId !== null) {
                throw new LogicException('ENTRY_CONTEXT_INVALID');
            }

            return $this->runtimeCache[$cacheKey] = $contexts;
        }

        if ($input->contextType !== 'saved_filter'
            || $input->savedPredictionFilterId === null
            || $input->savedPredictionFilterId <= 0) {
            throw new LogicException('ENTRY_CONTEXT_INVALID');
        }

        // Ownership is deliberately stricter than UI visibility. A future
        // public-strategy resolver must snapshot an explicit authorization.
        $rows = DB::table('saved_prediction_filters')
            ->where('id', $input->savedPredictionFilterId)
            ->where('user_id', $input->userId)
            ->limit(2)
            ->get(['id', 'user_id', 'filters', 'updated_at']);
        if ($rows->count() !== 1) {
            throw new LogicException('SAVED_FILTER_NOT_FOUND_OR_NOT_OWNED');
        }

        $row = $rows->first();
        $settings = $this->decodeObject($row->filters ?? null);
        $metadata = [];
        foreach (self::NON_ENTRY_METADATA_KEYS as $key) {
            if (array_key_exists($key, $settings)) {
                $metadata[$key] = $settings[$key];
                unset($settings[$key]);
            }
        }
        $contexts[] = [
            'origin' => 'saved_filter',
            'reference' => 'saved_filter:'.(int) $row->id.':'.(string) $row->updated_at,
            'settings' => $settings,
            'metadata' => $metadata,
        ];

        return $this->runtimeCache[$cacheKey] = $contexts;
    }

    /** @return array<string, mixed> */
    private function decodeObject(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LogicException('SAVED_FILTER_SNAPSHOT_INVALID');
            }
        }
        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new LogicException('SAVED_FILTER_SNAPSHOT_INVALID');
        }

        return $value;
    }
}
