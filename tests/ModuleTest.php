<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Tests;

use PHPUnit\Framework\TestCase;

class ModuleTest extends TestCase
{
    /** @test */
    public function index()
    {
        $response = ( true === true );

        $this->assertIsBool($response);
    }
}
