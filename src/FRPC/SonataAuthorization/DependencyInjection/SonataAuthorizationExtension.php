<?php
/*
 * DOO TECHGURU Confidential
 * Copyright (C) 2022 DOO TECHGURU.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of DOO TECHGURU, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to DOO TECHGURU
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace FRPC\SonataAuthorization\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class SonataAuthorizationExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        \assert(\is_array($bundles));

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        if (isset($bundles['SonataAdminBundle'])) {
            $loader->load('security.yaml');
        }

        $loader->load('command.yaml');
        $loader->load('controller.yaml');
        $loader->load('form.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);
        foreach ($config as $key => $cfg) {
            $container->setParameter(sprintf('%s.%s', $this->getAlias(), $key), $cfg);
        }
    }

    public function prepend(ContainerBuilder $container) : void
    {
        if ($container->hasExtension('twig')) {
            // add custom form widgets
            $container->prependExtensionConfig('twig',
                [
                    'form_themes' => [
                        '@SonataAuthorization/Admin/Form/Security/admin_fields.html.twig',
                        '@SonataAuthorization/Admin/Form/plain_password.html.twig',
                    ],
                ]);
        }
    }
}