<?php

namespace App\Services;

use App\Models\Instrument;
use Illuminate\Support\Facades\Http;

class StockIconService
{
    private const ALLOWED_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    public function findCached(Instrument $instrument): ?string
    {
        $directory = public_path('assets/stock-icons');

        foreach (array_unique(self::ALLOWED_TYPES) as $extension) {
            $existing = $directory.'/'.$instrument->id.'.'.$extension;
            if (is_file($existing)) {
                return $existing;
            }
        }

        return null;
    }

    public function findOrDownload(Instrument $instrument): ?string
    {
        if ($existing = $this->findCached($instrument)) {
            return $existing;
        }

        $directory = public_path('assets/stock-icons');
        $missingMarker = $directory.'/'.$instrument->id.'.missing';
        if (is_file($missingMarker) && filemtime($missingMarker) > now()->subDays(7)->getTimestamp()) {
            return null;
        }

        $website = $instrument->meta['website'] ?? null;
        $host = is_string($website) ? parse_url($website, PHP_URL_HOST) : null;

        if (! $this->isSafeHost($host)) {
            return null;
        }

        try {
            $candidates = [
                'https://'.$host.'/favicon.ico',
                'https://'.$host.'/favicon.png',
                'https://'.$host.'/apple-touch-icon.png',
            ];

            $pageResponse = Http::withHeaders([
                'Accept' => 'text/html',
                'User-Agent' => 'AktienKI Stock Icon Cache/1.0',
            ])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->timeout(6)
                ->get($website);

            if ($pageResponse->successful() && strlen($pageResponse->body()) <= 2_000_000) {
                preg_match_all(
                    '/<link\b[^>]*\brel=["\'][^"\']*(?:icon|apple-touch-icon)[^"\']*["\'][^>]*\bhref=["\']([^"\']+)["\'][^>]*>/i',
                    $pageResponse->body(),
                    $matches,
                );

                foreach ($matches[1] ?? [] as $href) {
                    $resolved = $this->resolveIconUrl($href, $host);
                    if ($resolved) {
                        array_unshift($candidates, $resolved);
                    }
                }
            }

            foreach (array_unique($candidates) as $candidate) {
                $saved = $this->download($candidate, $directory, (int) $instrument->id);
                if ($saved) {
                    if (is_file($missingMarker)) {
                        unlink($missingMarker);
                    }

                    return $saved;
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ((is_dir($directory) || mkdir($directory, 0755, true)) && ! is_file($missingMarker)) {
            touch($missingMarker);
        }

        return null;
    }

    public function refresh(Instrument $instrument): ?string
    {
        $directory = public_path('assets/stock-icons');
        $backups = [];

        foreach (array_unique(self::ALLOWED_TYPES) as $extension) {
            $path = $directory.'/'.$instrument->id.'.'.$extension;

            if (is_file($path)) {
                $backup = $path.'.refresh-backup';
                if (is_file($backup)) {
                    unlink($backup);
                }
                rename($path, $backup);
                $backups[$path] = $backup;
            }
        }

        $missingMarker = $directory.'/'.$instrument->id.'.missing';
        if (is_file($missingMarker)) {
            unlink($missingMarker);
        }

        $downloaded = $this->findOrDownload($instrument);

        if ($downloaded) {
            foreach ($backups as $backup) {
                if (is_file($backup)) {
                    unlink($backup);
                }
            }

            return $downloaded;
        }

        foreach ($backups as $path => $backup) {
            if (is_file($backup)) {
                rename($backup, $path);
            }
        }

        return null;
    }

    private function download(string $url, string $directory, int $instrumentId): ?string
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        if (! str_starts_with($url, 'https://') || ! $this->isSafeHost($urlHost)) {
            return null;
        }

        $response = Http::withHeaders([
            'Accept' => 'image/png,image/webp,image/x-icon,image/jpeg,image/gif',
            'User-Agent' => 'AktienKI Stock Icon Cache/1.0',
        ])
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->timeout(6)
            ->get($url);
        $body = $response->body();
        $imageInfo = $body !== '' && strlen($body) <= 1_000_000
            ? @getimagesizefromstring($body)
            : false;
        $detectedType = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

        if (! $response->successful() || ! isset(self::ALLOWED_TYPES[$detectedType])) {
            return null;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return null;
        }

        $path = $directory.'/'.$instrumentId.'.'.self::ALLOWED_TYPES[$detectedType];

        return file_put_contents($path, $body, LOCK_EX) === false ? null : $path;
    }

    private function resolveIconUrl(string $href, string $host): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5);

        if (str_starts_with($href, '//')) {
            return 'https:'.$href;
        }

        if (str_starts_with($href, '/')) {
            return 'https://'.$host.$href;
        }

        if (str_starts_with($href, 'https://')) {
            return $href;
        }

        if (! str_contains($href, '://') && $href !== '') {
            return 'https://'.$host.'/'.ltrim($href, '/');
        }

        return null;
    }

    private function isSafeHost(mixed $host): bool
    {
        return is_string($host)
            && $host !== ''
            && strlen($host) <= 253
            && filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host) === 1;
    }
}
