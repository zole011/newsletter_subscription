<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Gmbit\NewsletterSubscription\Controller\SubscriptionController;

ExtensionUtility::configurePlugin(
    'NewsletterSubscription',
    'Subscription',
    [
        SubscriptionController::class => 'index, subscribe',
    ],
    [
        SubscriptionController::class => 'subscribe',
    ]
);
// Register eID for AJAX requests
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_ajax'] = 
    \Gmbit\NewsletterSubscription\Controller\AjaxController::class . '::processRequest';