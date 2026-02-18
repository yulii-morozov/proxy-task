<?php

declare(strict_types=1);

namespace App\Service\TextModifier;

class TextModifier implements TextModifierInterface
{
    public function addTrademarkToSixLetterWords(string $text): string
    {
        return preg_replace_callback(
            '/\b([a-zA-Z]{6})\b/',
            fn(array $matches): string => $matches[1] . '™',
            $text
        );
    }
}