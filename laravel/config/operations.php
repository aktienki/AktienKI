<?php

return [
    'mini' => [
        'host' => env('MINI_PC_HOST', '192.168.1.100'),
        'port' => (int) env('MINI_PC_PORT', 2222),
        'user' => env('MINI_PC_USER', 'akiadmin'),
        'identity_file' => env('MINI_PC_IDENTITY_FILE', (static function (): string {
            $home = (string) ($_SERVER['HOME'] ?? getenv('HOME') ?: '');
            if ($home === '' && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $home = (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '');
            }

            return rtrim($home, '/').'/.ssh/aktienki_macmini';
        })()),
        'known_hosts_file' => env('MINI_PC_KNOWN_HOSTS_FILE'),
        'control_path' => env('MINI_PC_CONTROL_PATH', '/private/tmp/aktienki-mini-control'),
        'project_path' => env('MINI_PC_PROJECT_PATH', '/home/akiadmin/projects/ml/AktienKI-Python-Engine'),
        'database_tunnel_port' => (int) env('MINI_PC_DATABASE_TUNNEL_PORT', 25432),
        'remote_database_host' => env('MINI_PC_REMOTE_DATABASE_HOST', '217.154.240.14'),
    ],
];
