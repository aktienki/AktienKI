{{-- resources/views/livewire/dashboard/ai-engine.blade.php --}}

<section wire:poll.10s="refreshData">

    <h2 class="ak-section-title">
        AI Engine
    </h2>

    <div class="ak-card-grid">

        @foreach($engine as $card)

            @php
                $color = match($card['color']) {
                    'green' => 'text-emerald-300 bg-emerald-500/15 border-emerald-500/30',
                    'red' => 'text-rose-300 bg-rose-500/15 border-rose-500/30',
                    'amber' => 'text-amber-300 bg-amber-500/15 border-amber-500/30',
                    default => 'text-violet-300 bg-violet-500/15 border-violet-500/30',
                };
            @endphp

            <div class="ak-card">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="ak-card-label">
                            {{ $card['title'] }}
                        </p>

                        <p class="ak-card-value">
                            {{ $card['value'] }}
                        </p>

                    </div>

                    <span
                        class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $color }}">

                        {{ $card['status'] }}

                    </span>

                </div>

                <div class="mt-4">

                    <x-dashboard.score-stripes :percent="$card['percent']" />

                </div>

                <div class="mt-3 flex items-center justify-between">

                    <span class="text-[10px] text-slate-500">

                        Status

                    </span>

                    <span class="text-xs font-semibold text-white">

                        {{ $card['percent'] }} %

                    </span>

                </div>

            </div>

        @endforeach

    </div>

</section>
