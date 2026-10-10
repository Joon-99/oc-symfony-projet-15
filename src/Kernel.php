<?php

namespace App;

use App\DependencyInjection\Compiler\IdentityGenerationPreferencesPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        date_default_timezone_set($this->getContainer()->getParameter('timezone'));
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new IdentityGenerationPreferencesPass());
    }
}
