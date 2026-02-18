<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\TextModifier;

use App\Service\TextModifier\TextModifier;
use PHPUnit\Framework\TestCase;

class TextModifierTest extends TestCase
{
    private TextModifier $textModifier;

    protected function setUp(): void
    {
        $this->textModifier = new TextModifier();
    }

    public function testAddTrademarkToSixLetterWords(): void
    {
        $input = 'The system should handle proper words correctly.';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertStringContainsString('system™', $result);
        $this->assertStringContainsString('should™', $result);
        $this->assertStringContainsString('handle™', $result);
        $this->assertStringContainsString('proper™', $result);
    }

    public function testDoesNotModifyWordsWithDifferentLength(): void
    {
        $input = 'This is a test with words.';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertStringNotContainsString('This™', $result);
        $this->assertStringNotContainsString('is™', $result);
        $this->assertStringNotContainsString('test™', $result);
    }

    public function testHandlesEmptyString(): void
    {
        $result = $this->textModifier->addTrademarkToSixLetterWords('');
        $this->assertSame('', $result);
    }

    public function testHandlesMultipleOccurrencesOfSameWord(): void
    {
        $input = 'Please please please be patient.';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertEquals('Please™ please™ please™ be patient.', $result);
    }

    public function testHandlesMixedCaseWords(): void
    {
        $input = 'SYSTEM System system';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertStringContainsString('SYSTEM™', $result);
        $this->assertStringContainsString('System™', $result);
        $this->assertStringContainsString('system™', $result);
    }

    public function testDoesNotModifyWordsWithNumbers(): void
    {
        $input = 'test12 abc123';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertStringNotContainsString('test12™', $result);
        $this->assertStringNotContainsString('abc123™', $result);
    }

    public function testHandlesWordsAtBoundaries(): void
    {
        $input = 'system-handle,proper:friend;around(method)';
        $result = $this->textModifier->addTrademarkToSixLetterWords($input);
        
        $this->assertStringContainsString('system™', $result);
        $this->assertStringContainsString('handle™', $result);
        $this->assertStringContainsString('proper™', $result);
        $this->assertStringContainsString('friend™', $result);
        $this->assertStringContainsString('around™', $result);
        $this->assertStringContainsString('method™', $result);
    }
}
