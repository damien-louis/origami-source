<?php

declare(strict_types=1);

namespace App\Command;

use App\Environment\EnvironmentEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractBaseCommand extends Command
{
    /**
     * Prints additional details to the console: environment location and environment type.
     */
    protected function printEnvironmentDetails(EnvironmentEntity $environment, SymfonyStyle $io): void
    {
        $io->success('An environment is currently running.');
        $io->listing(
            [
                sprintf('Environment location: %s', $environment->getLocation()),
                sprintf('Environment type: %s', $environment->getType()),
            ]
        );
    }
}
