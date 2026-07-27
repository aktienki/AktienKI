{{-- resources/views/livewire/dashboard/market-sentiment.blade.php --}}

<section wire:poll.60s="refreshData">

    <h2 class="ak-section-title">
        {{ __('Marktsituation') }}
    </h2>

    <div class="ak-card-grid">

        @foreach($sentiment as $card)

            <x-dashboard.market-situation-card
                :card="$card"/>

        @endforeach

    </div>

</section>
