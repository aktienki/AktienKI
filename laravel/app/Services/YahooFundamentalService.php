<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fallback for sector/industry when Twelve Data's profile endpoint has no
 * data for a symbol at all ("symbol or figi parameter is missing or
 * invalid") - confirmed to affect roughly a quarter of the active universe,
 * including plainly German companies (e.g. Stabilus SE), not just foreign
 * cross-listings.
 *
 * Yahoo's quoteSummary endpoint requires a session cookie + CSRF "crumb"
 * (no API key) since a 2024 access-tightening; the older unauthenticated
 * v7/quote and v10/quoteSummary-without-crumb calls now both return 401.
 * YahooIndexService's chart endpoint (used for price history) is
 * unaffected by this and needs no crumb.
 */
class YahooFundamentalService
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; AktienKI/1.0)';

    /**
     * Null when Yahoo has no fundamentals for this symbol either, or the
     * session handshake itself failed.
     *
     * @return array{sector: ?string, industry: ?string}|null
     */
    public function assetProfile(string $symbol): ?array
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        $response = Http::withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
            ->withCookies($session['cookies'], 'query1.finance.yahoo.com')
            ->timeout(15)
            ->get('https://query1.finance.yahoo.com/v10/finance/quoteSummary/'.rawurlencode($symbol), [
                'modules' => 'assetProfile',
                'crumb' => $session['crumb'],
            ]);

        $profile = $response->successful()
            ? data_get($response->json(), 'quoteSummary.result.0.assetProfile')
            : null;
        if (! is_array($profile)) {
            return null;
        }

        $sector = trim((string) ($profile['sector'] ?? ''));
        $industry = trim((string) ($profile['industry'] ?? ''));
        if ($sector === '' && $industry === '') {
            return null;
        }

        return ['sector' => $sector !== '' ? $sector : null, 'industry' => $industry !== '' ? $industry : null];
    }

    /**
     * For a foreign-market cross-listing (e.g. a Japanese or Hong Kong
     * company only ever queried here under its German ".DE" ticker),
     * assetProfile() above has nothing - Yahoo doesn't recognize that
     * ticker at all. The ISIN is listing-independent, and Yahoo's search
     * endpoint resolves it straight to the home-market symbol, often
     * already including sector/industry in the same response - no session
     * cookie/crumb needed for this endpoint.
     *
     * @return array{sector: ?string, industry: ?string}|null
     */
    public function assetProfileByIsin(string $isin): ?array
    {
        $isin = trim($isin);
        if ($isin === '') {
            return null;
        }

        $response = Http::withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
            ->timeout(15)
            ->get('https://query1.finance.yahoo.com/v1/finance/search', ['q' => $isin]);
        $quote = $response->successful() ? data_get($response->json(), 'quotes.0') : null;
        if (! is_array($quote)) {
            return null;
        }

        $sector = trim((string) ($quote['sector'] ?? ''));
        $industry = trim((string) ($quote['industry'] ?? ''));
        if ($sector !== '' || $industry !== '') {
            return ['sector' => $sector !== '' ? $sector : null, 'industry' => $industry !== '' ? $industry : null];
        }

        // The search hit didn't carry sector/industry itself - fall back to
        // a full profile lookup under the resolved home-market symbol.
        $homeSymbol = trim((string) ($quote['symbol'] ?? ''));

        return $homeSymbol !== '' ? $this->assetProfile($homeSymbol) : null;
    }

    /**
     * @return array{cookies: array<string, string>, crumb: string}|null
     */
    private function session(): ?array
    {
        return Cache::remember('yahoo_finance_session_v1', now()->addMinutes(30), function (): ?array {
            $cookieResponse = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(10)->get('https://fc.yahoo.com');
            $cookies = collect($cookieResponse->cookies())->mapWithKeys(fn ($cookie) => [$cookie->getName() => $cookie->getValue()])->all();
            if ($cookies === []) {
                return null;
            }

            $crumbResponse = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->withCookies($cookies, 'query1.finance.yahoo.com')
                ->timeout(10)
                ->get('https://query1.finance.yahoo.com/v1/test/getcrumb');
            $crumb = trim((string) $crumbResponse->body());
            if (! $crumbResponse->successful() || $crumb === '' || str_contains($crumb, '<')) {
                return null;
            }

            return ['cookies' => $cookies, 'crumb' => $crumb];
        });
    }
}
