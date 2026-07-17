<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ContactRequestDto;
use App\Dto\ErrorResponseDto;
use App\Exception\ValidationException;
use App\Service\ContactService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Contact')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactService $contactService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/contact', name: 'api_contact_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a contact request',
        description: 'Validates input, stores contact in MySQL, analyzes via OpenRouter, sends emails via Mailtrap.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ContactRequestDto::class))
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Contact created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                                new OA\Property(property: 'phone', type: 'string'),
                                new OA\Property(property: 'comment', type: 'string'),
                                new OA\Property(property: 'sentiment', type: 'string', example: 'neutral'),
                                new OA\Property(property: 'category', type: 'string', example: 'support'),
                                new OA\Property(property: 'auto_reply', type: 'string'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: new Model(type: ErrorResponseDto::class)
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded',
                content: new OA\JsonContent(
                    ref: new Model(type: ErrorResponseDto::class)
                )
            ),
        ]
    )]
    public function create(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ContactRequestDto $dto,
        Request $request,
    ): JsonResponse {
        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            throw new ValidationException(errors: $errors);
        }

        $contact = $this->contactService->create($dto, $request->getClientIp() ?? 'unknown');

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $contact->getId(),
                'name' => $contact->getName(),
                'email' => $contact->getEmail(),
                'phone' => $contact->getPhone(),
                'comment' => $contact->getComment(),
                'sentiment' => $contact->getSentiment()->value,
                'category' => $contact->getCategory()->value,
                'auto_reply' => $contact->getAutoReply(),
                'created_at' => $contact->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_CREATED);
    }
}
