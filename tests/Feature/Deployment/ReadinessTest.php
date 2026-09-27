<?php

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function test_ready_returns_success_when_database_and_redis_respond(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['?column?' => 1]]);
        Redis::shouldReceive('ping')->once()->andReturn(true);

        $this->getJson('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
    }

    public function test_ready_returns_generic_failure_when_database_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('private database endpoint'));

        $response = $this->getJson('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
        $this->assertStringNotContainsString('private database endpoint', $response->getContent());
    }

    public function test_ready_returns_failure_when_redis_is_unavailable(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object) ['?column?' => 1]]);
        Redis::shouldReceive('ping')->once()->andReturn(false);

        $this->getJson('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }
}
