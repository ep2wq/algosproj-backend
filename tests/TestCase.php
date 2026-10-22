<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // The frontend origin every test request comes from, like the browser app (README "Sessions").
    protected const FRONTEND = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();

        // Requests from the frontend get the session, encrypted cookies and CSRF (skipped in tests
        // unless a test turns it on). Set here, not from .env, so a local .env can't change it.
        // A server variable, so flushHeaders() keeps it; a test can still send its own Origin.
        config(['sanctum.stateful' => ['localhost:5173']]);
        $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND]);
    }
}
