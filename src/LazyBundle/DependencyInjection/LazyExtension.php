<?php

namespace LazyBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration as DoctrineConfiguration;
use LazyBundle\EventListener\MappingListener;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
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
        if ($container->hasExtension('doctrine')) {
            $extension = $container->getExtension('doctrine');
            $tmpContainer = new ContainerBuilder();
            $tmpContainer->registerExtension($extension);
            $tmpContainer->setResourceTracking($container->isTrackingResources());
            $loader = new Loader\YamlFileLoader($tmpContainer, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('doctrine.yaml');
            foreach ($tmpContainer->getExtensionConfig('doctrine') as $config) {
                $container->prependExtensionConfig('doctrine', $config);
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
        $container->getDefinition(MappingListener::class)->replaceArgument(1, $config['second_level_cache']['entity_names']);
    }
}
