<?php

namespace LazyBundle\DependencyInjection;

use LazyBundle\Command\DeployFtpCommand;
use LazyBundle\DataCollector\CacheProviderDataCollector;
use LazyBundle\EventSubscriber\DoctrineMappingEventSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class LazyExtension extends Extension implements PrependExtensionInterface {

    /**
     * @var Reference|null
     */
    private $defaultCacheProvider;

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $container) {
        $tmpContainer = new ContainerBuilder();
        $tmpContainer->setResourceTracking($container->isTrackingResources());
        $loader = new Loader\YamlFileLoader($tmpContainer, new FileLocator(__DIR__.'/../Resources/config'));
        foreach (['framework', 'doctrine'] as $extensionName) {
            if ($container->hasExtension($extensionName)) {
                $extension = $container->getExtension($extensionName);
                $tmpContainer->registerExtension($extension);
                $loader->load($extensionName.'.yaml');

            }
        }
        $config = $this->getConfig($container);
        if ($container->hasExtension('doctrine')) {
            foreach ($config['dql_extensions'] as $dqlExtensionPack) {
                $loader->load('../../../../../../beberlei/doctrineextensions/config/'.$dqlExtensionPack.'.yml');
            }
        }
        $this->setupCacheProviders($tmpContainer, $config);
        $this->setupSession($tmpContainer, $config);

        foreach (['framework', 'doctrine'] as $extensionName) {
            if ($tmpContainer->hasExtension($extensionName)) {
                foreach ($tmpContainer->getExtensionConfig($extensionName) as $config) {
                    $container->prependExtensionConfig($extensionName, $config);
                }
            }
        }
        $container->addDefinitions($tmpContainer->getDefinitions());
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    protected function getConfig(ContainerBuilder $container): array {
        $processedConfigs = $this->getProcessedConfigs();
        if ($processedConfigs) {
            return array_shift($processedConfigs);
        }
        $configs = $container->getExtensionConfig($this->getAlias());
        return $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container) {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $config = $this->getConfig($container);
        $container->getDefinition(DoctrineMappingEventSubscriber::class)
            ->addMethodCall('setSlcEntityNames', [$config['second_level_cache']['entity_names']])
            ->addMethodCall('setEnableEnumTypes', [$config['enable_doctrine_enum_types']]);

        $container->getDefinition(DeployFtpCommand::class)->addMethodCall('setConfig', [$config['deploy_ftp']]);
        $container->getDefinition(CacheProviderDataCollector::class)->setArgument(0, $this->defaultCacheProvider);
    }

    /**
     * @param ContainerBuilder $container
     * @param array $config
     */
    private function setupCacheProviders(ContainerBuilder $container, array $config): void {
        // setup default cache provider for cache.app / cache.system
        $cacheProviderDsn = $container->resolveEnvPlaceholders($config['default_cache_provider'], true);
        if ($cacheProviderDsn) {
            $cacheProviderName = parse_url($cacheProviderDsn, PHP_URL_SCHEME);
            $cacheAdapterId = 'cache.adapter.'.$cacheProviderName;
            if ($cacheProviderName) {
                $defaultCacheProviderConfigName = 'default_'.$cacheProviderName.'_provider';
                $cacheConfig = [
                    'app' => $cacheAdapterId,
                    'system' => $cacheAdapterId,
                ];
                if (in_array($cacheProviderName, ['memcached', 'redis'])) {
                    $cacheConfig[$defaultCacheProviderConfigName] = $cacheProviderDsn;
                }
                $container->prependExtensionConfig('framework', ['cache' => $cacheConfig]);
                $this->defaultCacheProvider = new Reference('cache.'.$defaultCacheProviderConfigName);
            }
        }
    }
    private function setupSession(ContainerBuilder $container, array $config): void {
        // setup default cache provider for cache.app / cache.system
        $sessionHandlerDsn = $container->resolveEnvPlaceholders($config['session_handler'], true);
        if ($sessionHandlerDsn !== null) {
            if (0 === strpos($sessionHandlerDsn, 'php:')) {
                $handlerId = null;
            } else {
                $definition = new ChildDefinition('session.abstract_handler');
                $definition->replaceArgument(0, $sessionHandlerDsn);
                $container->setDefinition('session.lazy_handler', $definition)->setPublic(true);
                $handlerId = 'session.lazy_handler';
            }
            $container->prependExtensionConfig('framework', ['session' => ['handler_id' => $handlerId]]);
        }
    }
}
