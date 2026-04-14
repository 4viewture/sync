<?php

namespace FourViewture\Sync\Services;

use FourViewture\Sync\Domain\Model\SyncConfiguration;
use FourViewture\Sync\Services\Exception\SyncException;
use FourViewture\Sync\Services\Provider\AbstractImportService;
use FourViewture\Sync\Services\Provider\ImportProviderInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Class ImportService
 * @package Fourviewture\Newssync\Services
 * @singleton
 */
class ImportService
{
    /**
     * @var ObjectManager
     */
    protected $objectManager = null;

    /**
     * @var array
     */
    protected array $services = array();



    public function __construct($injectedServices = [])
    {
        foreach ($injectedServices as $service) {
            if (!$service instanceof ImportProviderInterface) {
                continue;
            }
            $this->services[] = $service;
        }
    }

    public function import(SyncConfiguration &$syncConfiguration): void
    {
        if (count($this->services) === 0) {
            $syncConfiguration->setLastsynclog('No import providers defined (' . count($this->services) . ')');
        }

        $syncConfiguration->setLastsync(new \DateTime('now'));
        /** @var ImportProviderInterface $service */
        $handled = false;
        try {
            $service = $this->getMatchingService($syncConfiguration);
            $service->handle($syncConfiguration);
            $syncConfiguration->setLastsynclog($service->getLog());
        } catch (SyncException $e) {
            $syncConfiguration->setLastsynclog($e->getMessage());
        }
    }

    public function getPossibleServices(): array
    {
        $possibleServices = [];
        foreach ($this->services as $service) {
            $possibleServices[] = get_class($service);
        }
        return $possibleServices;
    }

    protected function getMatchingService(SyncConfiguration $syncConfiguration): AbstractImportService
    {
        if ($syncConfiguration->getProvider() !== 'auto') {
            foreach ($this->services as $service) {
                if (get_class($service) === $syncConfiguration->getProvider()) {
                    if ($service->canHandle($syncConfiguration)) {
                        return $service;
                    }
                    throw new SyncException('Provider ' . get_class($service) .  'can not handle this configuration, but is selected as provider, you might switch to auto?');
                }
            }

            throw new SyncException(
                'No Matching provider found, having: ' . json_encode($this->getPossibleServices(), JSON_THROW_ON_ERROR)
            );
        }

        foreach ($this->services as $service) {
            if ($service->canHandle($syncConfiguration)) {
                return $service;
            }
        }

        throw new SyncException(
            'No Matching provider found, having: ' . json_encode($this->getPossibleServices(), JSON_THROW_ON_ERROR)
        );
    }
}
