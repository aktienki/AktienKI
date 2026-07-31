<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('market-prices', fn ($user): bool => $user !== null);
