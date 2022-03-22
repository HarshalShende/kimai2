<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Twig\Runtime;

use App\Configuration\SystemConfiguration;
use App\Entity\Configuration;
use App\Entity\User;
use App\Event\ThemeEvent;
use App\Tests\Configuration\TestConfigLoader;
use App\Twig\Runtime\ThemeExtension;
use App\Utils\Theme;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @covers \App\Twig\Runtime\ThemeExtension
 */
class ThemeEventExtensionTest extends TestCase
{
    private function getDefaultSettings(): array
    {
        return [
            'theme' => [
                'box_color' => 'green',
                'select_type' => null,
                'show_about' => true,
                'chart' => [
                    'background_color' => 'rgba(0,115,183,0.7)',
                    'border_color' => '#3b8bba',
                    'grid_color' => 'rgba(0,0,0,.05)',
                    'height' => '200'
                ],
                'branding' => [
                    'logo' => null,
                    'mini' => null,
                    'company' => null,
                    'title' => null,
                ],
            ],
        ];
    }

    protected function getSut(bool $hasListener = true, string $title = null): ThemeExtension
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('hasListeners')->willReturn($hasListener);
        $dispatcher->expects($hasListener ? $this->once() : $this->never())->method('dispatch');

        $translator = $this->getMockBuilder(TranslatorInterface::class)->getMock();
        $translator->method('trans')->willReturn('foo');

        $configs = [
            (new Configuration())->setName('theme.branding.title')->setValue($title)
        ];
        $loader = new TestConfigLoader($configs);
        $configuration = new SystemConfiguration($loader, $this->getDefaultSettings());
        $theme = new Theme($configuration);

        return new ThemeExtension($dispatcher, $translator, $configuration, $theme);
    }

    protected function getEnvironment(): Environment
    {
        $mock = $this->getMockBuilder(UsernamePasswordToken::class)->onlyMethods(['getUser'])->disableOriginalConstructor()->getMock();
        $mock->method('getUser')->willReturn(new User());
        /** @var UsernamePasswordToken $token */
        $token = $mock;

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $app = new AppVariable();
        $app->setTokenStorage($tokenStorage);

        $environment = new Environment(new FilesystemLoader());
        $environment->addGlobal('app', $app);

        return $environment;
    }

    public function testTrigger()
    {
        $sut = $this->getSut();
        $event = $sut->trigger($this->getEnvironment(), 'foo', []);
        self::assertInstanceOf(ThemeEvent::class, $event);
    }

    public function testTriggerWithoutListener()
    {
        $sut = $this->getSut(false);
        $event = $sut->trigger($this->getEnvironment(), 'foo', []);
        self::assertInstanceOf(ThemeEvent::class, $event);
    }

    public function testJavascriptTranslations()
    {
        $sut = $this->getSut();
        $values = $sut->getJavascriptTranslations();
        self::assertCount(24, $values);
    }

    public function getProgressbarColors()
    {
        yield ['bg-red', 100, false];
        yield ['bg-red', 91, false];
        yield ['bg-warning', 90, false];
        yield ['bg-warning', 80, false];
        yield ['bg-warning', 71, false];
        yield ['bg-green', 70, false];
        yield ['bg-green', 60, false];
        yield ['bg-green', 51, false];
        yield ['bg-green', 50, false];
        yield ['bg-green', 40, false];
        yield ['bg-green', 31, false];
        yield ['', 30, false];
        yield ['', 20, false];
        yield ['', 10, false];
        yield ['', 0, false];
        yield ['bg-green', 100, true];
        yield ['bg-green', 91, true];
        yield ['bg-green', 90, true];
        yield ['bg-green', 80, true];
        yield ['bg-green', 71, true];
        yield ['bg-warning', 70, true];
        yield ['bg-warning', 60, true];
        yield ['bg-warning', 51, true];
        yield ['bg-red', 50, true];
        yield ['bg-red', 40, true];
        yield ['bg-red', 31, true];
        yield ['', 30, true];
        yield ['', 20, true];
        yield ['', 10, true];
        yield ['', 0, true];
    }

    /**
     * @dataProvider getProgressbarColors
     */
    public function testProgressbarClass(string $expected, int $percent, ?bool $reverseColors = false)
    {
        $sut = $this->getSut(false);
        self::assertEquals($expected, $sut->getProgressbarClass($percent, $reverseColors));
    }

    public function testGetTitle()
    {
        $sut = $this->getSut(false);
        $this->assertEquals('Kimai – foo', $sut->generateTitle());
        $this->assertEquals('sdfsdf | Kimai – foo', $sut->generateTitle('sdfsdf | '));
        $this->assertEquals('<b>Kimai</b> ... foo', $sut->generateTitle('<b>', '</b> ... '));
        $this->assertEquals('Kimai | foo', $sut->generateTitle(null, ' | '));
    }

    public function testGetBrandedTitle()
    {
        $sut = $this->getSut(false, 'MyCompany');
        $this->assertEquals('MyCompany – foo', $sut->generateTitle());
        $this->assertEquals('sdfsdf | MyCompany – foo', $sut->generateTitle('sdfsdf | '));
        $this->assertEquals('<b>MyCompany</b> ... foo', $sut->generateTitle('<b>', '</b> ... '));
        $this->assertEquals('MyCompany | foo', $sut->generateTitle(null, ' | '));
    }
}
