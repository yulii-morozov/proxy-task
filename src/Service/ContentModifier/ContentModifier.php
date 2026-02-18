<?php

declare(strict_types=1);

namespace App\Service\ContentModifier;

use App\DTO\ContentModificationRequest;
use App\DTO\HtmlModificationRequest;
use App\Service\UrlRewriter\UrlRewriterInterface;
use Psr\Log\LoggerInterface;

class ContentModifier implements ContentModifierInterface
{
    public function __construct(
        private readonly HtmlModifier $htmlModifier,
        private readonly UrlRewriterInterface $urlRewriter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function modify(ContentModificationRequest $request): string
    {
        $contentTypeLower = strtolower($request->contentType);

        if ($this->isBinaryContent($contentTypeLower)) {
            return $request->content;
        }

        if ($this->isHtmlContent($contentTypeLower)) {
            return $this->processHtmlContent($request);
        }

        if ($this->isJsonContent($contentTypeLower, $request->content)) {
            return $this->urlRewriter->rewriteInText($request->content, $request->proxyBaseUrl);
        }

        if ($this->isTextContent($contentTypeLower)) {
            return $this->urlRewriter->rewriteInText($request->content, $request->proxyBaseUrl);
        }

        return $request->content;
    }

    private function processHtmlContent(ContentModificationRequest $request): string
    {
        $trimmed = trim($request->content);

        if ($trimmed === '' || $trimmed[0] !== '<') {
            return $this->urlRewriter->rewriteInText($request->content, $request->proxyBaseUrl);
        }

        try {
            $htmlRequest = new HtmlModificationRequest(
                html: $request->content,
                targetUrl: $request->targetUrl,
                proxyBaseUrl: $request->proxyBaseUrl,
                subdomainHost: $request->subdomainHost
            );

            return $this->htmlModifier->modify($htmlRequest);
        } catch (\Throwable $e) {
            $this->logger->warning('HTML parsing failed, falling back to text rewrite', [
                'url' => $request->targetUrl,
                'error' => $e->getMessage()
            ]);

            return $this->urlRewriter->rewriteInText($request->content, $request->proxyBaseUrl);
        }
    }

    private function isJsonContent(string $contentType, string $content): bool
    {
        if (str_contains($contentType, 'json')) {
            return true;
        }

        $trimmed = trim($content);
        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            json_decode($trimmed);
            return json_last_error() === JSON_ERROR_NONE;
        }

        return false;
    }

    private function isHtmlContent(string $contentType): bool
    {
        return str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml');
    }

    private function isTextContent(string $contentType): bool
    {
        return str_starts_with($contentType, 'text/')
            || str_contains($contentType, 'javascript')
            || str_contains($contentType, 'xml');
    }

    private function isBinaryContent(string $contentType): bool
    {
        return str_contains($contentType, 'image/')
            || str_contains($contentType, 'video/')
            || str_contains($contentType, 'audio/')
            || str_contains($contentType, 'application/octet-stream')
            || str_contains($contentType, 'application/pdf');
    }
}