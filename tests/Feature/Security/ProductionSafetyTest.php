<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_errors_do_not_leak_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::middleware('api')->get('/api/v1/_boom', fn () => throw new RuntimeException('secret database password in message'));

        $response = $this->getJson('/api/v1/_boom')->assertStatus(500);

        $response->assertExactJson(['message' => 'Server Error']);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    public function test_responses_carry_security_headers(): void
    {
        foreach (['/', '/api/v1/health'] as $uri) {
            $this->withoutVite()->get($uri)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_the_session_cookie_is_http_only_and_passwords_are_never_returned(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);

        $user = User::factory()->create();

        $body = $this->actingAs($user)->getJson('/api/v1/user')->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_every_customer_and_admin_endpoint_rejects_guests(): void
    {
        $protected = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
            ->filter(fn ($route) => in_array('auth:sanctum', $route->gatherMiddleware(), true));

        // Guard against the route file being reorganised in a way that drops protection.
        $this->assertGreaterThan(35, $protected->count());

        $adminRoutes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin'));
        $this->assertTrue($adminRoutes->isNotEmpty());

        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "{$route->uri()} is missing auth");
            $this->assertContains('admin', $middleware, "{$route->uri()} is missing the admin check");
            $this->assertContains('active', $middleware, "{$route->uri()} is missing the active-account check");
        }

        foreach ($protected as $route) {
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');

            $this->json($method, $uri)->assertUnauthorized();
        }
    }
}
