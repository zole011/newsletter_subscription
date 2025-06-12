<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Gmbit\NewsletterSubscription\Domain\Model\Subscription;
use Gmbit\NewsletterSubscription\Domain\Repository\SubscriptionRepository;

class SubscriptionController extends ActionController
{
    protected SubscriptionRepository $subscriptionRepository;

    public function __construct(SubscriptionRepository $subscriptionRepository)
    {
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function indexAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    public function subscribeAction(): ResponseInterface
    {
        // Get email from request arguments
        $arguments = $this->request->getArguments();
        $email = $arguments['email'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(json_encode([
                'success' => false,
                'message' => 'Molimo unesite validnu email adresu.'
            ]));
        }

        // Check if email already exists and is active
        $existingSubscription = $this->subscriptionRepository->findActiveByEmail($email);
        
        if ($existingSubscription) {
            return $this->jsonResponse(json_encode([
                'success' => false,
                'message' => 'Ova email adresa je već registrovana.'
            ]));
        }

        // Check if there's a hidden (unsubscribed) record to reactivate
        $hiddenSubscription = $this->subscriptionRepository->findByEmail($email);
        
        if ($hiddenSubscription && $hiddenSubscription->isHidden()) {
            // Reactivate existing subscription
            $hiddenSubscription->setHidden(false);
            $hiddenSubscription->generateUnsubscribeToken();
            
            try {
                $this->subscriptionRepository->update($hiddenSubscription);
                $this->persistenceManager->persistAll();
                
                return $this->jsonResponse(json_encode([
                    'success' => true,
                    'message' => 'Uspešno ste se ponovo registrovali za newsletter!'
                ]));
            } catch (\Exception $e) {
                return $this->jsonResponse(json_encode([
                    'success' => false,
                    'message' => 'Došlo je do greške. Molimo pokušajte ponovo.'
                ]));
            }
        }

        // Create new subscription
        $subscription = GeneralUtility::makeInstance(Subscription::class);
        $subscription->setEmail($email);
        $subscription->generateUnsubscribeToken();
        
        try {
            $this->subscriptionRepository->add($subscription);
            $this->persistenceManager->persistAll();
            
            return $this->jsonResponse(json_encode([
                'success' => true,
                'message' => 'Uspešno ste se registrovali za newsletter!'
            ]));
        } catch (\Exception $e) {
            return $this->jsonResponse(json_encode([
                'success' => false,
                'message' => 'Došlo je do greške. Molimo pokušajte ponovo.'
            ]));
        }
    }

    public function unsubscribeAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $email = $arguments['email'] ?? '';
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view->assign('error', 'Molimo unesite validnu email adresu.');
            return $this->htmlResponse();
        }

        // Find active subscription
        $subscription = $this->subscriptionRepository->findActiveByEmail($email);
        
        if (!$subscription) {
            $this->view->assign('error', 'Email adresa nije pronađena u našoj bazi.');
            return $this->htmlResponse();
        }

        // Generate unsubscribe link
        $unsubscribeLink = $this->uriBuilder
            ->setTargetPageUid($GLOBALS['TSFE']->id)
            ->uriFor('confirmUnsubscribe', ['token' => $subscription->getUnsubscribeToken()]);
        
        $this->view->assignMultiple([
            'email' => $email,
            'unsubscribeLink' => $unsubscribeLink,
            'message' => 'Link za odjavu je poslat na vašu email adresu.'
        ]);

        // Here you would typically send an email with the unsubscribe link
        // For demo purposes, we're just showing the link
        
        return $this->htmlResponse();
    }

    public function confirmUnsubscribeAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $token = $arguments['token'] ?? '';
        
        if (empty($token)) {
            $this->view->assign('error', 'Nevaljan token za odjavu.');
            return $this->htmlResponse();
        }

        // Find subscription by token
        $subscription = $this->subscriptionRepository->findByUnsubscribeToken($token);
        
        if (!$subscription) {
            $this->view->assign('error', 'Nevaljan ili istekao token za odjavu.');
            return $this->htmlResponse();
        }

        try {
            // Hide subscription instead of deleting it
            $subscription->setHidden(true);
            $this->subscriptionRepository->update($subscription);
            $this->persistenceManager->persistAll();
            
            $this->view->assign('success', 'Uspešno ste se odjavili sa newsletter-a.');
            
        } catch (\Exception $e) {
            $this->view->assign('error', 'Došlo je do greške. Molimo pokušajte ponovo.');
        }
        
        return $this->htmlResponse();
    }
}