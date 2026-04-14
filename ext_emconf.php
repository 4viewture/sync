<?php

$EM_CONF['sync'] = array(
    'title' => 'Sync stuff from several datasources, successor of newssync',
    'description' => 'Syncs data based on providers',
    'category' => 'plugin',
    'author' => 'Kay Strobach',
    'author_email' => 'typo3@kay-strobach.de',
    'state' => 'alpha',
    'version' => '10.5.0',
    'constraints' => array(
        'depends' => array(
            'typo3' => '12.4.0-13.4.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
    'autoload' => array(
        'psr-4' => array(
            'Fourviewture\\Sync\\' => 'Classes/'
        )
    )
);
