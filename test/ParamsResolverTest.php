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
use ReflectionProperty;
use RuntimeException;
use pine3ree\Container\ParamsResolver;

use const PHP_VERSION_ID;

use function strtoupper;
use function time;

final class ParamsResolverTest extends TestCase
{
    private ParamsResolver $resolver;

    private ContainerInterface $container;
    /**
     * set up test environmemt
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();

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
            ['number', false],
        ];

        $this->container->method('has')->willReturnMap($hasReturnMap);
        $this->container->method('get')->willReturnMap($containerParamsMap);
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

    public function testThatResolvingNotFoundParameterSucceedIfNullable(): void
    {
        // phpcs:ignore
        $callable = function (?string $nonexistent): void {};

        $args = $this->resolver->resolve($callable);

        self::assertNull($args[0]);
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

        $injectedParams = [];
        if (PHP_VERSION_ID < 80000) {
            $injectedParams = [
                'time' => 'now', // php-7.4
                'timezone' => 'UTC', // php-7.4
            ];
        }

        $args = $this->resolver->resolve($constructor, $injectedParams);

        self::assertEquals('now', $args[0]);

        $time = time();
        $injectedParams = [];
        if (PHP_VERSION_ID < 80000) {
            $injectedParams = [
                'time' => $time, // php-7.4
                'timezone' => 'UTC', // php-7.4
            ];
        } else {
            $injectedParams = [
                'datetime' => $time,
                DateTimeZone::class => 'UTC',
            ];
        }

        $args = $this->resolver->resolve($constructor, $injectedParams);

        self::assertEquals($time, $args[0]);
        self::assertEquals('UTC', $args[1]);
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

        $rp = new ReflectionProperty(ParamsResolver::class, 'container');
        $rp->setAccessible(true);
        $container = $rp->getValue($this->resolver);

        self::assertEquals($container, $args[0]);
    }

    public function testNullableDependency(): void
    {
        // phpcs:ignore
        $callable = function (?DateTimeZone $tz): void {};

        $args = $this->resolver->resolve($callable);

        self::assertNull($args[0]);
    }

    /**
     * @requires PHP 8.1.0
     */
    public function testUnresolvedDependencyWithDefaultValue(): void
    {
        // phpcs:ignore
        if (PHP_VERSION_ID < 80100) {
            self::markTestSkipped("Feature only available in PHP-8.1+");
        }

        $callable = [InitClass::class, '__construct'];

        $args = $this->resolver->resolve($callable);

        self::assertInstanceOf(DateTime::class, $args[0]);
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

    /**
     * @dataProvider provideMethods
     */
    public function testInstanceAndStaticMethods($callable, $params, $arg0): void
    {
        $args = $this->resolver->resolve($callable, $params);

        self::assertEquals($arg0, $args[0]);
    }

    public function provideMethods(): array
    {
        return [
            [[new TestClass('test'), 'doSomething'], null, 42],
            [[new TestClass('test'), 'doSomething'], ['number' => 27], 27],
            [[TestClass::class, 'doSomethingStatic'], null, null],
            [[TestClass::class, 'doSomethingStatic'], ['number' => 27], 27],
        ];
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

    /**
     * @dataProvider provideInvalidArraySpecs
     */
    public function testThatInvalidSpecsArraysRaiseException(array $callable): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->resolve($callable);
    }

    public function provideInvalidArraySpecs(): array
    {
        return [
            [[]],
            [[null, 'someMethod']],
            [['', 'someMethod']],
            [[123, 'someMethod']],
            [[Test::class]],
            [[Test::class, null]],
            [[Test::class, 123]],
        ];
    }

    public function testThatNonInvokableObjectRaisesException(): void
    {
        $this->expectException(RuntimeException::class);

        $args = $this->resolver->resolve(new TestClass('test'));
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

        $rp = new ReflectionProperty(ParamsResolver::class, 'cache');
        $rp->setAccessible(true);
        $cache = $rp->getValue();

        $rp_params = $cache[$key] ?? [];

        self::assertCount(2, $rp_params);
    }
}
