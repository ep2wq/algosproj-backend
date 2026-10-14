<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_frontend_origin_is_allowed(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);

        $this->call('OPTIONS', '/api/modules', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_other_origin_only_gets_the_frontend_origin_back(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);

        $this->call('OPTIONS', '/api/modules', server: [
            'HTTP_ORIGIN' => 'http://evil.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
