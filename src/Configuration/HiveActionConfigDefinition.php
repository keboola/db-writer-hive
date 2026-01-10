<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Configuration;

use Keboola\DbWriter\Configuration\Nodes\HiveSslNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class HiveActionConfigDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('parameters');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        // @formatter:off
        $rootNode
            ->ignoreExtraKeys(false)
            ->children()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('writer_class')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('db')
                    ->isRequired()
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->end()
                        ->scalarNode('database')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('user')
                            ->isRequired()
                        ->end()
                        ->scalarNode('password')->end()
                        ->scalarNode('#password')->end()
                        ->append($this->addSshNode())
                        ->append($this->addSslNode())
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $treeBuilder;
    }

    public function addSshNode(): NodeDefinition
    {
        $builder = new TreeBuilder();
        $node = $builder->root('ssh');

        // @formatter:off
        $node
            ->children()
                ->booleanNode('enabled')->end()
                ->arrayNode('keys')
                    ->children()
                        ->scalarNode('private')->end()
                        ->scalarNode('#private')->end()
                        ->scalarNode('public')->end()
                    ->end()
                ->end()
                ->scalarNode('sshHost')->end()
                ->scalarNode('sshPort')
                    ->defaultValue('22')
                ->end()
                ->scalarNode('remoteHost')
                ->end()
                ->scalarNode('remotePort')
                ->end()
                ->scalarNode('localPort')
                    ->defaultValue('33006')
                ->end()
                ->scalarNode('user')->end()
            ->end();
        // @formatter:on

        return $node;
    }

    public function addSslNode(): NodeDefinition
    {
        return new HiveSslNode('ssl');
    }
}
