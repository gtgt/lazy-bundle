<?php
namespace LazyBundle\DependencyInjection\Compiler;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class LoggerAwarePass
 *
 * Helper to set logger for the classes using LoggerAwareInterface
 */
class LoggerAwarePass implements CompilerPassInterface {

    /**
     * @param ContainerBuilder $container
     *
     * @throws \ReflectionException
     */
    public function process(ContainerBuilder $container) {
        if (!$container->has('logger')) {
            return;
        }
        $logger = new Reference('logger');

        foreach ($container->findTaggedServiceIds('lazy.logger_aware') as $id => $tagConfig) {
            if ($container->hasAlias($id)) {
                // don't brother with alias. the aliased service will be processed
                continue;
            }
            $definition = $container->getDefinition($id);
            // also skip non-autowired or syntetic services
            if (!$definition->isAutowired() || $definition->isSynthetic()) {
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
            if ($r->implementsInterface(LoggerAwareInterface::class)) {
                $definition->addMethodCall('setLogger', [$logger]);
            }
        }
    }
}
