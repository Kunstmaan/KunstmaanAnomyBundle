<?php

namespace Kunstmaan\AnomyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class KunstmaanAnomyExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        if (empty($config['backup_dir']) || empty($config['database_user']) || empty($config['database_password']) || empty($config['config_file'])) {
            throw new \RuntimeException('You need to provide all parameters for anomy to work.');
        }

        $container->setParameter('kunstmaan_anomy.backup_dir', $config['backup_dir']);
        $container->setParameter('kunstmaan_anomy.database_user', $config['database_user']);
        $container->setParameter('kunstmaan_anomy.database_password', $config['database_password']);
        $container->setParameter('kunstmaan_anomy.config_file', $config['config_file']);
    }
}
