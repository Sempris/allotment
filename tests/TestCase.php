<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run artisan commands (e.g. migrations in RefreshDatabase) without mocking console output.
     */
    public $mockConsoleOutput = false;
}
