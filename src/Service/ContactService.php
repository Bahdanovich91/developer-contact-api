<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequestDto;
use App\Entity\Contact;
use App\Enum\Category;
use App\Enum\Sentiment;
use App\Exception\RateLimitExceededException;
use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly OpenRouterAiService $openRouterAiService,
        private readonly ContactMailerService $contactMailerService,
        private readonly RateLimiterFactory $contactApiLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }


    public function create(ContactRequestDto $dto, string $clientIp): Contact
    {
        $limiter = $this->contactApiLimiter->create($clientIp);

        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new RateLimitExceededException(
                retryAfter: max(
                    1,
                    $limit->getRetryAfter()->getTimestamp() - time()
                )
            );
        }


        $contact = (new Contact())
            ->setName(trim($dto->name))
            ->setEmail(trim($dto->email))
            ->setPhone(trim($dto->phone))
            ->setComment(trim($dto->comment))
            ->setClientIp($clientIp);


        $this->contactRepository->save($contact);


        $aiResult = $this->openRouterAiService->analyze($dto);


        $contact
            ->setSentiment(
                Sentiment::tryFrom($aiResult->sentiment)
                ?? Sentiment::Unknown
            )
            ->setCategory(
                Category::tryFrom($aiResult->category)
                ?? Category::Other
            )
            ->setAutoReply($aiResult->autoReply);


        $this->contactRepository->save($contact);


        $this->contactMailerService
            ->sendOwnerNotification($contact);

        $this->contactMailerService
            ->sendUserAutoReply($contact);


        $this->logger->info(
            'Contact created successfully',
            [
                'contact_id' => $contact->getId(),
                'email' => $contact->getEmail(),
                'sentiment' => $contact->getSentiment()->value,
                'category' => $contact->getCategory()->value,
                'ai_fallback' => $aiResult->usedFallback,
                'ip' => $clientIp,
            ]
        );


        return $contact;
    }
}
