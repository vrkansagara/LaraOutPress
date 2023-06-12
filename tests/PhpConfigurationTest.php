<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Tests;

use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\HtmlCompressor;
use Vrkansagara\LaraOutPress\PhpConfiguration;

class PhpConfigurationTest extends TestCase
{
    /** @test */
    public function htmlCompressorInstance()
    {
        $phpConfiguration = new PhpConfiguration();

        $this->assertInstanceOf(PhpConfiguration::class, $phpConfiguration);
    }
}
