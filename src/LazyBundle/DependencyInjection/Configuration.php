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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('debug');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->arrayNode('second_level_cache')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('entity_names')->prototype('scalar')->info('Entity names to configure second level cache automatically (even in vendor).')->defaultValue([])->end()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
