<?php

declare(strict_types=1);

namespace App\Environment\Configuration;

use App\Exception\FilesystemException;
use App\Middleware\Binary\Mkcert;
use Ergebnis\Environment\Variables;
use Symfony\Component\Filesystem\Filesystem;

class AbstractConfiguration
{
    public const INSTALLATION_DIRECTORY = '/var/docker/';

    protected const DATABASE_IMAGE_OPTION_NAME = 'DOCKER_DATABASE_IMAGE';
    protected const PHP_IMAGE_OPTION_NAME = 'DOCKER_PHP_IMAGE';
    protected const BLACKFIRE_PARAMETERS = [
        'BLACKFIRE_CLIENT_ID',
        'BLACKFIRE_CLIENT_TOKEN',
        'BLACKFIRE_SERVER_ID',
        'BLACKFIRE_SERVER_TOKEN',
    ];

    protected Mkcert $mkcert;
    protected Variables $systemVariables;

    public function __construct(Mkcert $mkcert, Variables $systemVariables)
    {
        $this->mkcert = $mkcert;
        $this->systemVariables = $systemVariables;
    }

    /**
     * Prepares the project directory with environment files.
     */
    protected function copyEnvironmentFiles(string $source, string $destination): void
    {
        $filesystem = new Filesystem();

        // Create the directory where all configuration files will be stored
        $filesystem->mkdir($destination);

        // Copy the environment files into the project directory
        $filesystem->mirror($source, $destination, null, ['override' => true]);

        // Copy the common dotenv file into the project directory
        $filesystem->copy("{$source}/../.env", "{$destination}/.env", true);

        // Create the directory where Mkcert will store locally-trusted development certificate for Nginx
        $filesystem->mkdir("{$destination}/nginx/certs");
    }

    /**
     * Loads Blackfire credentials from the environment variables and updates the environment dotenv file.
     *
     * @throws FilesystemException
     */
    protected function loadBlackfireParameters(string $destination): void
    {
        $filename = "{$destination}/.env";
        foreach (self::BLACKFIRE_PARAMETERS as $parameter) {
            if ($this->systemVariables->has($parameter)) {
                $this->updateEnvironment($filename, $parameter, $this->systemVariables->get($parameter));
            }
        }
    }

    /**
     * Updates the environment dotenv file with the given parameter/value pair.
     *
     * @throws FilesystemException
     */
    protected function updateEnvironment(string $filename, string $parameter, string $value): void
    {
        if (!$configuration = file_get_contents($filename)) {
            // @codeCoverageIgnoreStart
            throw new FilesystemException(
                sprintf("Unable to load the environment configuration.\n%s", $filename)
            );
            // @codeCoverageIgnoreEnd
        }

        if (!$updates = preg_replace("/{$parameter}=.*/", "{$parameter}={$value}", $configuration)) {
            // @codeCoverageIgnoreStart
            throw new FilesystemException(
                sprintf("Unable to parse the environment configuration.\n%s", $filename)
            );
            // @codeCoverageIgnoreEnd
        }

        if (!file_put_contents($filename, $updates)) {
            // @codeCoverageIgnoreStart
            throw new FilesystemException(
                sprintf("Unable to update the environment configuration.\n%s", $filename)
            );
            // @codeCoverageIgnoreEnd
        }
    }
}
