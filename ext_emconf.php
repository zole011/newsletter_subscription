<?php
// ext_emconf.php
$EM_CONF['newsletter_subscription'] = [
    'title' => 'Newsletter Subscription',
    'description' => 'Simple newsletter subscription with AJAX support',
    'category' => 'plugin',
    'author' => 'Your Name',
    'author_email' => 'your@email.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Gmbit\\NewsletterSubscription\\' => 'Classes/',
        ],
    ],
];