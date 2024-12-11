<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPressTest\Unit;

use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\LaraOutPress;

class ModuleTest extends TestCase
{
    public function testIndex()
    {
        $response = ( true === true );

        $this->assertIsBool($response);
    }

    public function testFormatSizeUnits()
    {
        $formatSizeUnits = LaraOutPress::formatSizeUnits(10000);
        $this->assertEquals('9.77KB',$formatSizeUnits);
    }
}
