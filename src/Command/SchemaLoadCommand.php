<?php

namespace Radvance\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use RuntimeException;

class SchemaLoadCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('schema:load')
            ->setDescription('Uses dbtk-schema-loader to update db schema')
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Apply allow you to synchronise schema'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $apply = $input->getOption('apply');

        /*
         *  First Priority .env variable.
         *  If not found load find and load paramters.yml.
         */

        if (getenv('PDO')) {
            $pdo = getenv('PDO');
        } else {
            $filename = 'app/config/parameters.yml';
            if (!file_exists($filename)) {
                throw new RuntimeException('No such file: '.$filename);
            }
            $data = file_get_contents($filename);
            $config = Yaml::parse($data);

            if (isset($config['pdo'])) {
                $pdo = $config['pdo'];
            } else {
                if (!isset($config['parameters']['pdo'])) {
                    throw new RuntimeException("Can't find pdo configuration");
                }
                $pdo = $config['parameters']['pdo'];
            }
        }

        $cmdPrefix = 'vendor/bin/dbtk-schema-loader schema:load ';

        // module schema
        $application = require 'app/bootstrap.php'; // bit tricky way to get the app
        $modules = $application['module-manager']->getModules();
        foreach ($modules as $module) {
            $schemaPath = $module->getPath().'/../res/schema.xml';
            if (file_exists($schemaPath)) {
                $this->executeLoadCmd(
                    $this->getLoadCommand($schemaPath, $pdo),
                    $apply,
                    $output
                );
            }
        }

        $schemaFileDirectoryArray = ['app/', 'schema/', './',  'config/'];

        $schemaFilename = '';
        foreach ($schemaFileDirectoryArray as $schemaDirectory) {
            if (file_exists($schemaDirectory.'schema.xml')) {
                $schemaFilename = $schemaDirectory.'schema.xml';
                break;
            }
        }

        // main schema
        $cmd = $cmdPrefix.$schemaFilename.' '.$pdo;
        $this->executeLoadCmd(
            $this->getLoadCommand($schemaFilename, $pdo),
            $apply,
            $output
        );
    }

    private function getLoadCommand($schemaPath, $pdo)
    {
        return 'vendor/bin/dbtk-schema-loader schema:load '.$schemaPath.' '.$pdo;
    }

    private function executeLoadCmd($cmd, $apply, $output)
    {
        if ($apply) {
            $cmd .= ' --apply';
        }
        $process = new Process($cmd);
        $output->writeln($process->getCommandLine());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $output->writeln('<comment>'.$process->getOutput().'</>');
    }
}
