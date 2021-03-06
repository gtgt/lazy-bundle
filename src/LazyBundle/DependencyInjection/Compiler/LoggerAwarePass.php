<?php
namespace LazyBundle\DependencyInjection\Compiler;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class LoggerAwarePass
 *
 * Helper to set logger for the classes using LoggerAwareInterface
 */
class LoggerAwarePass implements CompilerPassInterface {
    use CompilerPassTrait;

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container) {
        if (!$container->has('logger')) {
            return;
        }
        $logger = new Reference('logger');

        foreach ($container->findTaggedServiceIds('lazy.logger_aware') as $id => $tagConfig) {
            $this->addMethodCall($container, $id, 'setLogger', [$logger], LoggerAwareInterface::class);
        }
    }
}
