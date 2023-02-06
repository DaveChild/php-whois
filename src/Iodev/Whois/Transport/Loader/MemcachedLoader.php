<?php

declare(strict_types=1);

namespace Iodev\Whois\Transport\Loader;

use \Memcached;
use Iodev\Whois\Error\ConnectionException;
use Iodev\Whois\Error\WhoisException;

class MemcachedLoader implements LoaderInterface
{
    public function __construct(
        protected LoaderInterface $loader,
        protected Memcached $memcached,
        protected string $keyPrefix = "",
        protected int $ttl = 3600,
    ) {}

    /**
     * @throws ConnectionException
     * @throws WhoisException
     */
    public function loadText(string $whoisHost, string $query): string
    {
        $key = $this->keyPrefix . md5(serialize([$whoisHost, $query]));
        $val = $this->memcached->get($key);
        if ($val) {
            return unserialize($val);
        }
        $val = $this->loader->loadText($whoisHost, $query);
        $this->memcached->set($key, serialize($val), $this->ttl);
        return $val;
    }
}