<?php

declare(strict_types=1);

namespace pine3ree\test\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use pine3ree\Container\ParamsResolver;
use pine3ree\Container\ParamsResolverFactory;
use pine3ree\Container\ParamsResolverInterface;

final class ParamsResolverFactoryTest extends TestCase
{
    private ParamsResolverFactory $factory;

    private ContainerInterface $container;

    /**
     * set up test environmemt
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ParamsResolverFactory();
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();

    }

    public function testThatFactoryCanCreateParamsResolverInstance(): void
    {
        $resolver = ($this->factory)($this->container);

        self::assertInstanceOf(ParamsResolverInterface::class, $resolver);
    }

    public function testThatInjectedContainerIsTheOneUsedAsArgument(): void
    {
        $resolver = ($this->factory)($this->container);

        self::assertInstanceOf(ParamsResolver::class, $resolver);
        self::assertSame($this->container, $resolver->getContainer());
    }
}
