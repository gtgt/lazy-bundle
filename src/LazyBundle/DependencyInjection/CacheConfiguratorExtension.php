<?php

namespace LazyBundle\DependencyInjection;

use LazyBundle\DataCollector\CacheProviderDataCollector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

class CacheConfiguratorExtension extends Extension implements PrependExtensionInterface {
    private $cacheProviderDataCollectorServiceId;

    public function __construct(string $cacheProviderDataCollectorServiceId = null) {
        $this->cacheProviderDataCollectorServiceId = $cacheProviderDataCollectorServiceId ?? CacheProviderDataCollector::class;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container) {
        if (!isset($container->getExtensions()['framework'])) {
            return;
        }
        $cacheProviderDsn = $_SERVER['CACHE_PROVIDER'] ?? null;
        if ($cacheProviderDsn) {
            $cacheProviderName = parse_url($cacheProviderDsn, PHP_URL_SCHEME);
            $cacheAdapterId = 'cache.adapter.'.$cacheProviderName;
            if ($cacheProviderName) {
                $cacheConfig = [
                    'app' => $cacheAdapterId,
                    'system' => $cacheAdapterId,
                ];
                if (in_array($cacheProviderName, ['memcached', 'redis'])) {
                    $cacheConfig['default_'.$cacheProviderName.'_provider'] = $cacheProviderDsn;
                }
                $container->prependExtensionConfig('framework', ['cache' => $cacheConfig]);
                $defaultProvider = 'cache.default_'.$cacheProviderName.'_provider';
            }
        }
        if (!isset($defaultProvider)) {
            return;
        }
        $container->prependExtensionConfig('lazy', ['default_cache_provider' => $defaultProvider]);
    }

    public function load(array $configs, ContainerBuilder $container) {

    }
}