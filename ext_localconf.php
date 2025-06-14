<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Gmbit\NewsletterSubscription\Controller\SubscriptionController;

ExtensionUtility::configurePlugin(
    'NewsletterSubscription',
    'Subscription',
    [
        SubscriptionController::class => 'index, confirm, unsubscribe, subscribeForm',
    ],
    [
        SubscriptionController::class => 'confirm, unsubscribe', // cached actions
    ]
);

// Register eID for AJAX subscription with email confirmation
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_ajax'] = 
    \Gmbit\NewsletterSubscription\Controller\AjaxController::class . '::processRequest';

// Register eID for checking subscription status
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_check_status'] = 
    \Gmbit\NewsletterSubscription\Controller\AjaxController::class . '::checkStatus';

// Register eID for email confirmation (no cHash needed)
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_confirm'] = 
    \Gmbit\NewsletterSubscription\Handler\EmailConfirmationHandler::class . '::processConfirmation';

// Register eID for email unsubscribe (no cHash needed)
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['newsletter_unsubscribe_link'] = 
    \Gmbit\NewsletterSubscription\Handler\EmailConfirmationHandler::class . '::processUnsubscribe';