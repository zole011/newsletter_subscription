<?php
return [
    'ctrl' => [
        'title' => 'Newsletter Subscription',
        'label' => 'email',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'email',
        'iconfile' => 'EXT:newsletter_subscription/Resources/Public/Icons/subscription.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'email, unsubscribe_token, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'invertStateDisplay' => true
                    ]
                ],
            ],
        ],
        'email' => [
            'exclude' => false,
            'label' => 'Email',
            'config' => [
                'type' => 'input',
                'eval' => 'email,required',
                'max' => 255,
            ],
        ],
        'unsubscribe_token' => [
            'exclude' => true,
            'label' => 'Unsubscribe Token',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'max' => 255,
                'readOnly' => true,
            ],
        ],
    ],
];