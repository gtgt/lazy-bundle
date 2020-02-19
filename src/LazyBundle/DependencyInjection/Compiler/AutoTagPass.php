<?php

namespace LazyBundle\DependencyInjection\Compiler;

use LazyBundle\DependencyInjection\Configuration\StrictConfigurationAwareInterface;
use LazyBundle\Manager\AbstractManager;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
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
    ];

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) {
        $instanceof = [];
        foreach (static::$classesToTag as $class => $tagName) {
            $instanceof[$class] = (new ChildDefinition(''))->addTag($tagName);
        }
        foreach ($container->getDefinitions() as $definition) {
            if (!$definition instanceof ChildDefinition) {
                $definition->setInstanceofConditionals($definition->getInstanceofConditionals() + $instanceof);
            }
        }

    }
}
