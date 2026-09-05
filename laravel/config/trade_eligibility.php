<?php

return [
    // A BUY remains the model signal. These thresholds only decide whether
    // executing it is currently economical for Pro users.
    'pause_below_net_return_percent' => (float) env('AKTIENKI_PAUSE_BUY_BELOW_NET_RETURN_PERCENT', 1.0),
    'resume_at_net_return_percent' => (float) env('AKTIENKI_RESUME_BUY_AT_NET_RETURN_PERCENT', 2.0),
];
