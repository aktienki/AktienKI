<?php

namespace App\Http\Controllers;

use App\Services\TechnicalPriceLevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EtfCertificateController extends Controller
{
    public function __invoke(Request $request, TechnicalPriceLevelService $priceLevels): View
    {
        $defaultTab = $request->routeIs('certificates.index') ? 'certificates' : 'etfs';
        $tab = match ($request->query('tab')) {
            'certificates' => 'certificates',
            'etfs' => 'etfs',
            default => $defaultTab,
        };
        $search = trim((string) $request->query('q'));
        $canViewLeveragedProducts = (string) data_get($request->user()?->meta, 'risk_profile.level', 'normal') === 'risk';
        $productGroup = $canViewLeveragedProducts && in_array($request->query('product_group'), ['investment', 'leverage'], true)
            ? (string) $request->query('product_group')
            : 'investment';
        $underlyingId = $request->integer('underlying') ?: null;
        $type = in_array($request->query('type'), ['discount_certificate', 'bonus_certificate', 'bond'], true)
            ? $request->query('type')
            : null;
        $discountMin = is_numeric($request->query('discount_min')) ? (float) $request->query('discount_min') : null;
        $returnMin = is_numeric($request->query('return_min')) ? (float) $request->query('return_min') : null;
        $returnPaMin = is_numeric($request->query('return_pa_min')) ? (float) $request->query('return_pa_min') : null;
        $maturityFrom = $this->validDate($request->query('maturity_from'));
        $maturityTo = $this->validDate($request->query('maturity_to'));
        $sort = in_array($request->query('sort'), ['underlying', 'product', 'type', 'maturity', 'issuer', 'price', 'discount', 'cap', 'return', 'return_pa', 'updated'], true)
            ? (string) $request->query('sort')
            : 'maturity';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $etfs = collect();
        $certificates = collect();
        $officialCertificates = collect();
        $officialCatalogDate = null;
        $stats = ['etfs' => 0, 'holdings' => 0, 'certificates' => 0, 'underlyings' => 0];

        if (Schema::hasTable('etf_funds')) {
            $etfQuery = DB::table('etf_funds')
                ->where('is_active', true)
                ->where('is_german_tradeable', true)
                ->whereNotNull('german_tradeability_verified_at');

            $stats['etfs'] = (clone $etfQuery)->count();
            $stats['holdings'] = (int) (clone $etfQuery)->sum('current_holding_count');

            if ($tab === 'etfs') {
                $etfs = $etfQuery
                    ->when($search !== '', function ($query) use ($search): void {
                        $term = '%'.mb_strtolower($search).'%';
                        $query->where(fn ($nested) => $nested
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(provider) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(symbol, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(isin, \'\')) LIKE ?', [$term]));
                    })
                    ->orderBy('provider')->orderBy('name')
                    ->paginate(18)->withQueryString();
            }
        }

        if (Schema::hasTable('linked_securities')) {
            $rankedStockPrices = DB::table('price_bars')
                ->where('interval', '1d')
                ->where('close', '>', 0)
                ->select(['instrument_id', 'close'])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument_id ORDER BY bar_time DESC, id DESC) AS price_rank');
            $latestStockPrices = DB::query()
                ->fromSub($rankedStockPrices, 'ranked_stock_price')
                ->where('price_rank', 1)
                ->select(['instrument_id', 'close']);
            $absoluteReturnSql = '((linked_securities.cap / NULLIF(latest_stock_price.close * (1 - linked_securities.discount_percent / 100.0), 0)) - 1) * 100';
            $daysToMaturitySql = DB::connection()->getDriverName() === 'pgsql'
                ? '(linked_securities.maturity_date - CURRENT_DATE)'
                : "(julianday(linked_securities.maturity_date) - julianday(date('now')))";
            // Certificate portals conventionally annualise the maximum return linearly
            // over the remaining term; compounding very short maturities produces
            // misleading four-digit percentages.
            $annualReturnSql = "($absoluteReturnSql) * 365.0 / NULLIF($daysToMaturitySql, 0)";

            $certificateBase = DB::table('linked_securities')
                ->leftJoinSub($latestStockPrices, 'latest_stock_price', fn ($join) => $join
                    ->on('latest_stock_price.instrument_id', '=', 'linked_securities.underlying_instrument_id'))
                ->where('linked_securities.is_active', true)
                ->whereNotNull('linked_securities.german_tradeability_verified_at')
                ->where(fn ($query) => $query->whereNull('maturity_date')->orWhereDate('maturity_date', '>=', today()))
                // Keep uncapped products. For capped products, the cap may be at most 5% below
                // the latest daily underlying price. If no stock price exists, no comparison is possible.
                ->where(fn ($query) => $query
                    ->whereNull('linked_securities.cap')
                    ->orWhereNull('latest_stock_price.close')
                    ->orWhereRaw('linked_securities.cap >= latest_stock_price.close * 0.95'));

            $stats['certificates'] = (clone $certificateBase)->count();
            $stats['underlyings'] = (clone $certificateBase)->distinct()->count('underlying_instrument_id');

            if ($tab === 'certificates') {
                $certificates = $certificateBase
                    ->join('instruments as underlying', 'underlying.id', '=', 'linked_securities.underlying_instrument_id')
                    ->when($underlyingId, fn ($query) => $query->where('linked_securities.underlying_instrument_id', $underlyingId))
                    ->when($type, fn ($query) => $query->where('linked_securities.type', $type))
                    ->when($discountMin !== null, fn ($query) => $query->where('linked_securities.discount_percent', '>=', $discountMin))
                    ->when($maturityFrom, fn ($query) => $query->whereDate('linked_securities.maturity_date', '>=', $maturityFrom))
                    ->when($maturityTo, fn ($query) => $query->whereDate('linked_securities.maturity_date', '<=', $maturityTo))
                    ->when($returnMin !== null, fn ($query) => $query
                        ->whereNotNull('linked_securities.cap')->whereNotNull('linked_securities.discount_percent')
                        ->whereNotNull('latest_stock_price.close')->whereRaw("$absoluteReturnSql >= ?", [$returnMin]))
                    ->when($returnPaMin !== null, fn ($query) => $query
                        ->whereNotNull('linked_securities.cap')->whereNotNull('linked_securities.discount_percent')
                        ->whereNotNull('latest_stock_price.close')->whereNotNull('linked_securities.maturity_date')
                        ->whereRaw("$daysToMaturitySql > 0")->whereRaw("$annualReturnSql >= ?", [$returnPaMin]))
                    ->when($search !== '', function ($query) use ($search): void {
                        $term = '%'.mb_strtolower($search).'%';
                        $query->where(fn ($nested) => $nested
                            ->whereRaw('LOWER(linked_securities.name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(linked_securities.issuer, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(linked_securities.isin) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(linked_securities.wkn, \'\')) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(underlying.name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(underlying.symbol) LIKE ?', [$term]));
                    })
                    ->select('linked_securities.*', 'underlying.name as underlying_name', 'underlying.symbol as underlying_symbol')
                    ->selectRaw('latest_stock_price.close as underlying_price')
                    ->selectRaw("$absoluteReturnSql as absolute_return_percent")
                    ->selectRaw("CASE WHEN linked_securities.maturity_date IS NOT NULL AND $daysToMaturitySql > 0 THEN $annualReturnSql END as annual_return_percent")
                    ->when($sort === 'underlying', fn ($query) => $query->orderBy('underlying.name', $direction))
                    ->when($sort === 'product', fn ($query) => $query->orderBy('linked_securities.name', $direction))
                    ->when($sort === 'type', fn ($query) => $query->orderBy('linked_securities.type', $direction))
                    ->when($sort === 'maturity', fn ($query) => $query->orderBy('linked_securities.maturity_date', $direction))
                    ->when($sort === 'issuer', fn ($query) => $query->orderBy('linked_securities.issuer', $direction))
                    ->when($sort === 'price', fn ($query) => $query->orderBy('linked_securities.price', $direction))
                    ->when($sort === 'discount', fn ($query) => $query->orderBy('linked_securities.discount_percent', $direction))
                    ->when($sort === 'cap', fn ($query) => $query->orderBy('linked_securities.cap', $direction))
                    ->when($sort === 'return', fn ($query) => $query->orderByRaw("$absoluteReturnSql $direction"))
                    ->when($sort === 'return_pa', fn ($query) => $query->orderByRaw("$annualReturnSql $direction"))
                    ->when($sort === 'updated', fn ($query) => $query->orderBy('linked_securities.quote_at', $direction))
                    ->orderBy('linked_securities.id')
                    ->paginate(18)->withQueryString();

                $resistanceByInstrument = $certificates->getCollection()
                    ->where('type', 'discount_certificate')
                    ->pluck('underlying_instrument_id')->unique()
                    ->mapWithKeys(fn ($instrumentId) => [(int) $instrumentId => $priceLevels->levels((int) $instrumentId)]);
                $certificates->getCollection()->each(function ($certificate) use ($resistanceByInstrument): void {
                    $levels = $resistanceByInstrument->get((int) $certificate->underlying_instrument_id, []);
                    $certificate->resistance = $levels['resistance'] ?? null;
                    $certificate->broken_resistance = $levels['broken_resistance'] ?? null;
                });
            }
        }

        if ($tab === 'certificates' && Schema::hasTable('deutsche_boerse_certificates')) {
            $officialBase = DB::table('deutsche_boerse_certificates')
                ->when(! $canViewLeveragedProducts || $productGroup === 'investment', fn ($query) => $query->where('warrant_type', 'CERTIFICATE'))
                ->when($canViewLeveragedProducts && $productGroup === 'leverage', fn ($query) => $query->whereIn('warrant_type', ['CALL', 'PUT', 'RANGE', 'OTHER']))
                ->when($maturityFrom, fn ($query) => $query->whereDate('maturity_date', '>=', $maturityFrom))
                ->when($maturityTo, fn ($query) => $query->whereDate('maturity_date', '<=', $maturityTo))
                ->when($search !== '', function ($query) use ($search): void {
                    $normalized = mb_strtolower($search);
                    $query->where(function ($nested) use ($search, $normalized): void {
                        $nested->whereRaw("to_tsvector('simple', coalesce(instrument_name,'') || ' ' || coalesce(isin,'') || ' ' || coalesce(wkn,'') || ' ' || coalesce(specialist,'')) @@ plainto_tsquery('simple', ?)", [$search])
                            ->orWhereRaw('LOWER(isin) = ?', [$normalized])
                            ->orWhereRaw('LOWER(COALESCE(wkn, \'\')) = ?', [$normalized]);
                    });
                });

            $officialCatalogDate = Cache::remember('deutsche-boerse-certificates:source-date', 3600, fn () =>
                DB::table('deutsche_boerse_certificates')->max('source_date'));
            $visibilityKey = $canViewLeveragedProducts ? $productGroup : 'investment';
            $stats['certificates'] = Cache::remember("deutsche-boerse-certificates:count:{$visibilityKey}", 3600, fn () =>
                (clone $officialBase)->count());
            $stats['underlyings'] = Cache::remember("deutsche-boerse-certificates:underlyings:{$visibilityKey}", 3600, fn () =>
                (clone $officialBase)->whereNotNull('underlying_code')->distinct()->count('underlying_code'));

            $officialCertificates = $officialBase
                ->orderByRaw('maturity_date IS NULL')
                ->orderBy('maturity_date')
                ->orderBy('id')
                ->paginate(30, ['*'], 'catalog_page')
                ->withQueryString();
        }

        return view('securities.index', compact('tab', 'search', 'type', 'underlyingId', 'discountMin', 'returnMin', 'returnPaMin', 'maturityFrom', 'maturityTo', 'sort', 'direction', 'etfs', 'certificates', 'officialCertificates', 'officialCatalogDate', 'productGroup', 'canViewLeveragedProducts', 'stats'));
    }

    private function validDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
