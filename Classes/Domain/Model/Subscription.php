<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Subscription extends AbstractEntity
{
    protected string $email = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}