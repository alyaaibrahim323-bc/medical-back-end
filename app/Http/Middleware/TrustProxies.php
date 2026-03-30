<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // ✅ وثقي كل الـ proxies (مناسب في VPS وراء load balancer / cloudflare)
    protected $proxies = '*';

    // ✅ خلي Laravel يقرأ X-Forwarded-For / Proto / Host صح
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
