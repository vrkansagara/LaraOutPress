<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Tests;

use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\HtmlCompressor;

class HtmlCompressorTest extends TestCase
{
    /** @test */
    public function htmlCompressorInstance()
    {
        $htmlCompressor = new HtmlCompressor();

        $this->assertInstanceOf(HtmlCompressor::class, $htmlCompressor);
    }
}
