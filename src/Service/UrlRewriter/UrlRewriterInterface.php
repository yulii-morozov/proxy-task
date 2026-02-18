<?php

declare(strict_types=1);

namespace App\Service\UrlRewriter;

use App\DTO\UrlRewriteContext;

interface UrlRewriterInterface
{
    public function rewriteInText(string $content, string $proxyBaseUrl): string;

    public function rewriteInDom(\DOMDocument $document, UrlRewriteContext $context): void;
}