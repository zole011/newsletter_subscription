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
}