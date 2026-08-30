<?php

return [
    'mac_mini' => [
        'name' => env('MAC_MINI_NAME', 'Mac mini von Silvio'),
        'host' => env('MAC_MINI_HOST', 'Mac-mini-von-Silvio.local'),
        'port' => (int) env('MAC_MINI_PORT', 22),
        'user' => env('MAC_MINI_USER', 'aktienki'),
        'identity_file' => env('MAC_MINI_IDENTITY_FILE', (static function (): string {
            $home = (string) ($_SERVER['HOME'] ?? getenv('HOME') ?: '');
            if ($home === '' && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $home = (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '');
            }

            return rtrim($home, '/').'/.ssh/aktienki_macmini';
        })()),
        'known_hosts_file' => env('MAC_MINI_KNOWN_HOSTS_FILE'),
        'host_key_alias' => env('MAC_MINI_HOST_KEY_ALIAS', '192.168.1.100'),
        'control_path' => env('MAC_MINI_CONTROL_PATH', '/private/tmp/aktienki-mac-mini-control'),
        'project_path' => env('MAC_MINI_PROJECT_PATH', '/Users/aktienki/pipeline-next'),
        'pipeline_database_name' => env('MAC_MINI_PIPELINE_DATABASE_NAME', 'aktienki_pipeline'),
        'pipeline_database_port' => (int) env('MAC_MINI_PIPELINE_DATABASE_PORT', 25433),
        'pipeline_database_socket' => env('MAC_MINI_PIPELINE_DATABASE_SOCKET', '/Users/aktienki/pipeline-next/.local/postgres/socket'),
        'control_plane_label' => env('MAC_MINI_CONTROL_PLANE_LABEL', 'com.aktienki.pipeline.control-plane'),
        'continuous_training_label' => env('MAC_MINI_CONTINUOUS_TRAINING_LABEL', 'com.aktienki.continuous-training'),
        'continuous_training_state' => env('MAC_MINI_CONTINUOUS_TRAINING_STATE', '/Users/aktienki/pipeline-data/continuous-training/state.json'),
        'continuous_training_log' => env('MAC_MINI_CONTINUOUS_TRAINING_LOG', '/Users/aktienki/pipeline-data/continuous-training/controller.log'),
        'server_host' => env('MAC_MINI_SERVER_HOST', '217.154.240.14'),
        'server_identity_file' => env('MAC_MINI_SERVER_IDENTITY_FILE', '/Users/aktienki/.ssh/aktienki_server'),
    ],
];
