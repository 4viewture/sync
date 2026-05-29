<?php

namespace FourViewture\Sync\Services\Provider;

use FourViewture\Sync\Domain\Model\SyncConfiguration;
use FourViewture\Sync\Services\DocBlockService;
use GeorgRinger\News\Domain\Model\FileReference;
use GeorgRinger\News\Domain\Model\Link;
use GeorgRinger\News\Domain\Model\News;
use GeorgRinger\News\Domain\Model\NewsDefault;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use ReflectionClass;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\Index\FileIndexRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Service\CacheService;

abstract class AbstractImportService implements ImportProviderInterface
{
    public const IMPORT_ID = 'newssync_unknown';
    public const PRIORITY = 0;

    /**
     * @var array
     */
    protected $output = array();

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var mixed
     */
    protected $emConfiguration = null;

    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    /**
     * @var StorageRepository
     */
    protected $storageRepository;

    protected DocBlockService $docBlockService;

    public function __construct(
        PersistenceManager $persistenceManager,
        ?ConnectionPool $connectionPool = null,
        ?StorageRepository $storageRepository = null
    )
    {
        $this->persistenceManager = $persistenceManager;

        $this->connectionPool = $connectionPool;
        if ($this->connectionPool === null) {
            $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        }

        $this->storageRepository = $storageRepository;
        if ($this->storageRepository === null) {
            $this->storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        }

        /** @var ExtensionConfiguration $configurationUtility */
        $configurationUtility = GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    public function injectDocBlockService(DocBlockService $docBlockService): void
    {
        $this->docBlockService = $docBlockService;
    }


    protected function getLLLString(): string
    {
        return 'LLL:EXT:newssync/Resources/Private/Language/locallang_db.xlf:tx_newssync_domain_model_syncconfiguration.provider.' . str_replace('\\', '_', get_class($this));
    }

    /**
     * We do the translation here, so we can append stuff in the itemProc.
     * @return string
     */
    public function getLabelForTca(): string
    {
        /** @var LanguageService $languageService */
        $languageService = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
        $translation = $languageService->sL($this->getLLLString());
        if ($translation === '') {
            return get_class($this);
        }
        return $translation;
    }

    /**
     * @param SyncConfiguration $syncConfiguration
     * @return boolean
     */
    public function canHandle(SyncConfiguration $syncConfiguration): bool
    {
        return false;
    }
    /**
     * @param SyncConfiguration $syncConfiguration
     */
    public function handle(SyncConfiguration $syncConfiguration): void
    {
        $this->output = array();
        $this->log('Importing with ' . get_class($this));
    }
    /**
     * @param $message
     */
    public function log($message)
    {
        $this->output[] = $message;
    }
    /**
     * @return string
     */
    public function getLog(): string
    {
        return implode(chr(10), $this->output);
    }

    protected function getCacheDir(?SyncConfiguration $syncConfiguration = null): string
    {
        $subFolder = 'default';
        if ($syncConfiguration !== null) {
            $subFolder = $syncConfiguration->getUid();
        }

        $cacheDir = Environment::getVarPath() . '/cache/sync/' . $subFolder . '/';
        GeneralUtility::mkdir_deep($cacheDir);

        return $cacheDir;
    }

    /**
     * @param int $uid of storage folder
     */
    protected function clearCache($uid)
    {
        $pagets = BackendUtility::getPagesTSconfig($uid);
        if (isset($pagets['TCEMAIN.']['clearCacheCmd'])) {
            $this->log('    found TCEMAIN.clearCacheCmd=' . $pagets['TCEMAIN.']['clearCacheCmd']);
            /** @var CacheService $cacheService */
            $cacheService = $this->getObjectManager()->get(CacheService::class);
            $cacheService->clearPageCache(GeneralUtility::trimExplode(',', $pagets['TCEMAIN.']['clearCacheCmd']));
        } else {
            $this->log('    found no TCEMAIN.clearCacheCmd, so cache is not cleared');
        }
    }
    /**
     * @return ObjectManager
     */
    protected function getObjectManager()
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }

