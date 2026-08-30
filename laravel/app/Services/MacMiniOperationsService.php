<?php

namespace App\Services;

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class MacMiniOperationsService
{
    public function status(): array
    {
        $applicationDatabase = $this->applicationDatabaseStatus();
        $servingDatabase = $this->servingDatabaseStatus();
        $databaseStats = $this->databaseStats();
        $macMini = config('operations.mac_mini');
        $remoteCommand = str_replace(
            [
                '__PROJECT_PATH__',
                '__DATABASE_NAME__',
                '__DATABASE_PORT__',
                '__DATABASE_SOCKET__',
                '__CONTROL_PLANE_LABEL__',
                '__SERVER_HOST__',
                '__SERVER_IDENTITY_FILE__',
            ],
            [
                escapeshellarg((string) $macMini['project_path']),
                escapeshellarg((string) $macMini['pipeline_database_name']),
                (string) ((int) $macMini['pipeline_database_port']),
                escapeshellarg((string) $macMini['pipeline_database_socket']),
                escapeshellarg((string) $macMini['control_plane_label']),
                escapeshellarg((string) $macMini['server_host']),
                escapeshellarg((string) $macMini['server_identity_file']),
            ],
            <<<'BASH'
set +e
project=__PROJECT_PATH__
database_name=__DATABASE_NAME__
database_port=__DATABASE_PORT__
database_socket=__DATABASE_SOCKET__
control_plane_label=__CONTROL_PLANE_LABEL__
server_host=__SERVER_HOST__
server_identity_file=__SERVER_IDENTITY_FILE__
platform="$(uname -s 2>/dev/null || echo unknown)"
log_file="$project/.local/control-plane/server.log"

echo '---STATUS---'
echo "platform=$platform"
if [ "$platform" = 'Darwin' ]; then
  echo "hostname=$(scutil --get ComputerName 2>/dev/null || hostname)"
  echo "model=$(system_profiler SPHardwareDataType 2>/dev/null | awk -F': ' '/Model Name/{print $2; exit}')"
  echo "model_identifier=$(sysctl -n hw.model 2>/dev/null)"
  echo "chip=$(system_profiler SPHardwareDataType 2>/dev/null | awk -F': ' '/Chip/{print $2; exit}')"
  echo "os_version=$(sw_vers -productVersion 2>/dev/null)"
  echo "uptime=$(uptime 2>/dev/null | sed 's/^[[:space:]]*//')"
  echo "load=$(sysctl -n vm.loadavg 2>/dev/null | awk '{print $2" "$3" "$4}')"
  echo "cpu=$(LC_ALL=C top -l 2 -n 0 2>/dev/null | awk '/CPU usage/{idle=$7} END{gsub("%", "", idle); if (idle != "") printf "%.1f%%", 100-idle}')"
  ram_total_bytes="$(sysctl -n hw.memsize 2>/dev/null)"
  ram_total_gb="$(awk -v bytes="$ram_total_bytes" 'BEGIN {if (bytes > 0) printf "%.0f", bytes/1073741824}')"
  ram_used="$(memory_pressure -Q 2>/dev/null | awk -F': ' '/System-wide memory free percentage/{gsub("%", "", $2); printf "%.1f%%", 100-$2; exit}')"
  echo "ram=${ram_total_gb:-—} GB · ${ram_used:-—}"
  echo "cpu_total=$(sysctl -n hw.ncpu 2>/dev/null)"
else
  echo "hostname=$(hostname 2>/dev/null)"
fi

echo "disk=$(df -H "$project" 2>/dev/null | awk 'NR==2 {print $5}')"
echo "pipeline_version=$(awk -F' = ' '/^version =/{gsub(/\"/, "", $2); print $2; exit}' "$project/config/worker.toml" 2>/dev/null)"
echo "git_revision=$(git -C "$project" rev-parse --short HEAD 2>/dev/null)"
echo "worker_processes=$(pgrep -f '[a]ktienki_pipeline.*worker' 2>/dev/null | wc -l | tr -d ' ')"

if launchctl print "gui/$(id -u)/$control_plane_label" >/dev/null 2>&1; then
  echo 'control_plane=active'
  echo "control_plane_pid=$(launchctl print "gui/$(id -u)/$control_plane_label" 2>/dev/null | awk -F' = ' '/^[[:space:]]*pid =/{print $2; exit}')"
else
  echo 'control_plane=inactive'
  echo 'control_plane_pid=—'
fi

psql_bin="$(command -v psql 2>/dev/null)"
if [ -x /opt/homebrew/opt/postgresql@17/bin/psql ]; then
  psql_bin=/opt/homebrew/opt/postgresql@17/bin/psql
fi
pg_isready_bin="$(dirname "$psql_bin")/pg_isready"
for state in queued claimed data_ready trained walk_forward_done calibrated filters_evaluated artifact_staged verified released documented failed; do
  echo "jobs_${state}=0"
done
if [ -x "$pg_isready_bin" ] && "$pg_isready_bin" -q -h "$database_socket" -p "$database_port"; then
  echo 'pipeline_database=active'
  echo "pipeline_database_port=$database_port"
  if [ -x "$psql_bin" ]; then
    job_counts="$("$psql_bin" -h "$database_socket" -p "$database_port" -d "$database_name" -At -F '|' -c 'SELECT state::text, count(*) FROM pipeline_jobs GROUP BY state ORDER BY state' 2>/dev/null)"
    while IFS='|' read -r state count; do
      if [ -n "$state" ]; then echo "jobs_${state}=$count"; fi
    done <<EOF
$job_counts
EOF
    worker_record="$("$psql_bin" -h "$database_socket" -p "$database_port" -d "$database_name" -At -F '|' -c "SELECT name, status::text, COALESCE(to_char(last_heartbeat_at, 'YYYY-MM-DD HH24:MI:SSOF'), '') FROM worker_nodes WHERE name = 'macmini-canary-01' ORDER BY registered_at DESC LIMIT 1" 2>/dev/null)"
    IFS='|' read -r worker_name worker_status worker_heartbeat <<EOF
$worker_record
EOF
    echo "worker_name=${worker_name:-macmini-canary-01}"
    echo "worker_status=${worker_status:-unknown}"
    echo "worker_heartbeat=${worker_heartbeat:-—}"
  fi
else
  echo 'pipeline_database=inactive'
  echo 'pipeline_database_port=—'
  echo 'worker_name=macmini-canary-01'
  echo 'worker_status=unknown'
  echo 'worker_heartbeat=—'
fi

if [ -r "$server_identity_file" ] && /usr/bin/ssh -i "$server_identity_file" -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=yes "root@$server_host" true >/dev/null 2>&1; then
  echo 'server_connection=active'
else
  echo 'server_connection=inactive'
fi

echo '---ERRORS---'
test -n "$log_file" && grep -E 'ERROR|Error|Traceback|FAILED|Connection refused' "$log_file" 2>/dev/null | tail -35
echo '---LOG---'
test -n "$log_file" && tail -70 "$log_file" 2>/dev/null
exit 0
BASH
        );
        $result = $this->ssh($remoteCommand, 25);

        if (! $result->successful()) {
            return $this->unreachableStatus(
                trim($result->errorOutput() ?: $result->output()) ?: __('Mac mini nicht erreichbar.'),
                $applicationDatabase,
                $servingDatabase,
                $databaseStats,
            );
        }

        $sections = $this->sections($result->output());
        $metrics = collect(preg_split('/\R/', trim($sections['STATUS'] ?? '')))
            ->filter(fn (string $line): bool => str_contains($line, '='))
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = explode('=', $line, 2);

                return [trim($key) => trim($value)];
            })->all();

        if (($metrics['platform'] ?? null) !== 'Darwin') {
            return $this->unreachableStatus(
                __('Der konfigurierte Rechner ist nicht der AktienKI-Mac-mini.'),
                $applicationDatabase,
                $servingDatabase,
                $databaseStats,
            );
        }

        return [
            'reachable' => true,
            'error' => null,
            'metrics' => $metrics,
            // Keep `database` as a backwards-compatible alias for the serving database.
            'database' => $servingDatabase,
            'application_database' => $applicationDatabase,
            'serving_database' => $servingDatabase,
            'database_stats' => $databaseStats,
            'errors' => $this->lines($sections['ERRORS'] ?? ''),
            'log' => $this->lines($sections['LOG'] ?? ''),
            'runs' => $this->walkForwardRuns(),
        ];
    }

    public function restartApplicationServices(): array
    {
        Artisan::call('queue:restart');

        $phpService = 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-fpm';
        $result = Process::timeout(15)->run([
            '/usr/bin/sudo', '-n', '/usr/bin/systemctl', 'reload', $phpService, 'nginx',
        ]);

        Log::notice('Admin requested application service restart.', [
            'successful' => $result->successful(),
            'services' => [$phpService, 'nginx'],
        ]);

        return $this->actionResult($result, __('Die Anwendungsdienste wurden neu geladen.'));
    }

    public function restartRemoteServer(): array
    {
        $result = Process::timeout(5)->run([
            '/usr/bin/sudo', '-n', '/usr/bin/systemd-run',
            '--on-active=3s',
            '/usr/bin/systemctl', 'reboot',
        ]);

        Log::warning('Admin scheduled a remote server reboot.', [
            'successful' => $result->successful(),
        ]);

        return $this->actionResult($result, __('Der Remote-Server wird in wenigen Sekunden neu gestartet.'));
    }

    private function applicationDatabaseStatus(): array
    {
        return $this->databaseStatus((string) config('database.default'));
    }

    private function servingDatabaseStatus(): array
    {
        return $this->databaseStatus('serving');
    }

    private function databaseStatus(string $connection): array
    {
        try {
            $database = DB::connection($connection)->selectOne(<<<'SQL'
SELECT current_database() AS name,
       COALESCE(inet_server_addr()::text, 'lokal') AS server,
       inet_server_port() AS port
SQL);

            return [
                'connected' => true,
                'connection' => $connection,
                'name' => $database->name ?? null,
                'server' => $database->server ?? null,
                'host' => $database->server ?? null,
                'port' => $database->port ?? null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'connected' => false,
                'connection' => $connection,
                'name' => null,
                'server' => null,
                'host' => null,
                'port' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function databaseStats(): array
    {
        $currentStocks = $this->currentStockQualityStats();
        $latestPrediction = $this->latestPredictionStats();
        $oldestModel = $this->oldestServingModel();

        try {
            $subscriptionCounts = DB::table('tariff_plans as plan')
                ->leftJoin('users as user', 'user.tariff_plan_id', '=', 'plan.id')
                ->whereNull('plan.deleted_at')
                ->groupBy('plan.id', 'plan.code', 'plan.name', 'plan.sort_order')
                ->orderBy('plan.sort_order')
                ->get([
                    'plan.code',
                    'plan.name',
                    DB::raw('COUNT(user.id) AS users'),
                ])
                ->map(fn (object $plan): array => [
                    'code' => (string) $plan->code,
                    'name' => (string) $plan->name,
                    'users' => (int) $plan->users,
                ])->all();

            return [
                'users' => DB::table('users')->count(),
                'active_users' => DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
                    ->distinct()
                    ->count('user_id'),
                'walk_forward_stocks' => DB::table('walk_forward_backtest_trades as trade')
                    ->join('walk_forward_backtest_runs as run', 'run.id', '=', 'trade.run_id')
                    ->whereIn('run.status', ['completed', 'completed_with_errors'])
                    ->distinct()
                    ->count('trade.instrument_id'),
                'oldest_model_symbol' => $oldestModel['symbol'],
                'oldest_model_trained_at' => $oldestModel['created_at'],
                'subscription_counts' => $subscriptionCounts,
                'current_stocks' => $currentStocks,
                'latest_prediction' => $latestPrediction,
            ];
        } catch (Throwable) {
            return [
                'users' => null,
                'active_users' => null,
                'walk_forward_stocks' => null,
                'oldest_model_symbol' => $oldestModel['symbol'],
                'oldest_model_trained_at' => $oldestModel['created_at'],
                'subscription_counts' => [],
                'current_stocks' => $currentStocks,
                'latest_prediction' => $latestPrediction,
            ];
        }
    }

    private function currentStockQualityStats(): array
    {
        $empty = [
            'available' => false,
            'total' => null,
            'classified' => null,
            'latest_calculated_at' => null,
            'source' => 'serving_active_models',
            'quality_counts' => [
                'quality' => 0,
                'solid' => 0,
                'basic' => 0,
                'unqualified' => 0,
                'unclassified' => 0,
            ],
        ];

        try {
            $stocks = DB::connection('serving')
                ->table('serving_active_models as active_model')
                ->join('serving_releases as release', 'release.id', '=', 'active_model.release_id')
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'active_model.instrument_id')
                ->where('instrument.is_active', true)
                ->get([
                    'instrument.id',
                    'instrument.is_active',
                    'instrument.is_tradeable',
                    'release.quality_class',
                    'release.created_at',
                ]);

            $counts = $empty['quality_counts'];
            foreach ($stocks as $stock) {
                $counts[$this->stockQualityClass($stock->quality_class)]++;
            }

            return [
                'available' => true,
                'total' => $stocks->count(),
                'classified' => $stocks->count() - $counts['unclassified'],
                'active_instruments' => $stocks->where('is_active', true)->count(),
                'tradeable_instruments' => $stocks->where('is_tradeable', true)->count(),
                'latest_calculated_at' => $stocks->pluck('created_at')->filter()->sortDesc()->first(),
                'source' => 'serving_active_models',
                'quality_counts' => $counts,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function stockQualityClass(mixed $qualityClass): string
    {
        return match (strtolower(trim((string) $qualityClass))) {
            'quality' => 'quality',
            'solid' => 'solid',
            'basic' => 'basic',
            'unqualified', 'not_qualified', 'underperform', 'observation' => 'unqualified',
            default => 'unclassified',
        };
    }

    private function oldestServingModel(): array
    {
        try {
            $model = DB::connection('serving')
                ->table('serving_active_models as active_model')
                ->join('serving_releases as release', 'release.id', '=', 'active_model.release_id')
                ->join('serving_instruments as instrument', 'instrument.id', '=', 'active_model.instrument_id')
                ->where('instrument.is_active', true)
                ->orderBy('release.created_at')
                ->orderBy('instrument.id')
                ->first(['instrument.symbol', 'release.created_at']);

            return [
                'symbol' => $model?->symbol,
                'created_at' => $model?->created_at,
            ];
        } catch (Throwable) {
            return ['symbol' => null, 'created_at' => null];
        }
    }

    private function latestPredictionStats(): array
    {
        $empty = [
            'available' => false,
            'successful' => null,
            'created_at' => null,
            'prediction_time' => null,
            'rows' => 0,
            'stocks' => 0,
            'horizons' => 0,
            'eligible' => 0,
            'complete' => 0,
            'missing' => 0,
            'coverage_percent' => null,
            'minimum_coverage_percent' => 95.0,
            'source' => 'serving_prediction_batches',
            'status' => null,
        ];

        try {
            $connection = DB::connection('serving');
            $latest = $connection->table('serving_prediction_batches')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            if (! $latest) {
                return $empty;
            }

            $batch = $connection->table('serving_predictions')
                ->where('batch_id', $latest->id)
                ->selectRaw('COUNT(*) AS rows')
                ->selectRaw('COUNT(DISTINCT instrument_id) AS stocks')
                ->selectRaw('COUNT(DISTINCT horizon) AS horizons')
                ->first();

            $eligible = (int) ($latest->expected_count ?? 0);
            $complete = (int) ($latest->completed_count ?? 0);
            $failed = (int) ($latest->failed_count ?? 0);
            $coveragePercent = $eligible > 0 ? round(($complete / $eligible) * 100, 1) : null;
            $successful = (string) $latest->status === 'complete'
                && $eligible > 0
                && $complete >= $eligible
                && $failed === 0;

            return [
                'available' => true,
                'successful' => $successful,
                'created_at' => $latest->finished_at ?? $latest->started_at,
                'prediction_time' => $latest->calculation_date,
                'rows' => (int) ($batch->rows ?? 0),
                'stocks' => (int) ($batch->stocks ?? 0),
                'horizons' => (int) ($batch->horizons ?? 0),
                'eligible' => $eligible,
                'complete' => $complete,
                'missing' => max(0, $eligible - $complete),
                'coverage_percent' => $coveragePercent,
                'minimum_coverage_percent' => 95.0,
                'source' => 'serving_prediction_batches',
                'status' => (string) $latest->status,
                'failed' => $failed,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function walkForwardRuns(): array
    {
        try {
            return DB::table('walk_forward_backtest_runs as run')
                ->orderByDesc('run.id')
                ->limit(12)
                ->get(['run.id', 'run.status', 'run.horizon_days', 'run.started_at', 'run.finished_at', 'run.error_message'])
                ->map(function (object $run): array {
                    $run->stocks = DB::table('walk_forward_backtest_trades')
                        ->where('run_id', $run->id)->distinct('instrument_id')->count('instrument_id');
                    $run->trades = DB::table('walk_forward_backtest_trades')->where('run_id', $run->id)->count();

                    return (array) $run;
                })->all();
        } catch (Throwable $exception) {
            return [['status' => 'unavailable', 'error_message' => $exception->getMessage()]];
        }
    }

    private function ssh(string $remoteCommand, int $timeout): ProcessResult
    {
        $macMini = config('operations.mac_mini');
        $identityFile = $this->runtimeIdentityFile((string) $macMini['identity_file']);
        $arguments = [
            '/usr/bin/ssh',
            '-p', (string) $macMini['port'],
            '-i', $identityFile,
        ];

        $knownHostsFile = (string) ($macMini['known_hosts_file'] ?? '');
        if ($knownHostsFile !== '') {
            array_push($arguments, '-o', 'UserKnownHostsFile='.$knownHostsFile);
        }

        $hostKeyAlias = (string) ($macMini['host_key_alias'] ?? '');
        if ($hostKeyAlias !== '') {
            array_push($arguments, '-o', 'HostKeyAlias='.$hostKeyAlias);
        }

        $controlPath = (string) ($macMini['control_path'] ?? '');
        if ($controlPath !== '' && file_exists($controlPath)) {
            array_push($arguments, '-o', 'ControlMaster=no', '-o', 'ControlPath='.$controlPath);
        }

        array_push(
            $arguments,
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=6',
            '-o', 'StrictHostKeyChecking=yes',
            $macMini['user'].'@'.$macMini['host'],
            '/bin/bash -lc '.escapeshellarg($remoteCommand),
        );

        return Process::timeout($timeout)->run($arguments);
    }

    private function runtimeIdentityFile(string $source): string
    {
        if ($source === '' || ! is_readable($source)) {
            return $source;
        }

        $uid = function_exists('posix_geteuid') ? (int) posix_geteuid() : 0;
        $directory = rtrim(sys_get_temp_dir(), '/').'/aktienki-operations-'.$uid;
        $target = $directory.'/mac-mini-identity';
        $contents = file_get_contents($source);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        if (! is_file($target) || file_get_contents($target) !== $contents) {
            file_put_contents($target, $contents, LOCK_EX);
        }
        chmod($directory, 0700);
        chmod($target, 0600);

        return $target;
    }

    private function sections(string $output): array
    {
        $sections = [];
        $current = null;
        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^---([A-Z]+)---$/', $line, $match)) {
                $current = $match[1];
                $sections[$current] = '';
                continue;
            }
            if ($current !== null) {
                $sections[$current] .= $line."\n";
            }
        }

        return $sections;
    }

    private function lines(string $content): array
    {
        return array_values(array_filter(array_map('rtrim', preg_split('/\R/', trim($content)))));
    }

    private function unreachableStatus(
        string $error,
        array $applicationDatabase,
        array $servingDatabase,
        array $databaseStats,
    ): array
    {
        return [
            'reachable' => false,
            'error' => $error,
            'metrics' => [],
            'database' => $servingDatabase,
            'application_database' => $applicationDatabase,
            'serving_database' => $servingDatabase,
            'database_stats' => $databaseStats,
            'errors' => [],
            'log' => [],
            'runs' => $this->walkForwardRuns(),
        ];
    }

    private function actionResult(ProcessResult $result, string $successMessage): array
    {
        return [
            'successful' => $result->successful(),
            'message' => $result->successful()
                ? $successMessage
                : (trim($result->errorOutput() ?: $result->output()) ?: __('Aktion fehlgeschlagen.')),
        ];
    }
}
