<div class="product-scene" aria-label="{{ app()->getLocale() === 'en' ? 'Animated original view from the AktienKI dashboard' : 'Animierte Originalansicht aus dem AktienKI-Dashboard' }}">
    <article class="product-card product-portfolio-card">
        <div class="product-card-head">
            <div>
                <span class="product-kicker">Aktives Portfolio</span>
                <div class="product-count"><strong>10</strong><span>Aktien</span></div>
                <small>RISIKO-SCORE · Ø 2,4</small>
            </div>
            <div class="product-market-actions">
                <span>Trend <b>→</b></span><span>Stimmung <b>→</b></span>
            </div>
        </div>
        <div class="product-rating">
            <div class="product-rating-head"><span>KI-Rating</span><span>5− bis 1+</span></div>
            <div class="product-rating-track">
                @foreach([
                    ['0','SELL','5−/5+','sell'],
                    ['1','WAIT','4−/4+','wait'],
                    ['5','HOLD','3−/3+','hold'],
                    ['2','WATCH','2−/2+','watch'],
                    ['2','BUY','1−/1+','buy'],
                ] as [$count,$label,$range,$tone])
                    <span class="product-rating-marker is-{{ $tone }}" style="--marker-index:{{ $loop->index }}">
                        <b>{{ $count }}</b><i></i><em>{{ $label }} <small>{{ $range }}</small></em>
                    </span>
                @endforeach
            </div>
        </div>
    </article>

    <article class="product-card product-cockpit-card">
        <div class="product-cockpit-head">
            <span class="product-signal-icon" aria-hidden="true">≋</span>
            <span><small>PRO · 5 HANDELSTAGE</small><strong>Signal-Cockpit</strong><em><b>3 BUY</b> <i>1 SELL</i> Ø KI 7,4 · Ø Risiko 8%</em></span>
            <span class="product-all">Alle →</span>
        </div>
        <div class="product-signal-table">
            <div class="product-signal-columns"><span>Aktie / Wechsel</span><span>Datum</span><span>5T</span><span>10T</span><span>15T</span><span>20T</span></div>
            @foreach([
                ['🇺🇸','KLA Corporation','WATCH → BUY','22.08.','+2,8%','+4,6%','+6,2%','+9,1%'],
                ['🇩🇪','SAP SE','HOLD → BUY','21.08.','+1,4%','+3,9%','+5,1%','+7,6%'],
                ['🇳🇱','ASML Holding N.V.','WATCH → BUY','20.08.','−0,4%','+2,2%','+4,8%','+8,7%'],
            ] as $row)
                <div class="product-signal-row" style="--row-index:{{ $loop->index }}">
                    <span class="product-stock"><b>{{ $row[0] }} {{ $row[1] }}</b><small>{{ $row[2] }} <i>KI 7,{{ 8 - $loop->index }}</i></small></span>
                    <time>{{ $row[3] }}</time>
                    @foreach(array_slice($row,4) as $value)
                        <span class="product-horizon {{ str_starts_with($value, '−') ? 'is-negative' : '' }}" style="--chip-index:{{ $loop->index }}">{{ $value }}</span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </article>
</div>
