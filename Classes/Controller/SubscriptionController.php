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

        // Check if email already exists
        $existingSubscription = $this->subscriptionRepository->findByEmail($email);
        
        if ($existingSubscription) {
            return $this->jsonResponse(json_encode([
                'success' => false,
                'message' => 'Ova email adresa je već registrovana.'
            ]));
        }

        // Create new subscription
        $subscription = GeneralUtility::makeInstance(Subscription::class);
        $subscription->setEmail($email);
        
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
}