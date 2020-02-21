<?php

namespace LazyBundle\DependencyInjection\Compiler;

use LazyBundle\DependencyInjection\Configuration\StrictConfigurationAwareInterface;
use LazyBundle\EntityListener\EntityListenerInterface;
use LazyBundle\Manager\AbstractManager;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutoTagPass implements CompilerPassInterface {
    use CompilerPassTrait;
    protected static $classesToTag = [
        ContainerAwareInterface::class => 'lazy.container_aware',
        LoggerAwareInterface::class => 'lazy.logger_aware',
        StrictConfigurationAwareInterface::class => 'lazy.strict_configuration_aware',
        AbstractManager::class => 'lazy.manager',
        EntityListenerInterface::class => ['doctrine.orm.entity_listener', ['lazy' => true]]
    ];

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) {
        foreach (static::$classesToTag as $class => $tag) {
            $container->registerForAutoconfiguration($class)->addTag(is_array($tag) ? $tag[0] : $tag, is_array($tag) ? $tag[1] : []);
        }
    }
}
