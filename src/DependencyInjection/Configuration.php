<?php

namespace Kunstmaan\AnomyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('kunstmaan_anomy');

        $rootNode
            ->children()
                ->scalarNode('config_file')->end()
                ->scalarNode('backup_dir')->end()
                ->scalarNode('database_user')->end()
                ->scalarNode('database_password')->end()
            ->end();
        return $treeBuilder;
    }
}
