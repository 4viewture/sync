<?php

namespace FourViewture\Sync\Command;

use FourViewture\Sync\Domain\Repository\SyncConfigurationRepository;
use FourViewture\Sync\Services\ImportService;
use FourViewture\Sync\Domain\Model\SyncConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sync:all',
    description: 'command to sync all sync sources'
)]
class SyncAllCommandController extends Command
{
    /**
     * syncConfigurationRepository
     *
     * @var SyncConfigurationRepository
     */
    protected $syncConfigurationRepository = null;

    /**
     * @var ImportService
     */
    protected $importService;

    public function injectSyncConfigurationRepository(SyncConfigurationRepository $syncConfigurationRepository): void
    {
        $this->syncConfigurationRepository = $syncConfigurationRepository;
    }

    public function injectImportService(ImportService $importService): void
    {
        $this->importService = $importService;
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Sync all newssync entries.');
    }

    /**
     *
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $syncConfigurations = $this->syncConfigurationRepository->findAll();
        /** @var SyncConfiguration $syncConfiguration */
        foreach ($syncConfigurations as $syncConfiguration) {
            $output->writeln('--------------------------------------------------------------------------------');
            $output->writeln('Starting: ' . $syncConfiguration->getTitle());
            $this->importService->import($syncConfiguration);
            $this->syncConfigurationRepository->update($syncConfiguration);
            $output->writeln($syncConfiguration->getLastsynclog());
        }

        return 0;
    }
}
