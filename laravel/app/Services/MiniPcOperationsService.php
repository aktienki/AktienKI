<?php

namespace App\Services;

use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class MiniPcOperationsService
{
    public function status(): array
    {
        $databaseStats = $this->databaseStats();
        $result = $this->ssh(<<<'BASH'
set +e
project='/home/akiadmin/projects/ml/AktienKI-Python-Engine'
echo '---STATUS---'
echo "hostname=$(hostname)"
echo "uptime=$(uptime -p 2>/dev/null || true)"
echo "load=$(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null || true)"
echo "cpu=$(LC_ALL=C top -bn1 2>/dev/null | awk '/%Cpu/{printf "%.1f%%", 100-$8; exit}')"
echo "ram=$(free -m 2>/dev/null | awk '/^Mem:/{printf "%.1f / %.1f GB · %.1f%%", $3/1024, $2/1024, ($3/$2)*100}')"
echo "cpu_total=$(lscpu -p=CORE 2>/dev/null | grep -v '^#' | sort -u | wc -l)"
echo "cpu_reserved=$(cat /home/akiadmin/.config/aktienki-cpu-reserve 2>/dev/null || echo 4)"
echo "database_tunnel=$(systemctl --user is-active aktienki-postgres-tunnel.service 2>/dev/null || true)"
echo "database_port=$(ss -ltn 2>/dev/null | grep -c ':25432 ')"
echo "vscode_tunnels=$(pgrep -fc '[c]ode tunnel')"
echo "engine_worker=$(pgrep -fc '[p]ython -m app.cli.engine_worker')"
echo "training=$(pgrep -fc '[a]ktienki-engine train-predict')"
if systemctl --user is-active --quiet aktienki-all-stock-training.service 2>/dev/null || systemctl --user is-active --quiet aktienki-german-training.service 2>/dev/null; then
  echo 'german_queue=active'
else
  echo 'german_queue=inactive'
fi
echo "walk_forward=$(pgrep -fc 'catch_up_walk_forward_and_predict\.py|backtest_walk_forward_heatmap')"
echo "model_server_connection=$(timeout 7 ssh -i /home/akiadmin/.ssh/aktienki_worker_tunnel -o BatchMode=yes -o ConnectTimeout=5 root@217.154.240.14 true >/dev/null 2>&1 && echo active || echo inactive)"
echo "model_sync_service=$(systemctl --user is-active aktienki-model-sync.service 2>/dev/null || true)"
echo "model_sync_timer=$(systemctl --user is-active aktienki-model-sync.timer 2>/dev/null || true)"
echo "model_sync_status=$(cat "$project/logs/model_sync.status" 2>/dev/null || echo 'unknown|||Noch keine Synchronisation')"
echo "disk=$(df -P "$project" 2>/dev/null | awk 'NR==2 {print $5}')"
echo '---DATABASE---'
cd "$project" || exit 0
log_file=$(ls -1t logs/all_stock_training.log logs/german_remaining_*.log logs/walk_forward_catchup_*.log 2>/dev/null | head -1)
timeout 10 .venv/bin/python -c "from app.database import Database; d=Database(); r=d.fetch_one('SELECT current_database() AS database, inet_server_addr()::text AS server, inet_server_port() AS port'); print('ok|'+str(r['database'])+'|'+str(r['server'])+'|'+str(r['port'])); d.close()" 2>&1
echo '---ERRORS---'
test -n "$log_file" && grep -E 'ERROR|Error|Traceback|OperationalError|FAILED|Connection refused' "$log_file" 2>/dev/null | tail -35
echo '---LOG---'
test -n "$log_file" && tail -70 "$log_file" 2>/dev/null
BASH, 20);

        if (! $result->successful()) {
            return [
                'reachable' => false,
                'error' => trim($result->errorOutput() ?: $result->output()) ?: __('Mini-PC nicht erreichbar.'),
                'metrics' => [],
                'database' => ['connected' => false],
                'database_stats' => $databaseStats,
                'errors' => [],
                'log' => [],
                'runs' => $this->walkForwardRuns(),
            ];
        }

        $sections = $this->sections($result->output());
        $metrics = collect(preg_split('/\R/', trim($sections['STATUS'] ?? '')))
            ->filter(fn (string $line): bool => str_contains($line, '='))
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = explode('=', $line, 2);

                return [trim($key) => trim($value)];
            })->all();
        $databaseLine = trim($sections['DATABASE'] ?? '');
        $databaseParts = str_starts_with($databaseLine, 'ok|') ? explode('|', $databaseLine) : [];

        return [
            'reachable' => true,
            'error' => null,
            'metrics' => $metrics,
            'database' => [
                'connected' => count($databaseParts) === 4,
                'name' => $databaseParts[1] ?? null,
                'server' => $databaseParts[2] ?? null,
                'port' => $databaseParts[3] ?? null,
                'error' => count($databaseParts) === 4 ? null : $databaseLine,
            ],
            'database_stats' => $databaseStats,
            'errors' => $this->lines($sections['ERRORS'] ?? ''),
            'log' => $this->lines($sections['LOG'] ?? ''),
            'runs' => $this->walkForwardRuns(),
        ];
    }

    private function databaseStats(): array
    {
        try {
            $oldestModel = DB::table('trained_models as model')
                ->join('instruments as instrument', 'instrument.id', '=', 'model.instrument_id')
                ->where('model.status', 'active')
                ->whereNotNull('model.trained_at')
                ->orderBy('model.trained_at')
                ->orderBy('model.id')
                ->first(['instrument.symbol', 'model.trained_at']);
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
                'oldest_model_symbol' => $oldestModel?->symbol,
                'oldest_model_trained_at' => $oldestModel?->trained_at,
                'subscription_counts' => $subscriptionCounts,
            ];
        } catch (Throwable) {
            return [
                'users' => null,
                'active_users' => null,
                'walk_forward_stocks' => null,
                'oldest_model_symbol' => null,
                'oldest_model_trained_at' => null,
                'subscription_counts' => [],
            ];
        }
    }

    public function restoreDatabaseTunnel(): array
    {
        $result = $this->ssh(<<<'BASH'
set -e
if systemctl --user cat aktienki-postgres-tunnel.service >/dev/null 2>&1; then
  systemctl --user restart aktienki-postgres-tunnel.service
else
  systemd-run --user --unit=aktienki-postgres-tunnel --property=Restart=always --property=RestartSec=5 /usr/bin/ssh -N -T -o BatchMode=yes -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -o StrictHostKeyChecking=yes -i /home/akiadmin/.ssh/aktienki_worker_tunnel -L 127.0.0.1:25432:127.0.0.1:5432 root@217.154.240.14
fi
sleep 1
systemctl --user is-active aktienki-postgres-tunnel.service
ss -ltn | grep ':25432 '
BASH, 15);

        return $this->actionResult($result, __('Datenbanktunnel wurde wiederhergestellt.'));
    }

    public function restartWalkForward(): array
    {
        $result = $this->ssh(<<<'BASH'
set -e
project='/home/akiadmin/projects/ml/AktienKI-Python-Engine'
if pgrep -f '^\.venv/bin/python /tmp/catch_up_walk_forward_and_predict\.py|^\.venv/bin/python .*backtest_walk_forward' >/dev/null; then
  echo 'already-running'
  exit 0
fi
cd "$project"
test -f /tmp/catch_up_walk_forward_and_predict.py
log_file="logs/walk_forward_catchup_$(date +%F).log"
nohup .venv/bin/python /tmp/catch_up_walk_forward_and_predict.py >> "$log_file" 2>&1 < /dev/null &
sleep 1
pgrep -af '^\.venv/bin/python /tmp/catch_up_walk_forward_and_predict\.py|^\.venv/bin/python .*backtest_walk_forward'
BASH, 15);

        $message = str_contains($result->output(), 'already-running')
            ? __('Der Walk-Forward-Test läuft bereits.')
            : __('Der Walk-Forward-Test wurde gestartet.');

        return $this->actionResult($result, $message);
    }

    public function restartEngineWorker(): array
    {
        $result = $this->ssh(<<<'BASH'
set -e
project='/home/akiadmin/projects/ml/AktienKI-Python-Engine'
if pgrep -f '[p]ython -m app.cli.engine_worker' >/dev/null; then
  echo 'already-running'
  exit 0
fi
cd "$project"
mkdir -p logs
nohup .venv/bin/python -m app.cli.engine_worker >> logs/engine_worker.log 2>&1 < /dev/null &
sleep 2
pgrep -af '[p]ython -m app.cli.engine_worker'
BASH, 15);

        $message = str_contains($result->output(), 'already-running')
            ? __('Der Engine-Worker läuft bereits.')
            : __('Der Engine-Worker wurde gestartet.');

        return $this->actionResult($result, $message);
    }

    public function toggleTraining(): array
    {
        $result = $this->ssh(<<<'BASH'
set -e
new_service='aktienki-all-stock-training.service'
old_service='aktienki-german-training.service'
handoff_service='aktienki-training-handoff.service'
if systemctl --user is-active --quiet "$new_service"; then
  systemctl --user stop "$new_service"
  systemctl --user stop "$handoff_service" 2>/dev/null || true
  echo 'training-stopped'
elif systemctl --user is-active --quiet "$old_service"; then
  systemctl --user stop "$old_service"
  systemctl --user stop "$handoff_service" 2>/dev/null || true
  echo 'training-stopped'
else
  systemctl --user stop "$handoff_service" 2>/dev/null || true
  systemctl --user start "$new_service"
  echo 'training-started'
fi
BASH, 15);

        $message = str_contains($result->output(), 'training-stopped')
            ? __('Das Training wurde kontrolliert beendet.')
            : __('Das Training wurde gestartet.');

        return $this->actionResult($result, $message);
    }

    public function setCpuReserve(int $reservedCpus): array
    {
        if (! in_array($reservedCpus, [0, 1, 2, 4], true)) {
            return ['successful' => false, 'message' => __('Ungültige CPU-Reserve.')];
        }

        $result = $this->ssh(<<<BASH
set -e
total="\$(lscpu -p=CORE | grep -v '^#' | sort -u | wc -l)"
reserved={$reservedCpus}
usable="\$((total-reserved))"
test "\$usable" -ge 1
printf '%s\n' "\$reserved" > /home/akiadmin/.config/aktienki-cpu-reserve
last_core="\$((usable-1))"
allowed_cpus="\$(lscpu -p=CPU,CORE | awk -F, -v last_core="\$last_core" '\$1 !~ /^#/ && \$2 <= last_core {print \$1}' | paste -sd, -)"
test -n "\$allowed_cpus"
pids="\$(pgrep -f '[a]ktienki-engine train-predict' || true)"
if test -n "\$pids"; then
  for pid in \$pids; do
    /usr/bin/taskset -pc "\$allowed_cpus" "\$pid" >/dev/null
  done
fi
echo "reserved=\$reserved usable=\$usable total=\$total"
BASH, 15);

        return $this->actionResult(
            $result,
            __('CPU-Reserve gespeichert: :reserved von :total physischen Kernen bleiben für andere Aufgaben frei.', [
                'reserved' => $reservedCpus,
                'total' => 12,
            ])
        );
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
        $mini = config('operations.mini');
        $arguments = [
            '/usr/bin/ssh',
            '-p', (string) $mini['port'],
            '-i', (string) $mini['identity_file'],
        ];

        $controlPath = (string) ($mini['control_path'] ?? '');
        if ($controlPath !== '' && file_exists($controlPath)) {
            array_push($arguments, '-o', 'ControlMaster=no', '-o', 'ControlPath='.$controlPath);
        }

        array_push(
            $arguments,
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=6',
            '-o', 'StrictHostKeyChecking=yes',
            $mini['user'].'@'.$mini['host'],
            '/bin/bash -lc '.escapeshellarg($remoteCommand),
        );

        return Process::timeout($timeout)->run($arguments);
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
            if ($current !== null) $sections[$current] .= $line."\n";
        }

        return $sections;
    }

    private function lines(string $content): array
    {
        return array_values(array_filter(array_map('rtrim', preg_split('/\R/', trim($content)))));
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
