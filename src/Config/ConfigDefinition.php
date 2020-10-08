<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Config;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->arrayNode('db')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->end()
                        ->scalarNode('database')->end()
                        ->scalarNode('schema')->end()
                        ->scalarNode('user')->end()
                        ->scalarNode('#password')->end()
                    ->end()
                ->end()
                ->arrayNode('blocks')
                    ->isRequired()
                    ->prototype('array')
                    ->children()
                        ->scalarNode('name')
                            ->isRequired()
                        ->end()
                        ->arrayNode('codes')
                            ->isRequired()
                            ->prototype('array')
                            ->children()
                                ->scalarNode('name')
                                    ->isRequired()
                                ->end()
                                ->arrayNode('script')
                                    ->isRequired()
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
