<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use Gmbit\NewsletterSubscription\Domain\Model\Subscription;

class SubscriptionRepository extends Repository
{
    public function findByEmail(string $email): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('email', $email)
        );
        
        return $query->execute()->getFirst();
    }

    public function findByUnsubscribeToken(string $token): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('unsubscribeToken', $token)
        );
        
        return $query->execute()->getFirst();
    }

    public function findByConfirmationToken(string $token): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('confirmationToken', $token)
        );
        
        return $query->execute()->getFirst();
    }

    public function findActiveByEmail(string $email): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('email', $email),
                $query->equals('hidden', 0),
                $query->equals('confirmed', 1)
            )
        );
        
        return $query->execute()->getFirst();
    }

    public function findConfirmedByEmail(string $email): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('email', $email),
                $query->equals('confirmed', 1)
            )
        );
        
        return $query->execute()->getFirst();
    }

    public function findPendingByEmail(string $email): ?Subscription
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('email', $email),
                $query->equals('confirmed', 0),
                $query->equals('hidden', 0)
            )
        );
        
        return $query->execute()->getFirst();
    }
}