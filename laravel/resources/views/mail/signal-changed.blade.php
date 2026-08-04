<x-mail::message>
# {{ __('Neues Signal erkannt') }}

{{ __('aKI hat für eine Aktie deiner Strategie einen Signalwechsel erkannt.') }}

**{{ $instrument->name }} ({{ $instrument->symbol }})**

| {{ __('Kennzahl') }} | {{ __('Wert') }} |
|:--|:--|
| {{ __('Signal') }} | **{{ $signal }}** |
| {{ __('Vorheriges Signal') }} | {{ $previousSignal }} |
| {{ __('Signalzeitpunkt') }} | {{ $prediction->prediction_time?->timezone('Europe/Berlin')->format('d.m.Y H:i') }} |
| {{ __('Aktueller Kurs') }} | {{ number_format((float) $prediction->current_price, 2, ',', '.') }} {{ $instrument->currency }} |
| {{ __('KI-Score') }} | {{ number_format((float) $prediction->ai_score, 1, ',', '.') }} / 10 |
| {{ __('Konfidenz') }} | {{ number_format(abs((float) $prediction->confidence) <= 1 ? (float) $prediction->confidence * 100 : (float) $prediction->confidence, 1, ',', '.') }} % |
@if($expectedReturn !== null)
| {{ __('Rendite-Prognose 20 Tage') }} | {{ number_format($expectedReturn, 2, ',', '.') }} % |
@endif

<x-mail::button :url="route('stocks.show', $instrument->symbol)">
{{ __('Analyse öffnen') }}
</x-mail::button>

{{ __('Ausgelöst durch deine Strategie „:name“.', ['name' => $strategy->name]) }}

---
{{ __('Die Inhalte dienen ausschließlich Informations- und Analysezwecken. Sie stellen keine Anlageberatung oder Aufforderung zum Handel dar. Prognosen können fehlerhaft sein und Verluste sind jederzeit möglich.') }}

aKI · aktienKI.com
</x-mail::message>
