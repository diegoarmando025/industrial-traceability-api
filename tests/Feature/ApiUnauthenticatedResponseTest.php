<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiUnauthenticatedResponseTest extends TestCase
{
    public function test_browser_request_without_token_gets_json_401_instead_of_login_redirect(): void
    {
        $this->withHeaders(['Accept' => 'text/html'])
            ->get('/api/v1/boxes/1')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_json_request_without_token_still_gets_401(): void
    {
        $this->getJson('/api/v1/boxes/1')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_browser_post_without_token_also_gets_json_401(): void
    {
        $this->withHeaders(['Accept' => 'text/html'])
            ->post('/api/v1/boxes', [])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
