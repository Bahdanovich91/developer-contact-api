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
    public function testSendOwnerNotificationSuccessfully(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send');

        $service = $this->createService($mailer);

        $service->sendOwnerNotification($this->createContact());
    }

    public function testSendUserAutoReplySuccessfully(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send');

        $service = $this->createService($mailer);

        $service->sendUserAutoReply($this->createContact());
    }

    public function testSendOwnerNotificationDoesNotThrowOnTransportError(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unavailable.'));

        $service = $this->createService($mailer);

        $service->sendOwnerNotification($this->createContact());

        self::assertTrue(true);
    }

    public function testSendUserAutoReplyDoesNotThrowOnTransportError(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP unavailable.'));

        $service = $this->createService($mailer);

        $service->sendUserAutoReply($this->createContact());

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
            mailerDsn: 'smtp://user:pass@sandbox.smtp.mailtrap.io:2525',
        );

        self::assertFalse($service->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenMailerDsnIsNullTransport(): void
    {
        $service = new ContactMailerService(
            mailer: $this->createStub(MailerInterface::class),
            logger: new NullLogger(),
            ownerEmail: 'owner@example.com',
            mailFrom: 'no-reply@example.com',
            mailFromName: 'Contact API',
            fallbackReply: 'Thank you!',
            mailerDsn: 'null://null',
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
            mailerDsn: 'smtp://user:pass@sandbox.smtp.mailtrap.io:2525',
        );

        self::assertTrue($service->isAvailable());
    }

    private function createService(MailerInterface $mailer): ContactMailerService
    {
        return new ContactMailerService(
            mailer: $mailer,
            logger: new NullLogger(),
            ownerEmail: 'owner@example.com',
            mailFrom: 'no-reply@example.com',
            mailFromName: 'Contact API',
            fallbackReply: 'Thank you!',
            mailerDsn: 'smtp://user:pass@sandbox.smtp.mailtrap.io:2525',
        );
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
