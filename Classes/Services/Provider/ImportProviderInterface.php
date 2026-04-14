<?php
declare(strict_types=1);

namespace FourViewture\Sync\Services\Provider;

use FourViewture\Sync\Domain\Model\SyncConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

interface ImportProviderInterface
{
    public function __construct(
        PersistenceManager $persistenceManager,
        ?ConnectionPool $connectionPool = null,
        ?StorageRepository $storageRepository = null
    );

    public function canHandle(SyncConfiguration $syncConfiguration): bool;

    public function handle(SyncConfiguration $syncConfiguration): void;

    public function getLog(): string;

    public function getLabelForTca(): string;
}
