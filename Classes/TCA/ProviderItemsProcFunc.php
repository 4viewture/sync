<?php

declare(strict_types=1);

namespace FourViewture\Sync\TCA;

use FourViewture\Sync\Domain\Model\SyncConfiguration;
use FourViewture\Sync\Services\ImportService;
use FourViewture\Sync\Services\Provider\ImportProviderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProviderItemsProcFunc
{
    /**
     * @param array $params
     * @param array $config
     * @param array $TSconfig
     * @param string $table
     * @param array $row
     * @return void
     */
    public static function itemsProcFunc(array &$params): void
    {

        $importService = GeneralUtility::makeInstance(ImportService::class);

        $config = new SyncConfiguration();
        $config->setUri($params['row']['uri'] ?? '');

        $entries = $importService->getPossibleServices();

        $collectedEntries = [];

        foreach ($entries as $entry) {
            $label = $entry;
            $canHandle = false;
            // check if the class can provide a translation label with interface

            try {
                /** @var ImportProviderInterface $obj */
                $obj = GeneralUtility::makeInstance($entry);
                $label = $obj->getLabelForTca();

                $canHandle = $obj->canHandle($config) ?? false;
            } catch (\Exception $e) {
                $label .= ' - ❌️ ' . $e->getMessage();
                $canHandle = false;
            }

            if (!$canHandle) {
                $label = '⚠ ' . $label;
            }

            $collectedEntries[$obj->getGroupForTca()][] = [
                'label' => $label,
                'value' => $entry,
                'icon' => $canHandle ? 'status-dialog-ok' : 'status-dialog-error',
            ];
        }

        foreach ($collectedEntries as $group => $items) {
            $params['items'][] = [
                'label' => $group,
                'value' => '--div--',
            ];
            foreach ($items as $item) {
                $params['items'][] = $item;
            }
        }
    }
}
