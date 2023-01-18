<?php

return [
    'frontend' => [
        'sourcebroker/configs/uncache' => [
            'target' => \SourceBroker\Configs\Middleware\Uncache::class,
            'before' => [
                'typo3/cms-frontend/timetracker',
            ],
        ],
    ],
];
