<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\ContactRequestDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactRequestDtoTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testValidDtoPassesValidation(): void
    {
        $dto = new ContactRequestDto(
            name: 'John Doe',
            email: 'john@example.com',
            phone: '+1234567890',
            comment: 'I need help with my order please.',
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
    }

    public function testInvalidEmailFailsValidation(): void
    {
        $dto = new ContactRequestDto(
            name: 'John Doe',
            email: 'not-an-email',
            phone: '+1234567890',
            comment: 'I need help with my order please.',
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('email', $violations->get(0)->getPropertyPath());
    }

    public function testRequiredFieldsFailValidationWhenBlank(): void
    {
        $dto = new ContactRequestDto();

        $violations = $this->validator->validate($dto);

        self::assertGreaterThanOrEqual(4, $violations->count());

        $fields = [];
        foreach ($violations as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        self::assertContains('name', $fields);
        self::assertContains('email', $fields);
        self::assertContains('phone', $fields);
        self::assertContains('comment', $fields);
    }

    public function testLengthConstraintsAreEnforced(): void
    {
        $dto = new ContactRequestDto(
            name: 'J',
            email: 'john@example.com',
            phone: '1234',
            comment: 'Hi',
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());

        $fields = [];
        foreach ($violations as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        self::assertContains('name', $fields);
        self::assertContains('phone', $fields);
        self::assertContains('comment', $fields);
    }
}
