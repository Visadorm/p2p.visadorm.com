<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Safety: abort if tests accidentally run against the production database
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped(
                'SAFETY: Tests must run on SQLite. Run "php artisan config:clear" first, or use "composer test".'
            );
        }
    }
}
