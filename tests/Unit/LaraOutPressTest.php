<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPressTest\Unit;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\LaraOutPress;

class LaraOutPressTest extends TestCase
{
    private function makeApp($enabledConfigValue, bool $runningInConsole = false): Application
    {
        $config = $this->createMock(\Illuminate\Contracts\Config\Repository::class);
        $config->method('get')->with('laraoutpress')->willReturn([
            'enabled' => $enabledConfigValue,
            'debug' => false,
            'target_environment' => '',
            'exclude_routes' => ['api/*'],
        ]);

        $app = $this->createMock(Application::class);
        $app->method('offsetGet')->with('config')->willReturn($config);
        $app->method('runningInConsole')->willReturn($runningInConsole);
        $app->method('version')->willReturn('11.0.0');

        return $app;
    }

    public function testSetEnabledDefaultsToFalseForNonBooleanConfigValue()
    {
        $laraOutPress = new LaraOutPress($this->makeApp('DYNAMIC'));

        $this->assertFalse($laraOutPress->isEnabled());
    }

    public function testSetEnabledIsTrueForBooleanTrueConfigValue()
    {
        $laraOutPress = new LaraOutPress($this->makeApp(true));

        $this->assertTrue($laraOutPress->isEnabled());
    }

    public function testSetEnabledIsFalseWhenRunningInConsole()
    {
        $laraOutPress = new LaraOutPress($this->makeApp(true, true));

        $this->assertFalse($laraOutPress->isEnabled());
    }

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
