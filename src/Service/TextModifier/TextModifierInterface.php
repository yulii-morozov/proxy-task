<?php

declare(strict_types=1);

namespace App\Service\TextModifier;

interface TextModifierInterface
{
    public function addTrademarkToSixLetterWords(string $text): string;
}