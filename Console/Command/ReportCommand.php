<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Console\Command;

use GlueAgency\Backoffice\Model\Client;
use GlueAgency\Backoffice\Model\Config;
use GlueAgency\Backoffice\Model\Report;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports the shop to the Glue Dashboard from every environment it is deployed
 * to: live sends the whole report, any other only where each store view
 * answers. The token decides whether anything is reported, and the deploy
 * names the environment, as it names the repository: Magento's deploy mode is
 * production on staging too, and its config names no environment.
 */
class ReportCommand extends Command
{
    private const OPTION_REPOSITORY_NAME = 'repository-name';

    private const OPTION_DRY_RUN = 'dry-run';

    private const OPTION_ENVIRONMENT = 'environment';

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
     * @param Config $config
     * @param Report $report
     * @param Client $client
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        Report $report,
        Client $client,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->report = $report;
        $this->client = $client;
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
                self::OPTION_ENVIRONMENT,
                null,
                InputOption::VALUE_REQUIRED,
                'The environment this host runs in: production on live, staging, dev or any other name elsewhere. Required'
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
        $repositoryName = $input->getOption(self::OPTION_REPOSITORY_NAME);
        $repositoryName = $repositoryName === null ? null : (string) $repositoryName;
        $environment = $input->getOption(self::OPTION_ENVIRONMENT);
        $environment = Report::environment($environment === null ? null : (string) $environment);

        if ($environment === '') {
            $output->writeln('<error>No environment given: pass --environment=production on live, or this host\'s environment (staging, dev, …) anywhere else. Not reporting.</error>');

            return Cli::RETURN_FAILURE;
        }

        if ($input->getOption(self::OPTION_DRY_RUN)) {
            $payload = $this->report->build($repositoryName, $environment);

            $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Cli::RETURN_SUCCESS;
        }

        $token = $this->config->getToken();

        if ($token === '') {
            $output->writeln("Running in the {$environment} environment, no Glue Dashboard token configured, not reporting.");

            return Cli::RETURN_SUCCESS;
        }

        try {
            $payload = $this->report->build($repositoryName, $environment);
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

        $this->reported($output, $payload);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Every value the report sent, so the deploy log shows what the Dashboard
     * was told, from live or not.
     *
     * @param OutputInterface $output
     * @param array $payload
     */
    private function reported(OutputInterface $output, array $payload): void
    {
        if (isset($payload['version'])) {
            $output->writeln("<info>Reported Magento {$payload['edition']} '{$payload['title']}' v'{$payload['version']}' from the {$payload['environment']} environment</info>");
            $output->writeln("    PHP v'{$payload['phpVersion']}'");
        } else {
            $output->writeln("<info>Reported Magento '{$payload['title']}' from the {$payload['environment']} environment</info>");
        }

        $output->writeln('<info>Store views</info>');

        foreach ($payload['storeViews'] as $storeView) {
            $output->writeln("    Store view '{$storeView['name']}' ({$storeView['code']}, {$storeView['locale']}) '{$storeView['url']}'");
        }

        if (isset($payload['packages'])) {
            $output->writeln('<info>Packages</info>');

            foreach ($payload['packages'] as $package) {
                $output->writeln("    Package '{$package['name']}' v'{$package['version']}'");
            }
        }

        $output->writeln('<info>Repository</info>');
        $output->writeln("    Name '" . ($payload['repository']['name'] ?? '') . "'");
    }
}
