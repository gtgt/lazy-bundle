<?php


namespace LazyBundle\DependencyInjection\Compiler;


use LazyBundle\DependencyInjection\Configuration\StrictConfigurationAwareInterface;
use LazyBundle\DependencyInjection\Configuration\StrictConfigurationChecker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class StrictConfigurationCheckerPass implements CompilerPassInterface {
    use CompilerPassTrait;
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container) {
        if (!$container->getParameter('kernel.debug')) {
            return;
        }
        $checkerDef = $container->register(StrictConfigurationChecker::class, StrictConfigurationChecker::class)->setArguments([
            new Reference('validator')
        ]);
        // $parameterBag = $container->getParameterBag();
        foreach ($container->findTaggedServiceIds('lazy.strict_configuration_aware') as $id => $tagConfig) {
            if ($container->hasAlias($id)) {
                // don't brother with aliases. the aliased service will be processed
                continue;
            }
            $definition = $container->getDefinition($id);
            // also skip synthetic & abstract services
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }
            // resolve classname
            if (!$r = $this->getReflectionClass($container, $definition)) {
                continue;
            }
            if ($r->implementsInterface(StrictConfigurationAwareInterface::class)) {
                $checkerDef->addMethodCall('addService', [new Reference($id), $id]);
            }
        }
    }
}
