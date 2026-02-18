<?php

declare(strict_types=1);

namespace App\Service\UrlRewriter;

use App\DTO\UrlRewriteContext;

final class UrlRewriter implements UrlRewriterInterface
{
    public function __construct(
        private readonly TextUrlRewriter $textRewriter,
        private readonly DomUrlRewriter $domRewriter,
    ) {
    }

    public function rewriteInText(string $content, string $proxyBaseUrl): string
    {
        return $this->textRewriter->rewrite($content, $proxyBaseUrl);
    }

    public function rewriteInDom(\DOMDocument $document, UrlRewriteContext $context): void
    {
        $this->domRewriter->rewrite($document, $context);
    }
}