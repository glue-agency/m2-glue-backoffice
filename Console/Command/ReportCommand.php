<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Console\Command;

use GlueAgency\Backoffice\Model\Client;
use GlueAgency\Backoffice\Model\Config;
use GlueAgency\Backoffice\Model\Report;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReportCommand extends Command
{
    private const OPTION_REPOSITORY_NAME = 'repository-name';

    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Report
     */
    private $report;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var State
     */
    private $state;

    /**
     * @param Config $config
     * @param Report $report
     * @param Client $client
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        Report $report,
        Client $client,
        State $state,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->report = $report;
        $this->client = $client;
        $this->state = $state;
    }

    protected function configure(): void
    {
        $this->setName('glue:backoffice:report')
            ->setDescription('Reports this install to the Glue Dashboard')
            ->addOption(
                self::OPTION_REPOSITORY_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The Bitbucket repository this install deploys from'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Print the report as JSON instead of sending it; needs no token'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_DRY_RUN)) {
            $repositoryName = $input->getOption(self::OPTION_REPOSITORY_NAME);
            $payload = $this->report->build($repositoryName === null ? null : (string) $repositoryName);

            $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Cli::RETURN_SUCCESS;
        }

        $token = $this->config->getToken();

        if ($token === '') {
            $output->writeln('No Glue Dashboard token configured, not reporting.');

            return Cli::RETURN_SUCCESS;
        }

        if ($this->state->getMode() === State::MODE_DEVELOPER) {
            $output->writeln('Running in developer mode, not reporting.');

            return Cli::RETURN_SUCCESS;
        }

        $repositoryName = $input->getOption(self::OPTION_REPOSITORY_NAME);

        try {
            $payload = $this->report->build($repositoryName === null ? null : (string) $repositoryName);
            [$success, $errors] = $this->client->report($this->config->getUrl(), $token, $payload);
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not report to the Glue Dashboard: ' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        if (! $success) {
            $output->writeln('<error>The Glue Dashboard did not accept the report:</error>');

            foreach ($errors as $error) {
                $output->writeln('<error>    ' . $error . '</error>');
            }

            return Cli::RETURN_FAILURE;
        }

        $output->writeln("<info>Reported Magento {$payload['edition']} '{$payload['title']}' v'{$payload['version']}'</info>");
        $output->writeln("    PHP v'{$payload['phpVersion']}'");
        $output->writeln('<info>Store views</info>');

        foreach ($payload['storeViews'] as $storeView) {
            $output->writeln("    Store view '{$storeView['name']}' ({$storeView['code']}, {$storeView['locale']})");
        }

        $output->writeln('<info>Packages</info>');

        foreach ($payload['packages'] as $package) {
            $output->writeln("    Package '{$package['name']}' v'{$package['version']}'");
        }

        $output->writeln('<info>Repository</info>');
        $output->writeln("    Name '" . ($payload['repository']['name'] ?? '') . "'");

        return Cli::RETURN_SUCCESS;
    }
}
