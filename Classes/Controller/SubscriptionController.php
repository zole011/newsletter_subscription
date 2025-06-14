<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Gmbit\NewsletterSubscription\Domain\Model\Subscription;
use Gmbit\NewsletterSubscription\Domain\Repository\SubscriptionRepository;
use Gmbit\NewsletterSubscription\Service\EmailService;

class SubscriptionController extends ActionController
{
    protected SubscriptionRepository $subscriptionRepository;
    protected EmailService $emailService;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        EmailService $emailService
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->emailService = $emailService;
    }

    public function indexAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    /**
     * Confirm subscription via email link
     */
    public function confirmAction(): ResponseInterface
    {
        error_log("SubscriptionController: confirmAction called");
        
        $arguments = $this->request->getArguments();
        error_log("SubscriptionController: Arguments received: " . json_encode($arguments));
        
        $token = $arguments['token'] ?? '';
        error_log("SubscriptionController: Token: " . $token);
        
        if (empty($token)) {
            error_log("SubscriptionController: Empty token");
            $this->view->assign('error', 'Nevaljan token za potvrdu.');
            return $this->htmlResponse();
        }

        // Find subscription by confirmation token
        $subscription = $this->subscriptionRepository->findByConfirmationToken($token);
        error_log("SubscriptionController: Subscription found: " . ($subscription ? $subscription->getEmail() : 'null'));
        
        if (!$subscription) {
            error_log("SubscriptionController: No subscription found for token");
            $this->view->assign('error', 'Nevaljan ili istekao token za potvrdu.');
            return $this->htmlResponse();
        }

        if ($subscription->isConfirmed()) {
            error_log("SubscriptionController: Already confirmed");
            $this->view->assign('info', 'Vaša email adresa je već potvrđena.');
            return $this->htmlResponse();
        }

        try {
            error_log("SubscriptionController: Attempting to confirm subscription");
            
            // Confirm subscription
            $subscription->setConfirmed(true);
            $subscription->setHidden(false);
            $subscription->generateUnsubscribeToken(); // Generate new unsubscribe token
            
            $this->subscriptionRepository->update($subscription);
            $this->persistenceManager->persistAll();
            
            error_log("SubscriptionController: Subscription confirmed successfully");
            
            // Send welcome email
            $this->emailService->sendWelcomeEmail($subscription->getEmail());
            
            // Return direct HTML response instead of using template
            $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Potvrda uspešna</title></head>
            <body style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px;'>
                <h2>🎉 Uspešno!</h2>
                <p>Hvala vam! Vaša email adresa <strong>{$subscription->getEmail()}</strong> je uspešno potvrđena.</p>
                <p>Dobrodošli u naš newsletter! Poslali smo vam email sa dobrodošlicom.</p>
                <a href='javascript:history.back()' style='color: #007bff;'>← Nazad</a>
            </body>
            </html>";
            
            return $this->htmlResponse($html);
            
        } catch (\Exception $e) {
            error_log("SubscriptionController: Exception in confirmAction: " . $e->getMessage());
            
            $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Greška</title></head>
            <body style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px;'>
                <h2>❌ Greška</h2>
                <p>Došlo je do greške pri potvrdi: {$e->getMessage()}</p>
                <a href='javascript:history.back()' style='color: #007bff;'>← Nazad</a>
            </body>
            </html>";
            
            return $this->htmlResponse($html);
        }
        
        // Default error cases
        if (empty($token)) {
            $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Nevaljan token</title></head>
            <body style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px;'>
                <h2>❌ Greška</h2>
                <p>Nevaljan token za potvrdu.</p>
                <a href='javascript:history.back()' style='color: #007bff;'>← Nazad</a>
            </body>
            </html>";
            return $this->htmlResponse($html);
        }
        
        if (!$subscription) {
            $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Token nije pronađen</title></head>
            <body style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px;'>
                <h2>❌ Greška</h2>
                <p>Nevaljan ili istekao token za potvrdu.</p>
                <a href='javascript:history.back()' style='color: #007bff;'>← Nazad</a>
            </body>
            </html>";
            return $this->htmlResponse($html);
        }
        
        if ($subscription->isConfirmed()) {
            $html = "
            <!DOCTYPE html>
            <html>
            <head><title>Već potvrđeno</title></head>
            <body style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px;'>
                <h2>ℹ️ Informacija</h2>
                <p>Vaša email adresa je već potvrđena.</p>
                <a href='javascript:history.back()' style='color: #007bff;'>← Nazad</a>
            </body>
            </html>";
            return $this->htmlResponse($html);
        }
    }

    /**
     * Unsubscribe via email link
     */
    public function unsubscribeAction(): ResponseInterface
    {
        error_log("SubscriptionController: unsubscribeAction called");
        
        $arguments = $this->request->getArguments();
        error_log("SubscriptionController: Unsubscribe arguments: " . json_encode($arguments));
        
        $token = $arguments['token'] ?? '';
        error_log("SubscriptionController: Unsubscribe token: " . $token);
        
        if (empty($token)) {
            $this->view->assign('error', 'Nevaljan token za odjavu.');
            return $this->htmlResponse();
        }

        // Find subscription by unsubscribe token
        $subscription = $this->subscriptionRepository->findByUnsubscribeToken($token);
        error_log("SubscriptionController: Unsubscribe subscription found: " . ($subscription ? $subscription->getEmail() : 'null'));
        
        if (!$subscription) {
            $this->view->assign('error', 'Nevaljan ili istekao token za odjavu.');
            return $this->htmlResponse();
        }

        if ($subscription->isHidden()) {
            $this->view->assign('info', 'Već ste odjavljeni sa newsletter-a.');
            return $this->htmlResponse();
        }

        try {
            // Unsubscribe
            $subscription->setHidden(true);
            $this->subscriptionRepository->update($subscription);
            $this->persistenceManager->persistAll();
            
            error_log("SubscriptionController: Unsubscribe successful for: " . $subscription->getEmail());
            
            $this->view->assignMultiple([
                'success' => 'Uspešno ste se odjavili sa newsletter-a.',
                'email' => $subscription->getEmail(),
                'goodbyeMessage' => 'Žao nam je što odlazite! Ako promenite mišljenje, uvek se možete ponovo registrovati.'
            ]);
            
        } catch (\Exception $e) {
            error_log("SubscriptionController: Unsubscribe exception: " . $e->getMessage());
            $this->view->assign('error', 'Došlo je do greške pri odjavi: ' . $e->getMessage());
        }
        
        return $this->htmlResponse();
    }

    /**
     * Show subscription form (if needed for non-AJAX usage)
     */
    public function subscribeFormAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }
}