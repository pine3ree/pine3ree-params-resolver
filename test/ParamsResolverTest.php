<?php

declare(strict_types=1);

namespace pine3ree\test\Container;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use pine3ree\Container\ParamsResolver;

use function count;
use function strtoupper;
use function time;

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
            ['datetime', 'now'],
        ];

        $hasReturnMap = [
            ['someInt', true],
            ['aString', true],
            [DateTimeInterface::class, true],
            [DateTimeImmutable::class, true],
            [DateTime::class, false],
            ['time', false],
            ['datetime', true],
            [DateTimeZone::class, false],
            ['timezone', false],
            [DirectoryIterator::class, false],
            ['nonexistent', false],
            [TestClass::class, false],
            [TestTrait::class, false],
            [TestInterface::class, false],
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
        // phpcs:ignore
        $callable = function (int $someInt, $aString): void {};

        $args = $this->resolver->resolve($callable);

        self::assertEquals(42, $args[0]);
        self::assertEquals('The Answer', $args[1]);
    }

    public function testResolveDependency(): void
    {
        // phpcs:ignore
        $callable = function (DateTimeInterface $datetime): void {};

        $args = $this->resolver->resolve($callable);

        self::assertInstanceOf(DateTimeInterface::class, $args[0]);
        self::assertInstanceOf(DateTimeImmutable::class, $args[0]);
    }

    public function testThatConstructorCallSuccessIfNotInContainer(): void
    {
        // phpcs:ignore
        $callable = function (DateTime $datetime): void {};

        $args = $this->resolver->resolve($callable);

        self::assertInstanceOf(DateTimeInterface::class, $args[0]);
        self::assertInstanceOf(DateTime::class, $args[0]);
    }

    public function testThatConstructorCallFailureRaisesExceptionIfNotInContainer(): void
    {
        // phpcs:ignore
        $callable = function (DirectoryIterator $dir): void {};

        $this->expectException(RuntimeException::class);
        $args = $this->resolver->resolve($callable);
    }

    public function testThatNonResolvableDependencyRaisesExceptionIfNotDefaultValue(): void
    {
        // phpcs:ignore
        $callable = function (TestInterface $test): void {};

        $this->expectException(RuntimeException::class);
        $args = $this->resolver->resolve($callable);
    }

    public function testThatResolvingNonExistentParameterRaisesExceptionIfNotDefaultValueOrNullable(): void
    {
        // phpcs:ignore
        $callable = function (string $nonexistent): void {};

        $this->expectException(RuntimeException::class);
        $args = $this->resolver->resolve($callable);
    }

    public function testThatResolvingNonExistentParameterSucceedIfDefaultValue(): void
    {
        // phpcs:ignore
        $callable = function ($nonexistent = 'default'): void {};

        $args = $this->resolver->resolve($callable);

        self::assertEquals('default', $args[0]);
    }

    public function testThatInjectedContainerIsUsed(): void
    {
        // phpcs:ignore
        $callable = function (int $someInt, string $aString): void {};

        $args = $this->resolver->resolve($callable, null, $this->alternate);

        self::assertEquals(24, $args[0]);
        self::assertEquals('Another answer', $args[1]);
    }

    public function testThatInjectedParamsAreUsed(): void
    {
        // phpcs:ignore
        $callable = function (int $someInt, string $aString, DateTimeInterface $datetime): void {};

        $injectedInt = 123;
        $injectedString = 'Injected answer';

        $args = $this->resolver->resolve($callable, [
            'someInt' => $injectedInt,
            'aString' => $injectedString,
            DateTimeInterface::class => new DateTimeImmutable('1970-01-01'),
        ]);

        self::assertEquals($injectedInt, $args[0]);
        self::assertEquals($injectedString, $args[1]);
    }

    public function testConstructor(): void
    {
        $constructor = [DateTimeImmutable::class, '__construct'];

        if (PHP_VERSION_ID < 80000) {
            $this->expectException(RuntimeException::class);
        }
        $args = $this->resolver->resolve($constructor, [
            'time' => 'now', // php-7.4
        ]);

        self::assertEquals('now', $args[0]);

        $time = time();
        $args = $this->resolver->resolve($constructor, [
            'time' => $time, // php-7.4
            'datetime' => $time,
        ]);

        self::assertEquals($time, $args[0]);
        self::assertNull($args[1]);
    }

    public function testThatNonExistentMethodRaisesException(): void
    {
        $dateTime = new DateTimeImmutable();

        $callable = [$dateTime, 'nonExistent'];

        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve($callable);
    }

    public function testThatNonExistentClassRaisesException(): void
    {
        $callable = ['Non\Existent\Class', '__construct'];

        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve($callable);
    }

    public function testContainerDependency(): void
    {
        // phpcs:ignore
        $callable = function (ContainerInterface $container): void {};

        $args = $this->resolver->resolve($callable);

        self::assertEquals($this->resolver->getContainer(), $args[0]);
    }

    public function testFunction(): void
    {
        $someString = 'SOME STRING';

        $args = $this->resolver->resolve('strtoupper', [
            'str' => $someString, // php-7.4
            'string' => $someString,
        ]);

        self::assertEquals('SOME STRING', $args[0]);
    }

    public function testThatNonExistingFunctionRaisesException(): void
    {
        $callable = 'nonExistingFunction';

        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve($callable);
    }

    public function testMethod(): void
    {
        $dateTime = new DateTimeImmutable();

        $callable = [$dateTime, 'format'];

        $args = $this->resolver->resolve($callable, [
            'format' => '%Y-%m-%d',
        ]);

        self::assertEquals('%Y-%m-%d', $args[0]);
    }

    public function testInvokableObject(): void
    {
        $invokable = new class {
            // phpcs:ignore
            public function __invoke(string $str) {
                //no-op;
            }
        };

        $args = $this->resolver->resolve($invokable, [
            'str' => $str = 'Some string',
        ]);

        self::assertEquals($str, $args[0]);
    }

    public function testThatNonInstatiatableClassRaisesException(): void
    {
        // phpcs:ignore
        $callable = function (TestClass $test): void {};

        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve($callable);
    }

    public function testThatInvalidDependencyParameterTypeRaisesException(): void
    {
        // phpcs:ignore
        $callable = function (TestTrait $test): void {};

        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve($callable);
    }

    public function testEmptyParams(): void
    {
        $args = $this->resolver->resolve('time');

        self::assertEquals([], $args);
    }

    public function testCachedReflectionParameters()
    {
        $constructor = [DateTimeImmutable::class, '__construct'];

        $time = time();
        $args = $this->resolver->resolve($constructor, [
            'time' => $time, // php-7.4
            'timezone' => 'UTC', // php-7.4
        ]);

        $key = DateTimeImmutable::class . '::__construct';

        $rf_params = ParamsResolver::getCachedReflectionParameters($key);

        self::assertCount(2, $rf_params);
    }
}
