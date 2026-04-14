<?php

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['newssync']
    = \FourViewture\Sync\Hooks\T3libTcemainHook::class . '->clearCachePostProc';
