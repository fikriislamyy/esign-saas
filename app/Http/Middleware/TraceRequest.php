<?php

namespace App\Http\Middleware;

use App\Observability\Telemetry;
use Closure;
use Illuminate\Http\Request;

class TraceRequest
{
    public function __construct(private readonly Telemetry $telemetry) {}

    public function handle(Request $request, Closure $next): mixed
    {
        return $this->telemetry->traceRequest($request, $next);
    }
}
