<?php

return [

    'rate_limit_per_minute' => (int) env('NOTIFICATIONS_RATE_LIMIT', 60),

    'idempotency_ttl_hours' => (int) env('NOTIFICATIONS_IDEMPOTENCY_TTL_HOURS', 24),

];
