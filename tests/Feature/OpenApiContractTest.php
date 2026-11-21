<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    public function test_documented_operations_match_laravel_api_routes(): void
    {
        $spec = json_decode(
            file_get_contents(base_path('docs/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $documented = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $documented[] = strtoupper($method).' '.$path;
                $this->assertNotEmpty($operation['operationId'] ?? null);
            }
        }

        $registered = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $registered[] = $method.' /'.$route->uri();
            }

            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $route->uri().' must require Sanctum');
        }

        sort($documented);
        sort($registered);

        $this->assertSame($registered, $documented, 'OpenAPI paths and methods drifted from Laravel route registration.');
    }

    public function test_every_api_operation_declares_auth_and_expected_http_statuses(): void
    {
        $spec = json_decode(
            file_get_contents(base_path('docs/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertArrayHasKey('sanctumBearer', $spec['components']['securitySchemes']);

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertNotEmpty(
                    $operation['security'] ?? $spec['security'] ?? null,
                    strtoupper($method).' '.$path.' must define authentication'
                );

                foreach (['401', '403', '404'] as $status) {
                    $this->assertArrayHasKey(
                        $status,
                        $operation['responses'],
                        strtoupper($method).' '.$path.' is missing status '.$status
                    );
                }
            }
        }
    }
}
