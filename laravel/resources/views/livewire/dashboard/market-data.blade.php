{{-- resources/views/livewire/dashboard/market-data.blade.php --}}

<section class="ak-dashboard-content">

    <h2 class="ak-section-title">
        {{ __('Märkte & Marktsituation') }}
    </h2>

    <div class="ak-dashboard-primary grid items-stretch gap-5 lg:grid-cols-2">
        <x-dashboard.market-atlas :country-ai-scores="$countryAiScores" />
        <x-dashboard.overall-market-situation :daily-ai-scores="$dailyAiScores" :assessment="$overallAssessment" />
    </div>

    <h3 class="ak-dashboard-market-title mb-3 mt-6 text-xs font-black uppercase tracking-[.18em] text-slate-400">
        {{ __('Indizes & Märkte') }}
    </h3>

    <div class="ak-card-grid ak-dashboard-markets">
        @foreach($markets as $market)
            <x-dashboard.market-card :market="$market" />
        @endforeach
    </div>

</section>
