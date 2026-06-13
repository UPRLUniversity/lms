<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views render @vite(...) tags; tests must not depend on a built manifest.
        $this->withoutVite();
    }
}
