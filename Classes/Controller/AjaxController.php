<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Gmbit\NewsletterSubscription\Service\EmailService;

class AjaxController
{
    protected EmailService $emailService;

    public function __construct()
    {
        $this->emailService = GeneralUtility::makeInstance(EmailService::class);
    }

    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $email = $request->getParsedBody()['email'] ?? $request->getQueryParams()['email'] ?? '';
        $action = $request->getParsedBody()['action'] ?? $request->getQueryParams()['action'] ?? 'toggle';
        
        // Debug log
        error_log("Newsletter AJAX Request - Email: {$email}, Action: {$action}");
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Molimo unesite validnu email adresu.',
                'action' => 'error'
            ]);
        }

        if ($action === 'unsubscribe') {
            return $this->processUnsubscribeRequest($email);
        }

        return $this->processSubscribeRequest($email);
    }

    protected function processSubscribeRequest(string $email): ResponseInterface
    {
        try {
            error_log("Processing subscribe request for: {$email}");
            
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Check if email exists
            $queryBuilder = $connection->createQueryBuilder();
            $existingRecord = $queryBuilder
                ->select('uid', 'hidden', 'confirmed', 'confirmation_token')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            error_log("Existing record check: " . ($existingRecord ? "Found UID: " . $existingRecord['uid'] : "Not found"));

            if ($existingRecord) {
                if ($existingRecord['confirmed'] == 1 && $existingRecord['hidden'] == 0) {
                    // Already confirmed and active
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Ova email adresa je već registrovana i potvrđena.',
                        'action' => 'already_subscribed'
                    ]);
                } elseif ($existingRecord['confirmed'] == 0) {
                    // Pending confirmation - resend email
                    $pageId = $GLOBALS['TSFE']->id ?? 1;
                    error_log("Resending confirmation email for UID: " . $existingRecord['uid']);
                    
                    try {
                        $emailSent = $this->emailService->sendConfirmationEmail(
                            $email, 
                            $existingRecord['confirmation_token'], 
                            $pageId
                        );
                        
                        error_log("Email send result: " . ($emailSent ? "SUCCESS" : "FAILED"));
                        
                        if ($emailSent) {
                            return new JsonResponse([
                                'success' => true,
                                'message' => 'Email za potvrdu je ponovo poslat. Proverite vaš inbox.',
                                'action' => 'confirmation_resent'
                            ]);
                        } else {
                            return new JsonResponse([
                                'success' => false,
                                'message' => 'Greška pri slanju email-a. Pokušajte ponovo.',
                                'action' => 'email_error'
                            ]);
                        }
                    } catch (\Exception $emailException) {
                        error_log("Email sending exception: " . $emailException->getMessage());
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Greška pri slanju email-a: ' . $emailException->getMessage(),
                            'action' => 'email_error'
                        ]);
                    }
                } else {
                    // Unsubscribed - reactivate but require confirmation
                    $confirmationToken = hash('sha256', $email . 'confirm' . time() . uniqid());
                    
                    $connection->update(
                        'tx_newslettersubscription_domain_model_subscription',
                        [
                            'hidden' => 0,
                            'confirmed' => 0,
                            'confirmation_token' => $confirmationToken,
                            'tstamp' => time(),
                        ],
                        ['uid' => $existingRecord['uid']]
                    );
                    
                    $pageId = $GLOBALS['TSFE']->id ?? 1;
                    $emailSent = $this->emailService->sendConfirmationEmail($email, $confirmationToken, $pageId);
                    
                    if ($emailSent) {
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Email za potvrdu je poslat. Proverite vaš inbox i kliknite na link za potvrdu.',
                            'action' => 'confirmation_sent'
                        ]);
                    } else {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Greška pri slanju email-a. Pokušajte ponovo.',
                            'action' => 'email_error'
                        ]);
                    }
                }
            } else {
                // New subscription
                error_log("Creating new subscription for: {$email}");
                
                $confirmationToken = hash('sha256', $email . 'confirm' . time() . uniqid());
                $unsubscribeToken = hash('sha256', $email . 'unsubscribe' . time() . uniqid());
                
                $insertResult = $connection->insert(
                    'tx_newslettersubscription_domain_model_subscription',
                    [
                        'pid' => 0,
                        'tstamp' => time(),
                        'crdate' => time(),
                        'email' => $email,
                        'confirmation_token' => $confirmationToken,
                        'unsubscribe_token' => $unsubscribeToken,
                        'confirmed' => 0,
                        'hidden' => 0,
                        'deleted' => 0,
                    ]
                );
                
                error_log("Database insert result: " . ($insertResult ? "SUCCESS" : "FAILED"));
                
                if (!$insertResult) {
                    error_log("Database insert failed for email: {$email}");
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Greška pri upisu u bazu podataka.',
                        'action' => 'database_error'
                    ]);
                }
                
                $pageId = $GLOBALS['TSFE']->id ?? 1;
                $baseUrl = $this->getCurrentBaseUrl();
                error_log("Attempting to send confirmation email, pageId: {$pageId}, baseUrl: {$baseUrl}");
                
                try {
                    $emailSent = $this->emailService->sendConfirmationEmail($email, $confirmationToken, $pageId);
                    error_log("New subscription email send result: " . ($emailSent ? "SUCCESS" : "FAILED"));
                    
                    if ($emailSent) {
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Email za potvrdu je poslat. Proverite vaš inbox i kliknite na link za potvrdu.',
                            'action' => 'confirmation_sent'
                        ]);
                    } else {
                        // Rollback - delete the record if email failed
                        error_log("Email failed, rolling back database record");
                        $connection->delete(
                            'tx_newslettersubscription_domain_model_subscription',
                            ['email' => $email, 'confirmed' => 0]
                        );
                        
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Greška pri slanju email-a. Pokušajte ponovo.',
                            'action' => 'email_error'
                        ]);
                    }
                } catch (\Exception $emailException) {
                    error_log("Email exception for new subscription: " . $emailException->getMessage());
                    // Rollback
                    $connection->delete(
                        'tx_newslettersubscription_domain_model_subscription',
                        ['email' => $email, 'confirmed' => 0]
                    );
                    
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Greška pri slanju email-a: ' . $emailException->getMessage(),
                        'action' => 'email_error'
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            error_log("General exception in processSubscribeRequest: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'message' => 'Došlo je do greške: ' . $e->getMessage(),
                'action' => 'error'
            ]);
        }
    }

    protected function processUnsubscribeRequest(string $email): ResponseInterface
    {
        try {
            error_log("Processing unsubscribe request for: {$email}");
            
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Find active confirmed subscription
            $queryBuilder = $connection->createQueryBuilder();
            $subscription = $queryBuilder
                ->select('uid', 'unsubscribe_token')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('confirmed', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            error_log("Unsubscribe record check: " . ($subscription ? "Found UID: " . $subscription['uid'] : "Not found"));

            if (!$subscription) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Email adresa nije pronađena ili nije potvrđena.',
                    'action' => 'not_found'
                ]);
            }

            $pageId = $GLOBALS['TSFE']->id ?? 1;
            $baseUrl = $this->getCurrentBaseUrl();
            error_log("Attempting to send unsubscribe email, pageId: {$pageId}, baseUrl: {$baseUrl}");
            
            try {
                $emailSent = $this->emailService->sendUnsubscribeEmail(
                    $email, 
                    $subscription['unsubscribe_token'], 
                    $pageId
                );
                
                error_log("Unsubscribe email send result: " . ($emailSent ? "SUCCESS" : "FAILED"));

                if ($emailSent) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Email sa linkom za odjavu je poslat. Proverite vaš inbox.',
                        'action' => 'unsubscribe_email_sent'
                    ]);
                } else {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Greška pri slanju email-a. Pokušajte ponovo.',
                        'action' => 'email_error'
                    ]);
                }
            } catch (\Exception $emailException) {
                error_log("Unsubscribe email exception: " . $emailException->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Greška pri slanju email-a: ' . $emailException->getMessage(),
                    'action' => 'email_error'
                ]);
            }
            
        } catch (\Exception $e) {
            error_log("General exception in processUnsubscribeRequest: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'message' => 'Došlo je do greške: ' . $e->getMessage(),
                'action' => 'error'
            ]);
        }
    }

    public function checkStatus(ServerRequestInterface $request): ResponseInterface
    {
        $email = $request->getParsedBody()['email'] ?? $request->getQueryParams()['email'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nevalidna email adresa.',
                'status' => 'invalid'
            ]);
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            $queryBuilder = $connection->createQueryBuilder();
            $record = $queryBuilder
                ->select('hidden', 'confirmed')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!$record) {
                return new JsonResponse([
                    'success' => true,
                    'status' => 'not_registered',
                    'message' => 'Email adresa nije registrovana.',
                    'action' => 'subscribe'
                ]);
            }

            if ($record['confirmed'] == 0) {
                return new JsonResponse([
                    'success' => true,
                    'status' => 'pending_confirmation',
                    'message' => 'Email adresa čeka potvrdu.',
                    'action' => 'confirm'
                ]);
            }

            if ($record['hidden'] == 0 && $record['confirmed'] == 1) {
                return new JsonResponse([
                    'success' => true,
                    'status' => 'subscribed',
                    'message' => 'Registrovani ste za newsletter.',
                    'action' => 'unsubscribe'
                ]);
            }

            if ($record['hidden'] == 1) {
                return new JsonResponse([
                    'success' => true,
                    'status' => 'unsubscribed',
                    'message' => 'Odjavljeni ste sa newsletter-a.',
                    'action' => 'subscribe'
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'status' => 'unknown',
                'message' => 'Nepoznat status.',
                'action' => 'subscribe'
            ]);

        } catch (\Exception $e) {
            error_log("General exception in checkStatus: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Greška pri proveri statusa.',
                'status' => 'error'
            ]);
        }
    }

    protected function getCurrentBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get current path without index.php
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '';
        
        // Remove /index.php if present
        if (basename($path) === 'index.php') {
            $path = dirname($path);
        }
        
        if ($path === '/' || $path === '.') {
            $path = '';
        }
        
        $baseUrl = $protocol . $host . $path;
        error_log("Current base URL: {$baseUrl}");
        return $baseUrl;
    }
}