<?php

namespace LazyBundle\DependencyInjection;

use LazyBundle\EventListener\MappingListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class LazyExtension extends Extension implements PrependExtensionInterface {
    /**
     * {@inheritDoc}
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
                foreach ($tmpContainer->getExtensionConfig($extensionName) as $config) {
                    $container->prependExtensionConfig($extensionName, $config);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container) {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->getDefinition(MappingListener::class)->addMethodCall('setSlcEntityNames', [$config['second_level_cache']['entity_names']]);
    }
}
