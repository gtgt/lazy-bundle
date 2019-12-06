<?php

namespace LazyBundle\DependencyInjection\Compiler;

use LazyBundle\Manager\AbstractManager;
use LazyBundle\Manager\ManagerConfigurator;
use LazyBundle\Manager\ManagerRegistry;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ManagerCompilerPass implements CompilerPassInterface {
    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     * @return void
     */
    public function process(ContainerBuilder $container) {
        $registry = $container->getDefinition(ManagerRegistry::class);
        $registry->addMethodCall('setContainer', [new Reference('service_container')]);
        foreach ($container->findTaggedServiceIds('lazy.manager') as  $id => $tagConfig) {
            if ($id === AbstractManager::class || $container->hasAlias($id)) {
                // don't brother with the abstract or aliases. the aliased service will be processed
                continue;
            }
            $definition = $container->getDefinition($id);
            // also skip synthetic services
            if ($definition->isSynthetic()) {
                continue;
            }

            // resolve classname
            $className = $container->getParameterBag()->resolveValue($definition->getClass());
            if (null === $className && $definition instanceof ChildDefinition) {
                $childDefinition = $definition;
                do {
                    $childDefinition = $container->getDefinition($childDefinition->getParent());
                    $className = $container->getParameterBag()->resolveValue($childDefinition->getClass());
                } while (null === $className && $childDefinition instanceof ChildDefinition);
            }
            if (null === $className) {
                continue;
            }
            if (!$r = $container->getReflectionClass($className)) {
                continue;
            }
            if ($r->isSubclassOf(AbstractManager::class)) {
                $definition->setConfigurator([new Reference(ManagerConfigurator::class), 'configure']);
                // add to registry
                $registry->addMethodCall('add', [$id]);
            }
        }
    }
}
