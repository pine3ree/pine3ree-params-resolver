<?php

declare(strict_types=1);

namespace pine3ree\test\Container;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use pine3ree\Container\ParamsResolver;

final class ParamsResolverTest extends TestCase
{
    private ParamsResolver $resolver;

    private ContainerInterface $container;
    private ContainerInterface $alternate;

    /**
     * set up test environmemt
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->alternate = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $this->resolver = new ParamsResolver($this->container);

        $containerParamsMap = [
            ['someInt', 42],
            ['aString', 'The Answer'],
            [DateTimeInterface::class, new DateTimeImmutable()],
            [DateTimeImmutable::class, new DateTimeImmutable()],
        ];

        $hasReturnMap = [
            ['someInt', true],
            ['aString', true],
            [DateTimeInterface::class, true],
            [DateTimeImmutable::class, true],
            [DateTime::class, false],
            [DirectoryIterator::class, false],
            ['noexistent', false],
        ];

        $this->container->method('has')->willReturnMap($hasReturnMap);
        $this->container->method('get')->willReturnMap($containerParamsMap);

        $alternateParamsMap = [
            ['someInt', 24],
            ['aString', 'Another answer'],
        ];
        $alternateHasMap = [
            ['someInt', true],
            ['aString', true],
        ];

        $this->alternate->method('has')->willReturnMap($alternateHasMap);
        $this->alternate->method('get')->willReturnMap($alternateParamsMap);
    }

    public function testResolveSimpleParameters(): void
    {
        $callable = function (int $someInt, $aString): void {};

        $args = $this->resolver->resolve($callable);

        self::assertEquals(42, $args[0]);
        self::assertEquals('The Answer', $args[1]);
    }

    public function testResolveDependency(): void
    {
        $callable = function (DateTimeInterface $datetime): void {};

        $args = $this->resolver->resolve($callable);

        self::assertInstanceOf(DateTimeInterface::class, $args[0]);
        self::assertInstanceOf(DateTimeImmutable::class, $args[0]);
    }

    public function testThatConstructorCallSuccessIfNotInContainer(): void
    {
        $callable = function (DateTime $datetime): void {};

        $args = $this->resolver->resolve($callable);

        self::assertInstanceOf(DateTimeInterface::class, $args[0]);
        self::assertInstanceOf(DateTime::class, $args[0]);
    }

    public function testThatConstructorCallFailureRaisesExceptionIfNotInContainer(): void
    {
        $callable = function (DirectoryIterator $dir): void {};

        $this->expectException(\RuntimeException::class);
        $args = $this->resolver->resolve($callable);
    }

    public function testResolveNonExistentParameterRaisesExceptionIfNotDefaultValue(): void
    {
        $callable = function ($noexistent): void {};

        $this->expectException(\RuntimeException::class);
        $args = $this->resolver->resolve($callable);
    }

    public function testResolveNonExistentParameterSucceedIfDefaultValue(): void
    {
        $callable = function ($noexistent = 'default'): void {};

        $args = $this->resolver->resolve($callable);

        self::assertEquals('default',  $args[0]);
    }

    public function testThatInjectedContainerIsUsed(): void
    {
        $callable = function (int $someInt, $aString): void {};

        $args = $this->resolver->resolve($callable, null, $this->alternate);

        self::assertEquals(24, $args[0]);
        self::assertEquals('Another answer', $args[1]);
    }

    public function testThatInjectedParamsAreUsed(): void
    {
        $callable = function (int $someInt, string $aString): void {};

        $injectedInt = 123;
        $injectedString = 'Injected answer';

        $args = $this->resolver->resolve($callable, [
            'someInt' => $injectedInt,
            'aString' => $injectedString,
        ]);

        self::assertEquals($injectedInt, $args[0]);
        self::assertEquals($injectedString, $args[1]);
    }
}
