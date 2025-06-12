<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AjaxController
{
    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $email = $request->getParsedBody()['email'] ?? $request->getQueryParams()['email'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Molimo unesite validnu email adresu.'
            ]);
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Check if email already exists and is active
            $queryBuilder = $connection->createQueryBuilder();
            $existingRecord = $queryBuilder
                ->select('uid', 'hidden')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if ($existingRecord) {
                if ($existingRecord['hidden'] == 0) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Ova email adresa je već registrovana.'
                    ]);
                } else {
                    // Reactivate hidden subscription
                    $unsubscribeToken = hash('sha256', $email . time() . uniqid());
                    
                    $connection->update(
                        'tx_newslettersubscription_domain_model_subscription',
                        [
                            'hidden' => 0,
                            'tstamp' => time(),
                            'unsubscribe_token' => $unsubscribeToken,
                        ],
                        ['uid' => $existingRecord['uid']]
                    );
                    
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Uspešno ste se ponovo registrovali za newsletter!'
                    ]);
                }
            }

            // Insert new subscription
            $unsubscribeToken = hash('sha256', $email . time() . uniqid());
            
            $connection->insert(
                'tx_newslettersubscription_domain_model_subscription',
                [
                    'pid' => 0,
                    'tstamp' => time(),
                    'crdate' => time(),
                    'email' => $email,
                    'unsubscribe_token' => $unsubscribeToken,
                    'hidden' => 0,
                    'deleted' => 0,
                ]
            );
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Uspešno ste se registrovali za newsletter!'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Došlo je do greške. Molimo pokušajte ponovo.'
            ]);
        }
    }

    public function processUnsubscribeRequest(ServerRequestInterface $request): ResponseInterface
    {
        $email = $request->getParsedBody()['email'] ?? $request->getQueryParams()['email'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Molimo unesite validnu email adresu.'
            ]);
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Find active subscription
            $queryBuilder = $connection->createQueryBuilder();
            $subscription = $queryBuilder
                ->select('uid', 'unsubscribe_token')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!$subscription) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Email adresa nije pronađena u našoj bazi.'
                ]);
            }

            // In a real application, you would send an email with unsubscribe link
            // For demo purposes, we'll return the unsubscribe token
            $unsubscribeUrl = '/index.php?id=' . $GLOBALS['TSFE']->id . 
                             '&tx_newslettersubscription_subscription[action]=confirmUnsubscribe' .
                             '&tx_newslettersubscription_subscription[controller]=Subscription' .
                             '&tx_newslettersubscription_subscription[token]=' . $subscription['unsubscribe_token'];

            return new JsonResponse([
                'success' => true,
                'message' => 'Link za odjavu je generisan.',
                'unsubscribeUrl' => $unsubscribeUrl
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Došlo je do greške. Molimo pokušajte ponovo.'
            ]);
        }
    }
}