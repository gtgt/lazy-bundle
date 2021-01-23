<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LazyBundle\DependencyInjection;

use Doctrine\Common\Collections\ArrayCollection;
use LazyBundle\Service\ExtensionConfigStore;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Define some ExpressionLanguage functions.
 *
 * To get a service, use service('request').
 * To get a parameter, use parameter('kernel.debug').
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface {
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var ArrayCollection
     */
    private $extensionConfigs;

    public function __construct(ContainerBuilder $container) {
        $this->container = $container;
        $this->extensionConfigs = new ArrayCollection();
    }

    public function getFunctions(): array {
        return [
            new ExpressionFunction('config', function (string $extensionName, string ...$name) {
                $extensionName = trim($extensionName, '"\'');
                $config = $this->extensionConfigs->get($extensionName);
                if (!$config) {
                    if (!$this->container->hasExtension($extensionName)) {
                        throw new ParameterNotFoundException($extensionName, null, 'config');
                    }
                    /** @var Extension $extensionName */
                    $extension = $this->container->getExtension($extensionName);
                    $configs = $this->container->getExtensionConfig($extensionName);

                    if (!$configs) {
                        throw new InvalidArgumentException(sprintf('Extension %s (%s) doesn\'t have config. Maybe implement %s or load it manually.', $extensionName, \get_class($extension), ConfigurableExtension::class));
                    }
                    $config = $extension->getProcessedConfigs();
                    if ($config) {
                        $config = array_shift($config);
                    } else {
                        $configuration = $extension->getConfiguration($configs, $this->container);
                        if (!$configuration) {
                            throw new InvalidArgumentException(sprintf('Extension %s (%s) doesn\'t have configuration class (%s).', $extensionName, \get_class($extension), ConfigurationInterface::class));
                        }
                        $processor = new Processor();
                        $config = $processor->processConfiguration($configuration, $configs);
                    }
                    $this->extensionConfigs->set($extensionName, $config);
                }
                while ($name) {
                    $key = trim(array_shift($name), '"\'');
                    if (!isset($config[$key])) {
                        throw new InvalidArgumentException(sprintf('There are no %s key in %s extension config..', $name, $extensionName));
                    }
                    $config = $config[$key];
                }
                return var_export($config, true);
            }, function (array $variables, string $extension, string ...$name) {
                return $variables['container']->get(ExtensionConfigStore::class)->getConfig($extension, ...$name);
            }),
        ];
    }
}
