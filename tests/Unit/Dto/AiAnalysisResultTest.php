<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\AiAnalysisResult;
use PHPUnit\Framework\TestCase;

final class AiAnalysisResultTest extends TestCase
{
    public function testFallbackCreatesExpectedResult(): void
    {
        $result = AiAnalysisResult::fallback('Thanks!');

        self::assertSame('unknown', $result->sentiment);
        self::assertSame('other', $result->category);
        self::assertSame('Thanks!', $result->autoReply);
        self::assertTrue($result->usedFallback);
    }

    public function testConstructorSetsProperties(): void
    {
        $result = new AiAnalysisResult(
            sentiment: 'positive',
            category: 'support',
            autoReply: 'We will help you soon.',
            usedFallback: false,
        );

        self::assertSame('positive', $result->sentiment);
        self::assertSame('support', $result->category);
        self::assertSame('We will help you soon.', $result->autoReply);
        self::assertFalse($result->usedFallback);
    }
}
