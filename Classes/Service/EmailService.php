<?php
declare(strict_types=1);

namespace Gmbit\NewsletterSubscription\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Mail\MailMessage;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class EmailService
{
    protected array $settings;
    protected ?LoggerInterface $logger;
    
    public function __construct(array $settings = [], LoggerInterface $logger = null)
    {
        // Merge with default settings
        $this->settings = array_merge([
            'fromEmail' => $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? 'noreply@localhost.com',
            'fromName' => $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? 'Newsletter',
            'smtpHost' => 'localhost',
            'smtpPort' => 25,
            'smtpEncryption' => false,
            'smtpUsername' => '',
            'smtpPassword' => '',
        ], $settings);
        
        $this->logger = $logger;
        $this->log('EmailService initialized with settings: ' . json_encode($this->settings));
    }

    public function sendConfirmationEmail(string $email, string $confirmationToken, int $pageId): bool
    {
        try {
            $this->log("Attempting to send confirmation email to: {$email}");
            
            // Use eID link instead of plugin action (no cHash needed!)
            $confirmationUrl = $this->getBaseUrl() . '/index.php?eID=newsletter_confirm&token=' . $confirmationToken;

            $subject = 'Potvrdite vašu prijavu za newsletter';
            $message = $this->getConfirmationEmailTemplate($email, $confirmationUrl);

            return $this->sendEmail($email, $subject, $message);
            
        } catch (\Exception $e) {
            $this->log("Exception in sendConfirmationEmail: " . $e->getMessage());
            return false;
        }
    }

    public function sendUnsubscribeEmail(string $email, string $unsubscribeToken, int $pageId): bool
    {
        try {
            $this->log("Attempting to send unsubscribe email to: {$email}");
            
            // Use eID link instead of plugin action (no cHash needed!)
            $unsubscribeUrl = $this->getBaseUrl() . '/index.php?eID=newsletter_unsubscribe_link&token=' . $unsubscribeToken;

            $subject = 'Odjava sa newsletter-a';
            $message = $this->getUnsubscribeEmailTemplate($email, $unsubscribeUrl);

            return $this->sendEmail($email, $subject, $message);
            
        } catch (\Exception $e) {
            $this->log("Exception in sendUnsubscribeEmail: " . $e->getMessage());
            return false;
        }
    }

    public function sendWelcomeEmail(string $email): bool
    {
        try {
            $subject = 'Dobrodošli u naš newsletter!';
            $message = $this->getWelcomeEmailTemplate($email);

            return $this->sendEmail($email, $subject, $message);
            
        } catch (\Exception $e) {
            $this->log("Exception in sendWelcomeEmail: " . $e->getMessage());
            return false;
        }
    }

    protected function sendEmail(string $to, string $subject, string $message): bool
    {
        // Try TYPO3 mail system first
        if ($this->sendWithTYPO3Mail($to, $subject, $message)) {
            return true;
        }

        // Fallback to PHP mail
        return $this->sendWithPHPMail($to, $subject, $message);
    }

    protected function sendWithTYPO3Mail(string $to, string $subject, string $message): bool
    {
        try {
            $this->log("Attempting TYPO3 mail send to: {$to}");
            
            if (!class_exists(MailMessage::class)) {
                $this->log("MailMessage class not found");
                return false;
            }

            $mail = GeneralUtility::makeInstance(MailMessage::class);
            
            // Create Address objects properly
            $fromAddress = new Address($this->settings['fromEmail'], $this->settings['fromName']);
            $toAddress = new Address($to);
            
            $mail->from($fromAddress)
                 ->to($toAddress)
                 ->subject($subject)
                 ->html($message);

            $result = $mail->send();
            $this->log("TYPO3 mail send result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result > 0;
            
        } catch (\Exception $e) {
            $this->log("TYPO3 mail exception: " . $e->getMessage());
            return false;
        }
    }

    protected function sendWithPHPMail(string $to, string $subject, string $message): bool
    {
        try {
            $this->log("Attempting PHP mail send to: {$to}");
            
            $headers = "From: {$this->settings['fromName']} <{$this->settings['fromEmail']}>\r\n";
            $headers .= "Reply-To: {$this->settings['fromEmail']}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: TYPO3 Newsletter\r\n";

            $result = mail($to, $subject, $message, $headers);
            $this->log("PHP mail result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result;
            
        } catch (\Exception $e) {
            $this->log("PHP mail exception: " . $e->getMessage());
            return false;
        }
    }

    protected function getConfirmationEmailTemplate(string $email, string $confirmationUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><title>Potvrdite prijavu</title></head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Potvrdite vašu email adresu</h2>
            <p>Pozdrav!</p>
            <p>Hvala vam što se prijavili za naš newsletter sa email adresom: <strong>{$email}</strong></p>
            <p>Da biste završili proces registracije, molimo vas da kliknete na dugme ispod:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$confirmationUrl}' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Potvrdite prijavu</a>
            </div>
            <p>Ili kopirajte i nalepite sledeći link u vaš browser:</p>
            <p><a href='{$confirmationUrl}'>{$confirmationUrl}</a></p>
            <p>Ako se niste vi prijavili za newsletter, možete ignorisati ovaj email.</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Ovaj email je automatski generisan. Molimo ne odgovarajte na njega.</p>
        </body>
        </html>";
    }

    protected function getUnsubscribeEmailTemplate(string $email, string $unsubscribeUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><title>Odjava sa newsletter-a</title></head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>Potvrdite odjavu</h2>
            <p>Pozdrav!</p>
            <p>Primili smo zahtev za odjavu sa newsletter-a za email adresu: <strong>{$email}</strong></p>
            <p>Da biste se odjavili, molimo vas da kliknete na dugme ispod:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$unsubscribeUrl}' style='background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Odjavi se</a>
            </div>
            <p>Ili kopirajte i nalepite sledeći link u vaš browser:</p>
            <p><a href='{$unsubscribeUrl}'>{$unsubscribeUrl}</a></p>
            <p>Ako niste vi zahtevali odjavu, možete ignorisati ovaj email.</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Ovaj email je automatski generisan. Molimo ne odgovarajte na njega.</p>
        </body>
        </html>";
    }

    protected function getWelcomeEmailTemplate(string $email): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><title>Dobrodošli!</title></head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>🎉 Dobrodošli!</h2>
            <p>Pozdrav!</p>
            <p>Hvala vam što ste potvrdili vašu email adresu <strong>{$email}</strong> i pridružili se našem newsletter-u!</p>
            <p>Od sada ćete redovno primati najnovije vesti, savete i ekskluzivne ponude direktno u vaš inbox.</p>
            <p>Hvala vam na poverenju! 🙏</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>Ovaj email je automatski generisan. Molimo ne odgovarajte na njega.</p>
        </body>
        </html>";
    }

    protected function getBaseUrl(): string
    {
        // Use your actual project URL
        return 'http://localhost/pep/public';
    }

    protected function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->info($message);
        } else {
            error_log("EmailService: " . $message);
        }
    }
}