<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(min: 2, max: 255)]
        public readonly string $name = '',

        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid email address.')]
        #[Assert\Length(max: 255)]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Phone is required.')]
        #[Assert\Length(min: 5, max: 50)]
        #[Assert\Regex(
            pattern: '/^[+\d\s\-()]+$/',
            message: 'Phone must contain only digits, spaces, and +()- characters.'
        )]
        public readonly string $phone = '',

        #[Assert\NotBlank(message: 'Comment is required.')]
        #[Assert\Length(min: 5, max: 5000)]
        public readonly string $comment = '',
    ) {
    }
}
