<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class HiveConfigRowDefinition extends HiveActionConfigRowDefinition
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('parameters');
        $rootNode = $treeBuilder->getRootNode();

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
                ->arrayNode('items')
                    ->isRequired()
                    ->cannotBeEmpty()
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
                        ->scalarNode('size')->end()
                        ->booleanNode('nullable')
                            ->defaultValue(false)
                        ->end()
                        ->booleanNode('default')
                            ->defaultValue(false)
                        ->end()
                    ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
