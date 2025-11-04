<?php

defined('TYPO3') or die();

$_EXTKEY = 'mpc_rss';

$EM_CONF['mpc_rss'] = array(
    'title' => 'MPC RSS',
    'description' => 'TYPO3 extension to fetch and render RSS feeds grouped by category, date or author.',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'Matthias Peltzer',
    'author_email' => 'mail@mpeltzer.de',
    'version' => '1.0.2',
    'constraints' => array(
        'depends' => array(
            'typo3' => '13.4.0-14.99.99',
            'fluid' => '13.4.0-14.99.99',
            'extbase' => '13.4.0-14.99.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
);


