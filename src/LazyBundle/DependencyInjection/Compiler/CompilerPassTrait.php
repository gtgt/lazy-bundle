<?php

namespace LazyBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

trait CompilerPassTrait {
    /**
     * @param ContainerBuilder $container
     * @param Definition $definition
     * @return \ReflectionClass|null
     */
    protected function getReflectionClass(ContainerBuilder $container, Definition $definition): ?\ReflectionClass {
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
            return null;
        }
        try {
            return $container->getReflectionClass($className);
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $id
     * @param string $method
     * @param array $arguments
     * @param string|null $classRestriction
     * @return bool
     */
    protected function addMethodCall(ContainerBuilder $container, string $id, string $method, array $arguments, string $classRestriction = null): bool {
        if ($container->hasAlias($id)) {
            // don't brother with alias. the aliased service will be processed
            return false;
        }
        $definition = $container->getDefinition($id);
        // also skip non-autowired or syntetic services
        if (!$definition->isAutowired() || $definition->isSynthetic()) {
            return false;
        }
        if (null !== $classRestriction) {
            $r = $this->getReflectionClass($container, $definition);
            if ($r === null || !$r->implementsInterface($classRestriction)) {
                return false;
            }
        }
        if (!in_array([$method, $arguments], $definition->getMethodCalls(), true)) {
            $definition->addMethodCall($method, $arguments);
        }
        return true;
    }
}
