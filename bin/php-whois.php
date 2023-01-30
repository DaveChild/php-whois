<?php

declare(strict_types=1);

use Iodev\Whois\Container\Default\Container;
use Iodev\Whois\Container\Default\ContainerBuilder;
use Iodev\Whois\Loader\LoaderInterface;
use Iodev\Whois\Module\Tld\TldModule;
use Iodev\Whois\Module\Tld\TldParserProviderInterface;
use Iodev\Whois\Module\Tld\TldServer;
use Iodev\Whois\Whois;

$scriptDir = '.';
if (preg_match('~^(.+?)/[^/]+$~ui', $_SERVER['SCRIPT_FILENAME'], $m)) {
    $scriptDir = $m[1];
}
include "$scriptDir/../vendor/autoload.php";

function main($argv)
{
    $action = trim($argv[1] ?? '');
    $args = array_slice($argv, 2);

    if (empty($action)) {
        $action = 'help';
    }
    switch (mb_strtolower(ltrim($action, '-'))) {
        case 'help':
        case 'h':
            help();
            return;
    }
    switch ($action) {
        case 'lookup':
            lookup($args[0]);
            break;

        case 'info':
            $opts = parseOpts(implode(' ', array_slice($args, 1)));
            info($args[0], $opts);
            break;

        default:
            echo "Unknown action: {$action}\n";
            exit(1);
    }
}

function parseOpts(string $str): array
{
    $result = [];
    $rest = trim($str);
    while (preg_match('~--([-_a-z\d]+)(\s+|=)(\'([^\']+)\'|[^-\s]+)~ui', $rest, $m, PREG_OFFSET_CAPTURE)) {
        $result[$m[1][0]] = $m[4][0] ?? $m[3][0];
        $rest = trim(mb_substr($rest, $m[0][1] + mb_strlen($m[0][0])));
    }
    return $result;
}

function getContainer(): Container
{
    static $container = null;
    if ($container === null) {
        $container = (new ContainerBuilder())->configure()->getContainer();
    }
    return $container;
}

function help()
{
    echo implode("\n", [
        'Welcome to php-whois CLI',
        '',
        '  Syntax:',
        '    php-whois {action} [arg1 arg2 ... argN]',
        '    php-whois help|--help|-h',
        '    php-whois lookup {domain}',
        '    php-whois info {domain} [--parser {type}] [--host {whois}]',
        '',
        '  Examples',
        '    php-whois lookup google.com',
        '    php-whois info google.com',
        '    php-whois info google.com --parser block',
        '    php-whois info ya.ru --host whois.nic.ru --parser auto',
        '',
        '',
    ]);
}

function lookup(string $domain)
{
    echo implode("\n", [
        '  action: lookup',
        "  domain: '{$domain}'",
        '',
        '',
    ]);

    $whois = getContainer()->get(Whois::class);
    $result = $whois->lookupDomain($domain);

    var_dump($result);
}

function info(string $domain, array $options = [])
{
    $options = array_replace([
        'host' => null,
        'parser' => null,
        'file' => null,
    ], $options);

    echo implode("\n", [
        '  action: info',
        "  domain: '{$domain}'",
        sprintf("  options: %s", json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        '',
        '',
    ]);

    $loader = null;
    if ($options['file']) {
        $loader = new \Iodev\Whois\Loader\FakeSocketLoader();
        $loader->text = file_get_contents($options['file']);

        getContainer()->bind(LoaderInterface::class, function() use ($loader) {
            return $loader;
        });
    }

    /** @var TldModule */
    $tld = getContainer()->get(TldModule::class);
    $tldServers = $tld->getServerCollection()->getList();

    $servers = $tld->getServerMatcher()->match($tldServers, $domain);

    if (!empty($options['host'])) {
        $host = $options['host'];
        $filteredServers = array_filter($servers, function (TldServer $server) use ($host) {
            return $server->host == $host;
        });
        if (count($filteredServers) == 0 && count($servers) > 0) {
            $filteredServers = [$servers[0]];
        }
        $servers = array_map(function (TldServer $server) use ($host) {
            return new TldServer(
                $server->zone,
                $host,
                $server->centralized,
                $server->parser,
                $server->queryFormat,
            );
        }, $filteredServers);
    }

    if (!empty($options['parser'])) {
        try {
            /** @var TldParserProviderInterface */
            $tldParserProvider = getContainer()->get(TldParserProviderInterface::class);
            $parser = $tldParserProvider->getByType($options['parser']);
        } catch (\Throwable $e) {
            echo "\nCannot create TLD parser with type '{$options['parser']}'\n\n";
            throw $e;
        }
        $servers = array_map(function (TldServer $server) use ($parser) {
            return new TldServer(
                $server->zone,
                $server->host,
                $server->centralized,
                $parser,
                $server->queryFormat,
            );
        }, $servers);
    }

    $result = $tld->getLoader()->lookupDomain($domain, $servers);

    var_dump($result->info);
}

main($argv);


