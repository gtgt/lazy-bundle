<?php
namespace LazyBundle;

use LazyBundle\DependencyInjection\Compiler\LoggerAwarePass;
use LazyBundle\DependencyInjection\Compiler\ManagerCompilerPass;
use LazyBundle\DependencyInjection\Compiler\StrictConfigurationCheckerPass;
use LazyBundle\DependencyInjection\Configuration\StrictConfigurationChecker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LazyBundle extends Bundle {
    private $isContainerFresh = false;

    /**
     * {@inheritDoc}
     */
    public function build(ContainerBuilder $container) {
        parent::build($container);
        $container->addCompilerPass(new LoggerAwarePass());
        $container->addCompilerPass(new ManagerCompilerPass());
        $container->addCompilerPass(new StrictConfigurationCheckerPass());
        $this->isContainerFresh = true;
    }

    /**
     * {@inheritDoc}
     */
    public function boot() {
        parent::boot();
        if ($this->isContainerFresh && $this->container->has(StrictConfigurationChecker::class)) {
            $this->container->get(StrictConfigurationChecker::class)->check();
        }
    }
}
