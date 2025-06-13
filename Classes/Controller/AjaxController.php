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
                'message' => 'Molimo unesite validnu email adresu.',
                'action' => 'error'
            ]);
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Check if email exists
            $queryBuilder = $connection->createQueryBuilder();
            $existingRecord = $queryBuilder
                ->select('uid', 'hidden', 'email')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if ($existingRecord) {
                // Toggle subscription status
                if ($existingRecord['hidden'] == 0) {
                    // Currently subscribed - unsubscribe
                    $connection->update(
                        'tx_newslettersubscription_domain_model_subscription',
                        [
                            'hidden' => 1,
                            'tstamp' => time(),
                        ],
                        ['uid' => $existingRecord['uid']]
                    );
                    
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Uspešno ste se odjavili sa newsletter-a.',
                        'action' => 'unsubscribed',
                        'email' => $email
                    ]);
                } else {
                    // Currently unsubscribed - resubscribe
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
                        'message' => 'Uspešno ste se ponovo registrovali za newsletter!',
                        'action' => 'subscribed',
                        'email' => $email
                    ]);
                }
            } else {
                // New subscription
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
                    'message' => 'Uspešno ste se registrovali za newsletter!',
                    'action' => 'subscribed',
                    'email' => $email
                ]);
            }
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Došlo je do greške. Molimo pokušajte ponovo.',
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
                'subscribed' => false
            ]);
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            $queryBuilder = $connection->createQueryBuilder();
            $record = $queryBuilder
                ->select('hidden')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            $isSubscribed = $record && $record['hidden'] == 0;

            return new JsonResponse([
                'success' => true,
                'subscribed' => $isSubscribed,
                'message' => $isSubscribed ? 'Registrovani ste za newsletter.' : 'Niste registrovani za newsletter.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Greška pri proveri statusa.',
                'subscribed' => false
            ]);
        }
    }
}