    final public function getPriority(): int
    {
        return static::PRIORITY;
    }

    protected function getFileByContent(SyncConfiguration $syncConfiguration, string $uri, ?string $forcedFileName = null)
    {
        $tmpFileName = GeneralUtility::tempnam(static::IMPORT_ID);
        file_put_contents($tmpFileName, GeneralUtility::getUrl($uri));

        // first try to find a file
        $fileIndexRepository = GeneralUtility::makeInstance(FileIndexRepository::class);
        $files = $fileIndexRepository->findByContentHash(sha1(file_get_contents($tmpFileName)));
        if (count($files)) {
            foreach ($files as $fileInfo) {
                if ($fileInfo['storage'] > 0) {
                    if (file_exists($tmpFileName)) {
                        unlink($tmpFileName);
                    }
                    $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObjectByStorageAndIdentifier(
                        $fileInfo['storage'],
                        $fileInfo['identifier']
                    );
                    $this->log('      found file:  ' . $file->getCombinedIdentifier());
                    return $file;
                }
            }
        }

        // not found, create one!
        $filename = $forcedFileName ?? basename(parse_url($uri, PHP_URL_PATH));
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $storage = $resourceFactory->getDefaultStorage();
        $folder = $storage->getDefaultFolder();
        if ($syncConfiguration->getProcessingfolder() !== '') {
            $storage = $this->storageRepository->findByCombinedIdentifier($syncConfiguration->getProcessingfolder());
            $folder = $resourceFactory->getObjectFromCombinedIdentifier($syncConfiguration->getProcessingfolder());
            if (!$folder instanceof Folder) {
                $this->log('      Problem using ' . $syncConfiguration->getProcessingfolder() . ' as storage folder');
                return null;
            }
        }

        if ($tmpFileName === null) {
            throw new \Exception('No tmp file found for: ' . $uri);
        }
        if (!is_file($tmpFileName) || !is_readable($tmpFileName)) {
            throw new \Exception('Tmp file not readable for: ' . $uri);
        }

        $targetFileName = 'newssync-' . hash('crc32b', $uri) . '-' . $filename;

        $file = $storage->createFile(
            $targetFileName,
            $folder
        );

        if ($file === null) {
            throw new \Exception('Could not create file for: ' . $uri . ' please check the processing folder');
        }

        $file->setContents(
            file_get_contents($tmpFileName)
        );

        if ($file->getExtension() === '') {
            list($type, $extension) = explode('/', $file->getMimeType(), 2);
            $file->rename($filename . '.' . $extension);
        }

        $this->log('      created file:  ' . $file->getCombinedIdentifier());

        if (file_exists($tmpFileName)) {
            unlink($tmpFileName);
        }

        return $file;
    }

    protected function generateSlug(int $uid, string $tableName = 'tx_news_domain_model_news', string $slugFieldName = 'path_segment'): string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $record = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();

        $helper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $tableName,
            $slugFieldName,
            $GLOBALS['TCA'][$tableName]['columns'][$slugFieldName]['config']
        );

        $value = $helper->generate($record, $record['pid']);
        $state = RecordStateFactory::forName($tableName)->fromArray($record, $record['pid'], $record['uid']);

        $value = $helper->buildSlugForUniqueInSite($value, $state);

        $queryBuilder
            ->update($tableName)
            ->where(
                $queryBuilder->expr()->eq('uid', $uid)
            )
            ->set($slugFieldName, $value)
            ->executeQuery();
        return $value;
    }

    public function getGroupForTca(): string
    {
        return 'Default';
    }

    public function getClass(): string
    {
        return static::class;
    }

    public function getDescription(): string
    {
        $docComment = (new ReflectionClass(static::class))->getDocComment();
        return $this->docBlockService->getDescription($docComment);
    }

    public function getDocumentationUrl(): string
    {
        return '';
    }

    public function getExtension(): string
    {
        return 'sync';
    }
}
