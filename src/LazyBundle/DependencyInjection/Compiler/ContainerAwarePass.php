<?php
namespace LazyBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class ContainerAwarePass
 *
 * Helper to set logger for the classes using LoggerAwareInterface
 */
class ContainerAwarePass implements CompilerPassInterface {
    use CompilerPassTrait;

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container) {
        if (!$container->has('service_container')) {
            return;
        }
        $containerReference = new Reference('service_container');

        foreach ($container->findTaggedServiceIds('lazy.container_aware') as $id => $tagConfig) {
            $this->addMethodCall($container, $id, 'setContainer', [$containerReference], ContainerAwareInterface::class);
        }
    }
}
