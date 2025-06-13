<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Gmbit\NewsletterSubscription\Controller\SubscriptionController;

ExtensionUtility::configurePlugin(
    'NewsletterSubscription',
    'Subscription',
    [
        SubscriptionController::class => 'index',
    ],
    [
        SubscriptionController::class => '',
    ]
);

// Register eID for AJAX toggle subscription
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_ajax'] = 
    \Gmbit\NewsletterSubscription\Controller\AjaxController::class . '::processRequest';

// Register eID for checking subscription status
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_check_status'] = 
    \Gmbit\NewsletterSubscription\Controller\AjaxController::class . '::checkStatus';