<?php

// Security events and IP bans (README "Security events and IP bans").
return [
    // Proxies whose X-Forwarded-* headers are believed: comma-separated IPs or CIDR ranges, or *
    // (any caller; only when the app can be reached through the proxy alone). Empty: none, so the
    // request IP is the connecting address and a client cannot fake it with X-Forwarded-For.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // security:prune deletes events last seen longer ago than this, and bans expired longer ago than ban_retention_days.
    'retention_days' => 90,
    'ban_retention_days' => 30,
];
