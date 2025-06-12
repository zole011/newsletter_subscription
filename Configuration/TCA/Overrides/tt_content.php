<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'NewsletterSubscription',
    'Subscription',
    'Newsletter Subscription'
);