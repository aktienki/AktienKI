@php
    $klaLanding = view('landing.kla', compact('quote', 'score', 'betaTesterCount', 'betaTesterLimit'))->render();

    echo str_replace(
        [
            route('landing.kla.quote'),
            'KLA Corporation',
            'KLAC',
            'KLA-Analyse',
            'KLA Aktienanalyse',
            'KLA als Beispiel',
            'für KLA',
            'Bei KLA',
            'von KLA',
            'KLA.',
            'KLA Kauf-Chance',
            'Strukturelle Nachfrage durch Halbleiterfertigung und Prozesskontrolle.',
            'Zyklische Investitionen der Halbleiterindustrie können Ergebnisse stark beeinflussen.',
        ],
        [
            route('landing.nvidia.quote'),
            'NVIDIA Corporation',
            'NVDA',
            'NVIDIA-Analyse',
            'NVIDIA Aktienanalyse',
            'NVIDIA als Beispiel',
            'für NVIDIA',
            'Bei NVIDIA',
            'von NVIDIA',
            'NVIDIA.',
            'NVIDIA Kauf-Chance',
            'Strukturelle Nachfrage nach KI-Beschleunigern, Rechenzentren und Hochleistungscomputing.',
            'Hohe Bewertung, Halbleiterzyklen und Exportbeschränkungen können die Kursentwicklung stark beeinflussen.',
        ],
        $klaLanding,
    );
@endphp
