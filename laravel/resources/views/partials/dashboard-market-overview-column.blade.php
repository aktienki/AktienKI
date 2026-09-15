                <div id="dashboard-middle-column" data-dashboard-card="market" data-dashboard-size="{{ $dashboardCardSize('market') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('market') }}" class="dashboard-bento-market dashboard-market-overview-grid min-h-0 sm:col-span-2 {{ $dashboardMarketVisible ? '' : 'hidden' }}">
                    @include('partials.dashboard-market-overview-card')
                    @include('partials.dashboard-daily-tips-card')
                </div>
