<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Contact;
use App\Enum\Category;
use App\Enum\Sentiment;
use App\Service\ContactMailerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class ContactMailerServiceTest extends TestCase
{
    public function testSendOwnerNotificationDoesNotThrowOnTransportError(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unavailable.'));

        $service = new ContactMailerService(
            mailer: $mailer,
            logger: new NullLogger(),
            ownerEmail: 'owner@example.com',
            mailFrom: 'no-reply@example.com',
            mailFromName: 'Contact API',
            fallbackReply: 'Thank you!',
        );

        $service->sendOwnerNotification($this->createContact());

        self::assertTrue(true);
    }

    public function testIsAvailableReturnsFalseWhenConfigurationIsIncomplete(): void
    {
        $service = new ContactMailerService(
            mailer: $this->createStub(MailerInterface::class),
            logger: new NullLogger(),
            ownerEmail: '',
            mailFrom: 'no-reply@example.com',
            mailFromName: 'Contact API',
            fallbackReply: 'Thank you!',
        );

        self::assertFalse($service->isAvailable());
    }

    public function testIsAvailableReturnsTrueWhenConfigurationIsComplete(): void
    {
        $service = new ContactMailerService(
            mailer: $this->createStub(MailerInterface::class),
            logger: new NullLogger(),
            ownerEmail: 'owner@example.com',
            mailFrom: 'no-reply@example.com',
            mailFromName: 'Contact API',
            fallbackReply: 'Thank you!',
        );

        self::assertTrue($service->isAvailable());
    }

    private function createContact(): Contact
    {
        $contact = new Contact();

        $contact->setName('John Doe');
        $contact->setEmail('john@example.com');
        $contact->setPhone('+1234567890');
        $contact->setComment('Need help please');
        $contact->setSentiment(Sentiment::Neutral);
        $contact->setCategory(Category::Support);
        $contact->setAutoReply('Thank you!');

        $contact->onPrePersist();

        return $contact;
    }
}
