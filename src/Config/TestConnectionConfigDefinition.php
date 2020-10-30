<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Config;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class TestConnectionConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->arrayNode('db')
                    ->isRequired()
                    ->children()
                        ->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('port')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('database')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('schema')->end()
                        ->scalarNode('user')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('#password')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
