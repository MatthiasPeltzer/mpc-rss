<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'MPC RSS',
    'description' => 'Fetch and render RSS feeds grouped by category',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'Matthias Peltzer',
    'author_email' => 'mail@mpeltzer.de',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.9.99',
            'fluid' => '13.4.0-13.9.99',
            'extbase' => '13.4.0-13.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];


