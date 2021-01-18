<?php

namespace LazyBundle\DependencyInjection;

use LazyBundle\Command\DeployFtpCommand;
use LazyBundle\DataCollector\CacheProviderDataCollector;
use LazyBundle\EventListener\MappingListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class LazyExtension extends Extension implements PrependExtensionInterface {
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
        if ($container->hasExtension('doctrine')) {
            $configs = $container->getExtensionConfig('lazy');
            while ($config = array_shift($configs)) {
                foreach ($config['dql_extensions'] as $dqlExtensionPack) {
                    $loader->load('../../../../../../beberlei/doctrineextensions/config/'.$dqlExtensionPack.'.yml');
                }
            }
        }
        foreach (['framework', 'doctrine'] as $extensionName) {
            if ($tmpContainer->hasExtension($extensionName)) {
                foreach ($tmpContainer->getExtensionConfig($extensionName) as $config) {
                    $container->prependExtensionConfig($extensionName, $config);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container) {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->getDefinition(MappingListener::class)->addMethodCall('setSlcEntityNames', [$config['second_level_cache']['entity_names']]);
        $container->getDefinition(DeployFtpCommand::class)->addMethodCall('setConfig', [$config['deploy_ftp']]);
        if (!empty($config['default_cache_provider'])) {
            $container->getDefinition(CacheProviderDataCollector::class)->setArgument(0, new Reference($config['default_cache_provider']));
        }
    }
}
