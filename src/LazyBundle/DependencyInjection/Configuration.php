<?php /** @noinspection PhpUndefinedMethodInspection */

namespace LazyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
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
            ->scalarNode('default_cache_provider')->defaultValue('%env(CACHE_PROVIDER)%')->end()
        ;

        $cronNode = $rootNode
            ->children()
            ->arrayNode('cron')
            ->info('Cron settings')
            ->addDefaultsIfNotSet();

        $this->addCronGlobalsNode($cronNode);
        $this->addCronJobsNode($cronNode);
        $cronNode->end();

        return $treeBuilder;
    }

    private function addCronGlobalsNode(ArrayNodeDefinition $rootNode): void
    {
        $cronGlobalsNode = $rootNode
            ->children()
            ->arrayNode('globals')
            ->info('Global settings for cron')
            ->addDefaultsIfNotSet();

        $this->appendSharedNodes($cronGlobalsNode->children());
        $cronGlobalsNode->end();
    }

    private function addCronJobsNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
            ->scalarNode('php_executable')->defaultValue('php')->end()
            ->end();
        $cronJobsNode = $rootNode
            ->children()
            ->arrayNode('jobs')
            ->defaultValue([])
            ->info('Here you can define your cron jobs')
            ->useAttributeAsKey('name', false);

        $prototypeNode = $cronJobsNode->arrayPrototype()->info('Job configuration');

        $prototypeNode
            ->children()
            ->scalarNode('schedule')->defaultValue('* * * * *')->info('Crontab schedule format (man -s 5 crontab) or DateTime format (Y-m-d H:i:s)')->end()
            ->scalarNode('command')->isRequired()->cannotBeEmpty()->info('Command to execute')->end()
            ->booleanNode('is_symfony_command')->defaultTrue()->info("If set to true, bundle will decorate *command* with 'php path/bin/console {command}'")
        ;

        $this->appendSharedNodes($prototypeNode->children());

        $prototypeNode->end();
        $cronJobsNode->end();
    }

    private function appendSharedNodes(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('runAs')->defaultNull()->info('Run as this user, if crontab user has *sudo* privileges')->end()
            ->booleanNode('debug')->defaultFalse()->info("Send *jobby* internal messages to 'debug.log'")->end()
            ->scalarNode('environment')->defaultNull()->info('Development environment for this job')->end()
            ->scalarNode('runOnHost')->defaultValue(gethostname())->info('Run jobs only on this hostname')->end()
            ->integerNode('maxRuntime')->defaultNull()->info('Maximum execution time for this job (in seconds)')->end()
            ->booleanNode('enabled')->defaultTrue()->info('Run this job at scheduled times')->end()
            ->scalarNode('haltDir')->defaultNull()->info('A job will not run if this directory contains a file bearing the job\'s name')->end()
            ->scalarNode('output')->defaultValue('/dev/null')->info('Redirect *stdout* and *stderr* to this file')->end()
            ->scalarNode('output_stdout')->defaultValue('/dev/null')->info('Redirect *stdout* to this file')->end()
            ->scalarNode('output_stderr')->defaultValue('/dev/null')->info('Redirect *stderr* to this file')->end()
            ->scalarNode('dateFormat')->defaultValue('Y-m-d H:i:s')->info('Format for dates on *jobby* log messages')->end()
            ->scalarNode('recipients')->defaultNull()->info('Comma-separated string of email addresses')->end()
            ->scalarNode('mailer')->defaultValue('sendmail')->info('Email method: *sendmail* or *smtp* or *mail*')->end()
            ->scalarNode('smtpHost')->defaultNull()->info('SMTP host, if *mailer* is smtp')->end()
            ->integerNode('smtpPort')->defaultValue(25)->info('SMTP port, if *mailer* is smtp')->end()
            ->scalarNode('smtpUsername')->defaultNull()->info('SMTP user, if *mailer* is smtp')->end()
            ->scalarNode('smtpPassword')->defaultNull()->info('SMTP password, if *mailer* is smtp')->end()
            ->scalarNode('smtpSecurity')->defaultNull()->info('SMTP security option: *ssl* or *tls*, if *mailer* is smtp')->end()
            ->scalarNode('smtpSender')->defaultValue('jobby@<hostname>')->info('The sender and from addresses used in SMTP notices')->end()
            ->scalarNode('smtpSenderName')->defaultValue('Jobby')->info('The name used in the from field for SMTP messages')->end()
        ;
    }
}
