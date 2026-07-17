<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class ContactMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $ownerEmail,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
        private readonly string $fallbackReply,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
    }

    public function sendOwnerNotification(Contact $contact): void
    {
        $email = (new Email())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to($this->ownerEmail)
            ->subject(sprintf('[Contact] New message from %s', $contact->getName()))
            ->text($this->buildOwnerBody($contact));

        $this->send($email, 'owner', $contact);
    }

    public function sendUserAutoReply(Contact $contact): void
    {
        $reply = $contact->getAutoReply() ?: $this->fallbackReply;

        $email = (new Email())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to($contact->getEmail())
            ->subject('We received your message')
            ->text($this->buildUserBody($contact, $reply));

        $this->send($email, 'user', $contact);
    }

    public function isAvailable(): bool
    {
        try {
            $dsn = trim($this->mailerDsn);
            if ('' === $dsn || str_starts_with($dsn, 'null://')) {
                return false;
            }

            return '' !== trim($this->mailFrom) && '' !== trim($this->ownerEmail);
        } catch (\Throwable) {
            return false;
        }
    }

    private function send(Email $email, string $recipientType, Contact $contact): void
    {
        try {
            $this->mailer->send($email);
            $this->logger->info('Contact email sent', [
                'type' => $recipientType,
                'contact_id' => $contact->getId(),
                'to' => $recipientType === 'owner' ? $this->ownerEmail : $contact->getEmail(),
            ]);
        } catch (TransportExceptionInterface $exception) {
            // Mailtrap Sandbox free tier may reject the second message in a burst.
            if (str_contains($exception->getMessage(), 'Too many emails per second')) {
                $this->logger->warning('Mailer rate-limited, retrying once', [
                    'type' => $recipientType,
                    'contact_id' => $contact->getId(),
                ]);

                sleep(15);

                try {
                    $this->mailer->send($email);
                    $this->logger->info('Contact email sent', [
                        'type' => $recipientType,
                        'contact_id' => $contact->getId(),
                        'to' => $recipientType === 'owner' ? $this->ownerEmail : $contact->getEmail(),
                        'retried' => true,
                    ]);

                    return;
                } catch (TransportExceptionInterface $retryException) {
                    $exception = $retryException;
                }
            }

            $this->logger->error('Failed to send contact email', [
                'type' => $recipientType,
                'contact_id' => $contact->getId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildOwnerBody(Contact $contact): string
    {
        return implode("\n", [
            'New contact form submission:',
            '',
            'Name: '.$contact->getName(),
            'Email: '.$contact->getEmail(),
            'Phone: '.$contact->getPhone(),
            'Comment: '.$contact->getComment(),
            '',
            'AI sentiment: '.$contact->getSentiment()->value,
            'AI category: '.$contact->getCategory()->value,
            'AI auto-reply: '.($contact->getAutoReply() ?? ''),
            '',
            'Created at: '.$contact->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function buildUserBody(Contact $contact, string $reply): string
    {
        return implode("\n", [
            'Hello '.$contact->getName().',',
            '',
            $reply,
            '',
            '---',
            'Your message:',
            $contact->getComment(),
            '',
            'Best regards,',
            $this->mailFromName,
        ]);
    }
}
