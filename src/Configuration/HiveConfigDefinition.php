<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class HiveConfigDefinition extends HiveActionConfigDefinition
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
                    ->end()
                ->end()
                ->arrayNode('tables')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('tableId')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('dbName')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->booleanNode('incremental')
                                ->defaultValue(false)
                            ->end()
                            ->booleanNode('export')
                                ->defaultValue(true)
                            ->end()
                            ->arrayNode('primaryKey')
                                ->prototype('scalar')
                                ->end()
                            ->end()
                            ->booleanNode('bcp')
                                ->defaultValue(false)
                            ->end()
                            ->arrayNode('items')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('name')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->scalarNode('dbName')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->scalarNode('type')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->scalarNode('size')
                                        ->end()
                                        ->scalarNode('nullable')
                                        ->end()
                                        ->scalarNode('default')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $treeBuilder;
    }
}
