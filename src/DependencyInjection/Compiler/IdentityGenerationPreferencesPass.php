<?php

namespace App\DependencyInjection\Compiler;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Entities use the portable #[ORM\GeneratedValue] (AUTO) strategy. On Postgres, AUTO is ambiguous
 * between SEQUENCE and IDENTITY (see https://github.com/doctrine/orm/issues/8893), so we pin the
 * preference here instead of hardcoding a strategy in every entity.
 */
class IdentityGenerationPreferencesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Doctrine builds its Configuration service itself; queueing a method call here is the
        // only way to run extra setup on it once the container constructs it.
        $container->getDefinition('doctrine.orm.default_configuration')
            ->addMethodCall('setIdentityGenerationPreferences', [[
                PostgreSQLPlatform::class => ClassMetadata::GENERATOR_TYPE_SEQUENCE,
            ]]);
    }
}
