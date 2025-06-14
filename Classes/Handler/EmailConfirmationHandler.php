<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Gmbit\NewsletterSubscription\Service\EmailService;

class EmailConfirmationHandler
{
    public static function processConfirmation(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        
        error_log("EmailConfirmationHandler: processConfirmation called with token: {$token}");
        
        if (empty($token)) {
            return self::createHtmlResponse("❌ Greška", "Nevaljan token za potvrdu.");
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Find subscription by confirmation token
            $queryBuilder = $connection->createQueryBuilder();
            $subscription = $queryBuilder
                ->select('uid', 'email', 'confirmed', 'hidden')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('confirmation_token', $queryBuilder->createNamedParameter($token, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!$subscription) {
                return self::createHtmlResponse("❌ Greška", "Nevaljan ili istekao token za potvrdu.");
            }

            if ($subscription['confirmed'] == 1) {
                return self::createHtmlResponse("ℹ️ Informacija", "Vaša email adresa je već potvrđena.");
            }

            // Confirm subscription
            $unsubscribeToken = hash('sha256', $subscription['email'] . 'unsubscribe' . time() . uniqid());
            
            $updateResult = $connection->update(
                'tx_newslettersubscription_domain_model_subscription',
                [
                    'confirmed' => 1,
                    'hidden' => 0,
                    'unsubscribe_token' => $unsubscribeToken,
                    'tstamp' => time(),
                ],
                ['uid' => $subscription['uid']]
            );

            error_log("EmailConfirmationHandler: Database update result: " . $updateResult);

            // Send welcome email
            try {
                $emailService = GeneralUtility::makeInstance(EmailService::class);
                $emailService->sendWelcomeEmail($subscription['email']);
                error_log("EmailConfirmationHandler: Welcome email sent to: " . $subscription['email']);
            } catch (\Exception $emailError) {
                error_log("EmailConfirmationHandler: Welcome email failed: " . $emailError->getMessage());
                // Continue anyway, confirmation is more important than welcome email
            }

            return self::createHtmlResponse(
                "🎉 Uspešno!", 
                "Hvala vam! Vaša email adresa <strong>{$subscription['email']}</strong> je uspešno potvrđena.<br><br>Dobrodošli u naš newsletter! Poslali smo vam email sa dobrodošlicom."
            );

        } catch (\Exception $e) {
            error_log("EmailConfirmationHandler: Exception in processConfirmation: " . $e->getMessage());
            return self::createHtmlResponse("❌ Greška", "Došlo je do greške pri potvrdi: " . $e->getMessage());
        }
    }

    public static function processUnsubscribe(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        
        error_log("EmailConfirmationHandler: processUnsubscribe called with token: {$token}");
        
        if (empty($token)) {
            return self::createHtmlResponse("❌ Greška", "Nevaljan token za odjavu.");
        }

        try {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('tx_newslettersubscription_domain_model_subscription');
            
            // Find subscription by unsubscribe token
            $queryBuilder = $connection->createQueryBuilder();
            $subscription = $queryBuilder
                ->select('uid', 'email', 'hidden')
                ->from('tx_newslettersubscription_domain_model_subscription')
                ->where(
                    $queryBuilder->expr()->eq('unsubscribe_token', $queryBuilder->createNamedParameter($token, \Doctrine\DBAL\ParameterType::STRING)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!$subscription) {
                return self::createHtmlResponse("❌ Greška", "Nevaljan ili istekao token za odjavu.");
            }

            if ($subscription['hidden'] == 1) {
                return self::createHtmlResponse("ℹ️ Informacija", "Već ste odjavljeni sa newsletter-a.");
            }

            // Unsubscribe
            $updateResult = $connection->update(
                'tx_newslettersubscription_domain_model_subscription',
                [
                    'hidden' => 1,
                    'tstamp' => time(),
                ],
                ['uid' => $subscription['uid']]
            );

            error_log("EmailConfirmationHandler: Unsubscribe update result: " . $updateResult);

            return self::createHtmlResponse(
                "✅ Odjava završena", 
                "Uspešno ste se odjavili sa newsletter-a.<br><br>Žao nam je što odlazite! Ako promenite mišljenje, uvek se možete ponovo registrovati."
            );

        } catch (\Exception $e) {
            error_log("EmailConfirmationHandler: Exception in processUnsubscribe: " . $e->getMessage());
            return self::createHtmlResponse("❌ Greška", "Došlo je do greške pri odjavi: " . $e->getMessage());
        }
    }

    protected static function createHtmlResponse(string $title, string $message): ResponseInterface
    {
        // Try to get the referrer URL for home link, fallback to root
        $homeUrl = '/';
        
        // Try to get a better home URL from referrer
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
            // If referrer is not a confirmation page, use its base
            if (strpos($referrer, 'eID=newsletter_confirm') === false && 
                strpos($referrer, 'eID=newsletter_unsubscribe') === false) {
                $parsedUrl = parse_url($referrer);
                $homeUrl = ($parsedUrl['scheme'] ?? 'http') . '://' . ($parsedUrl['host'] ?? 'localhost');
                if (!empty($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
                    // Get the directory of the path
                    $pathDir = dirname($parsedUrl['path']);
                    if ($pathDir !== '.' && $pathDir !== '/') {
                        $homeUrl .= $pathDir;
                    }
                }
            }
        }
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$title}</title>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .container {
                    background: white;
                    max-width: 600px;
                    padding: 50px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    text-align: center;
                    line-height: 1.6;
                }
                h2 {
                    margin: 0 0 25px 0;
                    color: #333;
                    font-size: 28px;
                }
                p {
                    margin: 0 0 30px 0;
                    color: #666;
                    font-size: 18px;
                }
                .back-link {
                    display: inline-block;
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 600;
                    padding: 12px 24px;
                    border: 2px solid #667eea;
                    border-radius: 25px;
                    transition: all 0.3s ease;
                    margin-right: 15px;
                }
                .back-link:hover {
                    background: #667eea;
                    color: white;
                    transform: translateY(-2px);
                }
                .home-link {
                    display: inline-block;
                    color: #28a745;
                    text-decoration: none;
                    font-weight: 600;
                    padding: 12px 24px;
                    border: 2px solid #28a745;
                    border-radius: 25px;
                    transition: all 0.3s ease;
                }
                .home-link:hover {
                    background: #28a745;
                    color: white;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>{$title}</h2>
                <p>{$message}</p>
                <div>
                    <a href='javascript:history.back()' class='back-link'>← Nazad</a>
                    <a href='{$homeUrl}' class='home-link'>🏠 Početna</a>
                </div>
            </div>
        </body>
        </html>";
        
        return new HtmlResponse($html);
    }
}