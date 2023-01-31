<?php

declare(strict_types=1);

namespace Iodev\Whois;

use Iodev\Whois\Container\Default\Container;
use Iodev\Whois\Container\Default\ContainerBuilder;
use Iodev\Whois\Loader\FakeSocketLoader;
use Iodev\Whois\Loader\LoaderInterface;
use Iodev\Whois\Loader\ResponseHandler;
use Iodev\Whois\Module\Tld\Parser\CommonParserOpts;
use Iodev\Whois\Module\Tld\Parser\TestCommonParser;
use Iodev\Whois\Module\Tld\TldInfoRankCalculator;
use Iodev\Whois\Tool\DateTool;
use Iodev\Whois\Tool\DomainTool;
use Iodev\Whois\Tool\ParserTool;
use PHPUnit\Framework\TestCase;
use Iodev\Whois\Config\ConfigProvider;
use Iodev\Whois\Config\ConfigProviderInterface;
use Iodev\Whois\Module\Tld\TldParserProviderInterface;
use Iodev\Whois\Module\Tld\TldServerMatcher;
use Iodev\Whois\Module\Tld\TldServerProvider;

abstract class BaseTestCase extends TestCase
{
    protected Container $container;
    protected ConfigProvider $configProvider;
    protected FakeSocketLoader $loader;
    protected Whois $whois;


    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->container = static::getContainer();
        $this->configProvider = static::getConfigProvider();
        $this->loader = static::getLoader();
        $this->whois = static::getWhois();

        $this->onConstructed();
    }

    /**
     * To skip every time copy-paste __construct params on constructor overriding
     */
    protected function onConstructed()
    {
    }

    protected static function getContainer(): Container
    {
        static $container = null;

        if ($container === null) {
            $container = (new ContainerBuilder())
                ->configure()
                ->getContainer()
            ;
            $container->bindMany([
                LoaderInterface::class => function() {
                    return static::getLoader();
                },

                TestCommonParser::class => function() use ($container) {
                    return new TestCommonParser(
                        $container->get(CommonParserOpts::class),
                        $container->get(TldInfoRankCalculator::class),
                        $container->get(ParserTool::class),
                        $container->get(DomainTool::class),
                        $container->get(DateTool::class),
                    );
                },

                TldServerProviderInterface::class => function() {
                    return $this->container->get(TldServerProvider::class);
                },

                TldServerProvider::class => function() use ($container) {
                    $instance = new TldServerProvider(
                        $container,
                        $container->get(ConfigProviderInterface::class),
                        $container->get(LoaderInterface::class),
                        $container->get(TldParserProviderInterface::class),
                        $container->get(TldServerMatcher::class),
                    );
                    $instance->setWhoisUpdateEnabled(false);
                    return $instance;
                },
            ]);
        }
        return $container;
    }

    protected static function getConfigProvider(): ConfigProvider
    {
        static $configProvider = null;

        if ($configProvider === null) {
            $configProvider = static::getContainer()->get(ConfigProvider::class);
        }
        return $configProvider;
    }

    protected static function getLoader(): FakeSocketLoader
    {
        static $loader = null;

        if ($loader === null) {
            $loader = new FakeSocketLoader(
                static::getContainer()->get(ResponseHandler::class),
            );
        }
        return $loader;
    }

    protected static function getWhois(): Whois
    {
        static $whois = null;

        if ($whois === null) {
            $whois = static::getContainer()->get(Whois::class);
        }
        return $whois;
    }
}