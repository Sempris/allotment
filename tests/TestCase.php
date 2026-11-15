<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run artisan commands (e.g. migrations in RefreshDatabase) without mocking console output.
     */
    public $mockConsoleOutput = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without a compiled frontend, so the tests do not depend on `npm run build`.
        $this->withoutVite();
    }
}
