<?php

declare(strict_types=1);

namespace FourViewture\Sync\Upgrades;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('fourViewtureSyncMigrateSyncConfigurationWizard')]
class MigrateSyncConfigurationWizard implements UpgradeWizardInterface
{
    private const SOURCE_MAIN_TABLE = 'tx_newssync_domain_model_syncconfiguration';
    private const SOURCE_MM_TABLE = 'tx_newssync_syncconfiguration_clearcachepages_page_mm';
    private const TARGET_MAIN_TABLE = 'tx_sync_domain_model_syncconfiguration';
    private const TARGET_MM_TABLE = 'tx_sync_syncconfiguration_clearcachepages_page_mm';

    public function getTitle(): string
    {
        return 'EXT:sync - Migrate sync configuration from EXT:newssync tables';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Migrates all records from "%s" and "%s" to "%s" and "%s". '
            . 'Will be skipped if the target table already contains records.',
            self::SOURCE_MAIN_TABLE,
            self::SOURCE_MM_TABLE,
            self::TARGET_MAIN_TABLE,
            self::TARGET_MM_TABLE,
        );
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    /**
     * The wizard should run when:
     *  - the target main table has no records yet, AND
     *  - the source main table has records to migrate
     */
    public function updateNecessary(): bool
    {
        if ($this->countRecords(self::TARGET_MAIN_TABLE) > 0) {
            return false;
        }

        return $this->countRecords(self::SOURCE_MAIN_TABLE) > 0;
    }

    public function executeUpdate(): bool
    {
        $this->migrateMainTable();
        $this->migrateMmTable();

        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function migrateMainTable(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $sourceRows = $connectionPool
            ->getConnectionForTable(self::SOURCE_MAIN_TABLE)
            ->select(
                ['*'],
                self::SOURCE_MAIN_TABLE,
                [],
            )
            ->fetchAllAssociative();

        if (empty($sourceRows)) {
            return;
        }

        $targetConnection = $connectionPool->getConnectionForTable(self::TARGET_MAIN_TABLE);

        foreach ($sourceRows as $row) {
            $targetConnection->insert(self::TARGET_MAIN_TABLE, $row);
        }
    }

    private function migrateMmTable(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $sourceRows = $connectionPool
            ->getConnectionForTable(self::SOURCE_MM_TABLE)
            ->select(
                ['*'],
                self::SOURCE_MM_TABLE,
                [],
            )
            ->fetchAllAssociative();

        if (empty($sourceRows)) {
            return;
        }

        $targetConnection = $connectionPool->getConnectionForTable(self::TARGET_MM_TABLE);

        foreach ($sourceRows as $row) {
            $targetConnection->insert(self::TARGET_MM_TABLE, $row);
        }
    }

    private function countRecords(string $table): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        return (int)$connection->count('*', $table, []);
    }
}
