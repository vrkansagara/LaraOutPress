<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPressTest\Unit;

use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\LaraOutPress;

class LaraOutPressTest extends TestCase
{
    public function testFormatSizeUnitsForZeroDoesNotProduceNan()
    {
        $this->assertSame('0B', LaraOutPress::formatSizeUnits(0));
    }

    public function testFormatSizeUnitsForNegativeSizeDoesNotProduceNan()
    {
        $this->assertSame('0B', LaraOutPress::formatSizeUnits(-100));
    }

    public function testFormatSizeUnitsForBytes()
    {
        $this->assertSame('500B', LaraOutPress::formatSizeUnits(500));
    }

    public function testFormatSizeUnitsForKilobytes()
    {
        $this->assertSame('9.77KB', LaraOutPress::formatSizeUnits(10000));
    }

    public function testFormatSizeUnitsForMegabytes()
    {
        $this->assertSame('1MB', LaraOutPress::formatSizeUnits(1048576));
    }

    public function testFormatSizeUnitsForGigabytes()
    {
        $this->assertSame('1GB', LaraOutPress::formatSizeUnits(1024 ** 3));
    }
}
