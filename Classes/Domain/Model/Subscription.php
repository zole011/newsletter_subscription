<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Subscription extends AbstractEntity
{
    protected string $email = '';
    protected string $unsubscribeToken = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(string $unsubscribeToken): void
    {
        $this->unsubscribeToken = $unsubscribeToken;
    }

    /**
     * Generate unique unsubscribe token
     */
    public function generateUnsubscribeToken(): string
    {
        $this->unsubscribeToken = hash('sha256', $this->email . time() . uniqid());
        return $this->unsubscribeToken;
    }
}