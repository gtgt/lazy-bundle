<?php /** @noinspection PhpUndefinedMethodInspection */

namespace LazyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('lazy');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->arrayNode('second_level_cache')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('entity_names')->prototype('scalar')->info('Entity names to configure second level cache automatically (even in vendor).')->defaultValue([])->end()->end()
                ->end()
            ->end()
            ->arrayNode('dql_extensions')->enumPrototype()->values(['mysql', 'oracle', 'postgres', 'sqlite'])->end()->end()
            ->arrayNode('deploy_ftp')
                ->useAttributeAsKey('name')
                ->prototype('array')
                    ->children()
                        ->scalarNode('hostname')->isRequired()->end()
                        ->scalarNode('path')->defaultValue('/')->end()
                        ->scalarNode('port')->defaultValue(21)->end()
                        ->scalarNode('user')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->end()
                        ->scalarNode('exclude_file')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
