<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('sync', function ($user) {
    return true; // Single-user app — any authenticated user can listen
});
