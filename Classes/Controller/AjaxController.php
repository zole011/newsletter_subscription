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
            
            // Check if email already exists
            // Check if email already exists
            $queryBuilder = $connection->createQueryBuilder();
            $existingRecord = $queryBuilder
                ->select('uid')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            
            if ($existingRecord) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Ova email adresa je već registrovana.'
                ]);
            }

            // Insert new subscription
            $connection->insert(
                'tx_newslettersubscription_domain_model_subscription',
                [
                    'pid' => 0,
                    'tstamp' => time(),
                    'crdate' => time(),
                    'email' => $email,
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
}