<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'API error response'
)]
final readonly class ErrorResponseDto
{
    public function __construct(
        #[OA\Property(example: false)]
        public bool $success,

        #[OA\Property(example: 'Validation failed')]
        public string $message,

        /**
         * @var list<array{field:string,message:string}>
         */
        #[OA\Property(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(
                        property: 'field',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'message',
                        type: 'string'
                    ),
                ]
            )
        )]
        public array $errors = [],
    ) {
    }
}
