<?php

declare(strict_types=1);

namespace App\Environment;

use App\Environment\EnvironmentMaker\DockerHub;
use App\Environment\EnvironmentMaker\RequirementsChecker;
use App\Environment\EnvironmentMaker\TechnologyIdentifier;
use App\Exception\InvalidConfigurationException;
use App\Helper\Validator;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class EnvironmentMaker
{
    private array $availableTypes = [
        EnvironmentEntity::TYPE_DRUPAL,
        EnvironmentEntity::TYPE_MAGENTO2,
        EnvironmentEntity::TYPE_OROCOMMERCE,
        EnvironmentEntity::TYPE_SYLIUS,
        EnvironmentEntity::TYPE_SYMFONY,
    ];

    private TechnologyIdentifier $technologyIdentifier;
    private DockerHub $dockerHub;
    private RequirementsChecker $requirementsChecker;
    private Validator $validator;

    public function __construct(
        TechnologyIdentifier $technologyIdentifier,
        DockerHub $dockerHub,
        RequirementsChecker $requirementsChecker,
        Validator $validator
    ) {
        $this->technologyIdentifier = $technologyIdentifier;
        $this->dockerHub = $dockerHub;
        $this->requirementsChecker = $requirementsChecker;
        $this->validator = $validator;
    }

    /**
     * Asks the question about the environment name.
     */
    public function askEnvironmentName(SymfonyStyle $io, string $defaultName): string
    {
        return $io->ask('What is the name of the environment you want to install?', $defaultName);
    }

    /**
     * Asks the choice question about the environment type.
     */
    public function askEnvironmentType(SymfonyStyle $io, string $location): string
    {
        return $io->choice(
            'Which type of environment you want to install?',
            $this->availableTypes,
            $this->technologyIdentifier->identify($location)
        );
    }

    /**
     * Asks the choice question about the PHP version.
     */
    public function askPhpVersion(SymfonyStyle $io): string
    {
        $availableVersions = $this->dockerHub->getImageTags('ajardin/php');
        $defaultVersion = DockerHub::DEFAULT_IMAGE_VERSION;

        return \count($availableVersions) > 1
            ? $io->choice('Which version of PHP do you want to use?', $availableVersions, $defaultVersion)
            : $availableVersions[0]
        ;
    }

    /**
     * Asks the autocomplete question about the database version.
     */
    public function askDatabaseVersion(SymfonyStyle $io): string
    {
        $availableVersions = $this->dockerHub->getImageTags('library/mariadb');
        $defaultVersion = DockerHub::DEFAULT_IMAGE_VERSION;

        $question = new Question('Which version of MariaDB do you want to use?', $defaultVersion);
        $question->setAutocompleterValues($availableVersions);

        return $io->askQuestion($question);
    }

    /**
     * Asks the question about the environment domains.
     */
    public function askDomains(SymfonyStyle $io, string $name): ?string
    {
        if (!$this->requirementsChecker->canMakeLocallyTrustedCertificates()) {
            $io->warning('Generation of the locally-trusted development certificate skipped because the tool is not installed.');

            return null;
        }

        if ($io->confirm('Do you want to generate a locally-trusted development certificate?', false)) {
            $domains = $io->ask(
                'Which domains does this certificate belong to?',
                "{$name}.localhost",
                fn (string $answer): string => $this->localDomainsCallback($answer)
            );
        }

        return $domains ?? null;
    }

    /**
     * Validates the response provided by the user to the local domains question.
     *
     * @throws InvalidConfigurationException
     */
    private function localDomainsCallback(string $answer): string
    {
        if (!$this->validator->validateHostname($answer)) {
            throw new InvalidConfigurationException('The hostname provided is invalid.');
        }

        return $answer;
    }
}
