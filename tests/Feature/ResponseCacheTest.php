<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResponseCacheTest extends TestCase
{
    private const string TOKEN = 'test-internal-token';

    private int $handlerCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.internal_api.token' => self::TOKEN]);

        Route::middleware(['api', 'cache.response'])->get('api/test-cached', function () {
            $this->handlerCalls++;

            return successResponse(['call' => $this->handlerCalls, 'query' => request()->query()]);
        });

        Route::middleware(['api', 'cache.response'])->get('api/test-cached-failure', function () {
            $this->handlerCalls++;

            return errorResponse('unavailable', 500);
        });

        Route::middleware('api')->get('api/test-throttled', fn () => successResponse());
    }

    public function test_repeated_request_is_served_from_cache(): void
    {
        $this->getJson('/api/test-cached')
            ->assertOk()
            ->assertHeader('X-Response-Cache', 'MISS');

        $this->getJson('/api/test-cached')
            ->assertOk()
            ->assertHeader('X-Response-Cache', 'HIT')
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.call', 1);

        $this->assertSame(1, $this->handlerCalls);
    }

    public function test_different_query_strings_are_cached_separately(): void
    {
        $this->getJson('/api/test-cached?page=1')->assertJsonPath('data.call', 1);
        $this->getJson('/api/test-cached?page=2')->assertJsonPath('data.call', 2);
        $this->getJson('/api/test-cached?page=1')->assertJsonPath('data.call', 1);
    }

    public function test_unsuccessful_responses_are_not_cached(): void
    {
        $this->getJson('/api/test-cached-failure')->assertStatus(500);
        $this->getJson('/api/test-cached-failure')->assertStatus(500);

        $this->assertSame(2, $this->handlerCalls);
    }

    public function test_flush_endpoint_with_valid_token_invalidates_cached_responses(): void
    {
        $this->getJson('/api/test-cached')->assertJsonPath('data.call', 1);

        $this->deleteJson(route('api.response-cache.destroy'), [], ['X-Internal-Token' => self::TOKEN])
            ->assertOk();

        $this->getJson('/api/test-cached')
            ->assertHeader('X-Response-Cache', 'MISS')
            ->assertJsonPath('data.call', 2);
    }

    public function test_flush_endpoint_rejects_missing_or_wrong_token(): void
    {
        $this->getJson('/api/test-cached');

        $this->deleteJson(route('api.response-cache.destroy'))->assertForbidden();
        $this->deleteJson(route('api.response-cache.destroy'), [], ['X-Internal-Token' => 'wrong'])->assertForbidden();

        $this->getJson('/api/test-cached')->assertHeader('X-Response-Cache', 'HIT');
    }

    public function test_flush_endpoint_is_forbidden_when_no_token_is_configured(): void
    {
        config(['services.internal_api.token' => null]);

        $this->deleteJson(route('api.response-cache.destroy'), [], ['X-Internal-Token' => ''])->assertForbidden();
    }

    public function test_clear_command_invalidates_cached_responses(): void
    {
        $this->getJson('/api/test-cached');

        $this->artisan('app:clear-response-cache')->assertSuccessful();

        $this->getJson('/api/test-cached')->assertHeader('X-Response-Cache', 'MISS');
    }

    public function test_internal_token_bypasses_api_rate_limit(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/test-throttled', ['X-Internal-Token' => self::TOKEN]);
        }

        $this->getJson('/api/test-throttled', ['X-Internal-Token' => self::TOKEN])->assertOk();
        $this->getJson('/api/test-throttled', ['X-Internal-Token' => 'wrong'])->assertOk();

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/test-throttled');
        }

        $this->getJson('/api/test-throttled', ['X-Internal-Token' => 'wrong'])->assertTooManyRequests();
    }
}
