<?php

return [
    'mock_insurer_scenario' => env('APP_MOCK_INSURER_SCENARIO', 'success'),
    'allowed_scenarios' => ['success', 'temporary_failure', 'timeout_after_issue', 'delayed_callback', 'duplicate_callback'],
    'mock_insurer_enabled' => env('APP_ENV') !== 'production',
];
