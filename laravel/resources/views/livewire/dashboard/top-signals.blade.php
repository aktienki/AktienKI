<section id="top-signals" class="scroll-mt-24" wire:poll.10s="refreshSignals">

    <h2 class="ak-section-title">
        Top KI Signale
    </h2>

    <div class="ak-card-grid">

        @foreach($signals as $signal)

            <x-dashboard.signal-card :signal="$signal"/>

        @endforeach

    </div>

</section>
