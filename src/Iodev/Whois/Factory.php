<?php

declare(strict_types=1);

namespace Iodev\Whois;

use Iodev\Whois\Modules\Asn\AsnParser;
use Iodev\Whois\Modules\Asn\AsnServer;
use Iodev\Whois\Modules\Tld\Parsers\AutoParser;
use Iodev\Whois\Modules\Tld\Parsers\BlockParser;
use Iodev\Whois\Modules\Tld\Parsers\CommonParser;
use Iodev\Whois\Modules\Tld\Parsers\IndentParser;
use Iodev\Whois\Modules\Tld\TldParser;
use Iodev\Whois\Modules\Tld\TldServer;
use Iodev\Whois\Punycode\IPunycode;
use InvalidArgumentException;
use Iodev\Whois\Container\Default\ContainerBuilder;
use Psr\Container\ContainerInterface;

class Factory implements IFactory
{
    private ContainerInterface $container;

    public function __construct()
    {
        $this->container = (new ContainerBuilder())
            ->configure()
            ->getContainer()
        ;
    }

    public static function get(): Factory
    {
        static $instance;
        if (!$instance) {
            $instance = new static();
        }
        return $instance;
    }

    public function createPunycode(): IPunycode
    {
        return $this->container->get(IPunycode::class);
    }

    /**
     * @param array|null $configs
     * @param TldParser|null $defaultParser
     * @return TldServer[]
     */
    public function createTldSevers($configs = null, TldParser $defaultParser = null): array
    {
        $configs = is_array($configs) ? $configs : Config::load("module.tld.servers");
        $defaultParser = $defaultParser ?: $this->createTldParser();
        $servers = [];
        foreach ($configs as $config) {
            $servers[] = $this->createTldSever($config, $defaultParser);
        }
        return $servers;
    }

    /**
     * @param array $config
     * @param TldParser|null $defaultParser
     * @return TldServer
     */
    public function createTldSever(array $config, TldParser $defaultParser = null): TldServer
    {
        $zone = $config['zone'] ?? '';
        if (empty($zone)) {
            throw new InvalidArgumentException("Zone must be specified");
        }
        $zone = rtrim('.' . trim($zone, '.'), '.');

        $host = $config['host'] ?? '';
        if (empty($host)) {
            throw new InvalidArgumentException("Host must be specified");
        }

        return new TldServer(
            $zone,
            $host,
            $config['centralized'] ?? false,
            $this->createTldSeverParser($config, $defaultParser),
            $config['queryFormat'] ?? TldServer::DEFAULT_QUERY_FORMAT,
        );
    }

    /**
     * @param array $config
     * @param TldParser|null $defaultParser
     * @return TldParser
     */
    public function createTldSeverParser(array $config, TldParser $defaultParser = null): TldParser
    {
        $options = $config['parserOptions'] ?? [];
        if (isset($config['parserClass'])) {
            return $this->createTldParserByClass(
                $config['parserClass'],
                $config['parserType'] ?? null
            )->setOptions($options);
        }
        if (isset($config['parserType'])) {
            return $this->createTldParser($config['parserType'])->setOptions($options);
        }
        return $defaultParser ?: $this->createTldParser()->setOptions($options);
    }

    /**
     * @param string $type
     * @return TldParser
     */
    public function createTldParser($type = null)
    {
        $type = $type ? $type : TldParser::AUTO;
        $d = [
            TldParser::AUTO => AutoParser::class,
            TldParser::COMMON => CommonParser::class,
            TldParser::COMMON_FLAT => CommonParser::class,
            TldParser::BLOCK => BlockParser::class,
            TldParser::INDENT => IndentParser::class,
            TldParser::INDENT_AUTOFIX => IndentParser::class,
        ];
        return $this->createTldParserByClass($d[$type], $type);
    }

    /**
     * @param string $className
     * @param string $configType
     * @return TldParser
     */
    public function createTldParserByClass($className, $configType = null)
    {
        $configType = empty($configType) ? TldParser::AUTO : $configType;
        $config = $this->getTldParserConfigByType($configType);

        /* @var $parser TldParser */
        $parser = new $className();
        $parser->setConfig($config);
        if ($parser->getType() == TldParser::AUTO) {
            $this->setupTldAutoParser($parser, $config);
        }

        return $parser;
    }

    /**
     * @param AutoParser $parser
     * @param array $config
     */
    protected function setupTldAutoParser(AutoParser $parser, $config = [])
    {
        /* @var $autoParser AutoParser */
        foreach ($config['parserTypes'] ?? [] as $type) {
            $parser->addParser($this->createTldParser($type));
        }
    }

    /**
     * @param string $type
     * @return array
     */
    public function getTldParserConfigByType($type)
    {
        if ($type == TldParser::COMMON_FLAT) {
            $type = TldParser::COMMON;
            $extra = ['isFlat' => true];
        }
        if ($type == TldParser::INDENT_AUTOFIX) {
            $type = TldParser::INDENT;
            $extra = ['isAutofix' => true];
        }
        $config = Config::load("module.tld.parser.$type");
        return empty($extra) ? $config : array_merge($config, $extra);
    }

    /**
     * @param array $configs|null
     * @param AsnParser $defaultParser
     * @return AsnServer[]
     */
    public function createAsnSevers($configs = null, AsnParser $defaultParser = null): array
    {
        $configs = is_array($configs) ? $configs : Config::load("module.asn.servers");
        $defaultParser = $defaultParser ?: $this->createAsnParser();
        $servers = [];
        foreach ($configs as $config) {
            $servers[] = $this->createAsnSever($config, $defaultParser);
        }
        return $servers;
    }

    /**
     * @param array $config
     * @param AsnParser $defaultParser
     * @return AsnServer
     */
    public function createAsnSever($config, AsnParser $defaultParser = null)
    {
        $host = $config['host'] ?? '';
        if (empty($host)) {
            throw new InvalidArgumentException("Host must be specified");
        }
        return new AsnServer(
            $host,
            $this->createAsnSeverParser($config, $defaultParser),
            $config['queryFormat'] ?? AsnServer::DEFAULT_QUERY_FORMAT,
        );
    }

    /**
     * @param array $config
     * @param AsnParser|null $defaultParser
     * @return AsnParser
     */
    public function createAsnSeverParser(array $config, AsnParser $defaultParser = null): AsnParser
    {
        if (isset($config['parserClass'])) {
            return $this->createAsnParserByClass($config['parserClass']);
        }
        return $defaultParser ?: $this->createAsnParser();
    }

    /**
     * @return AsnParser
     */
    public function createAsnParser(): AsnParser
    {
        return new AsnParser();
    }

    /**
     * @param string $className
     * @return AsnParser
     */
    public function createAsnParserByClass($className): AsnParser
    {
        return new $className();
    }

}
