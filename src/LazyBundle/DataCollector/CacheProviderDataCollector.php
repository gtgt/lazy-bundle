<?php

namespace LazyBundle\DataCollector;

use LazyBundle\Util\Str;
use Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

class CacheProviderDataCollector extends DataCollector implements TemplateAwareDataCollectorInterface, LateDataCollectorInterface {
    /**
     * @var AdapterInterface
     */
    private $cacheProvider;

    /**
     * @param $defaultCacheProvider
     */
    public function __construct($defaultCacheProvider = null) {
        $this->cacheProvider = $defaultCacheProvider;
    }

    /**
     * @inheritDoc
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null) {

    }

    /**
     * @inheritDoc
     */
    public function lateCollect() {
        if ($this->cacheProvider === null) {
            return;
        }
        $this->data = ['type' => Str::getShortClassName(get_class($this->cacheProvider))];
        if ($this->cacheProvider instanceof \Memcached) {
            $stats = $this->cacheProvider->getStats();
            $stats = array_shift($stats);
            $this->data += [
                'usage' => $stats['bytes'] / 1024 / 1024,
                'total' => $stats['limit_maxbytes'] / 1024 / 1024,
                'items' => $stats['curr_items'],
            ];
        } elseif ($this->cacheProvider instanceof RedisProxy) {
            $stats = $this->cacheProvider->info('MEMORY');
            $this->data += [
                'usage' => $stats['used_memory_human'],
                'total' => $stats['maxmemory_human'],
                'items' => $this->cacheProvider->dbSize(),
            ];
        }
    }

    public function reset() {
        $this->data = [];
    }

    public function getName() {
        return self::class;
    }

    public function getData() {
        return $this->data;
    }

    public static function getTemplate(): ?string {
        return '@Lazy/Collector/cache_provider.html.twig';
    }
}