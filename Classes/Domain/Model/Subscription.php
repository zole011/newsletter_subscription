<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Subscription extends AbstractEntity
{
    protected string $email = '';
    protected string $unsubscribeToken = '';
    protected string $confirmationToken = '';
    protected bool $confirmed = false;

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

    public function getConfirmationToken(): string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(string $confirmationToken): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): void
    {
        $this->confirmed = $confirmed;
    }

    /**
     * Generate unique unsubscribe token
     */
    public function generateUnsubscribeToken(): string
    {
        $this->unsubscribeToken = hash('sha256', $this->email . 'unsubscribe' . time() . uniqid());
        return $this->unsubscribeToken;
    }

    /**
     * Generate unique confirmation token
     */
    public function generateConfirmationToken(): string
    {
        $this->confirmationToken = hash('sha256', $this->email . 'confirm' . time() . uniqid());
        return $this->confirmationToken;
    }
}