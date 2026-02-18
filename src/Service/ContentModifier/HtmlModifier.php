<?php

declare(strict_types=1);

namespace App\Service\ContentModifier;

use App\DTO\HtmlModificationRequest;
use App\DTO\UrlRewriteContext;
use App\Service\UrlRewriter\UrlRewriterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

class HtmlModifier
{
    private const SUBDOMAIN_MARKER = '_sd_';

    public function __construct(
        private readonly UrlRewriterInterface $urlRewriter,
        private readonly LoggerInterface $logger,
        private readonly array $targetDomains,
        private readonly array $mainDomains
    ) {
    }

    public function modify(HtmlModificationRequest $request): string
    {
        $html = $request->html;
        $trimmed = trim($html);

        if ($trimmed === '' ||
            (!str_starts_with($trimmed, '<!') &&
             !str_starts_with($trimmed, '<?xml') &&
             !str_starts_with($trimmed, '<html') &&
             strpos($trimmed, '<') === false)) {
            return $html;
        }

        try {
            $internalErrors = libxml_use_internal_errors(true);

            $crawler = new Crawler($request->html);
            if ($crawler->count() === 0) {
                return $request->html;
            }

            $dom = $crawler->getNode(0)->ownerDocument;

            $effectiveBaseUrl = $this->getEffectiveBaseUrl($request->proxyBaseUrl, $request->subdomainHost);
            $this->addBaseTag($dom, $effectiveBaseUrl);

            $this->injectProxyJavaScript($dom, $request);

            $context = UrlRewriteContext::fromTargetUrl($request->targetUrl, $effectiveBaseUrl);
            $this->urlRewriter->rewriteInDom($dom, $context);

            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);

            return $dom->saveHTML();

        } catch (\Exception $e) {
            $this->logger->error('Error modifying HTML: ' . $e->getMessage());
            return $request->html;
        }
    }

    private function getEffectiveBaseUrl(string $proxyBaseUrl, ?string $subdomainHost): string
    {
        if ($subdomainHost) {
            return rtrim($proxyBaseUrl, '/') . '/' . self::SUBDOMAIN_MARKER . '/' . $subdomainHost;
        }

        return $proxyBaseUrl;
    }

    private function addBaseTag(\DOMDocument $document, string $effectiveBaseUrl): void
    {
        $head = $document->getElementsByTagName('head')->item(0);
        if (!$head) {
            return;
        }

        $existingBases = $document->getElementsByTagName('base');
        while ($existingBases->length > 0) {
            $existingBases->item(0)->parentNode->removeChild($existingBases->item(0));
        }

        $base = $document->createElement('base');
        $base->setAttribute('href', rtrim($effectiveBaseUrl, '/') . '/');

        if ($head->firstChild) {
            $head->insertBefore($base, $head->firstChild);
        } else {
            $head->appendChild($base);
        }
    }

    private function injectProxyJavaScript(\DOMDocument $document, HtmlModificationRequest $request): void
    {
        $head = $document->getElementsByTagName('head')->item(0);
        if (!$head) {
            return;
        }

        $proxyBase = rtrim($request->proxyBaseUrl, '/');
        $subdomainPrefix = $request->subdomainHost
            ? '/' . self::SUBDOMAIN_MARKER . '/' . $request->subdomainHost
            : '';
        $subdomainHost = $request->subdomainHost ?? '';

        $config = [
            'proxyBase' => $proxyBase,
            'targetDomains' => $this->targetDomains,
            'mainDomains' => $this->mainDomains,
            'subdomainPrefix' => $subdomainPrefix,
            'subdomainMarker' => self::SUBDOMAIN_MARKER,
            'currentSubdomain' => $subdomainHost
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        $configScript = $document->createElement('script');
        $configScript->textContent = "window.__PROXY_CONFIG__ = " . $configJson . ";";

        $scriptPath = $proxyBase . '/assets/js/proxy-rewriter.js';
        $externalScript = $document->createElement('script');
        $externalScript->setAttribute('src', $scriptPath);
        $externalScript->setAttribute('defer', 'defer');

        if ($head->firstChild) {
            $head->insertBefore($configScript, $head->firstChild);
            $head->insertBefore($externalScript, $configScript->nextSibling);
        } else {
            $head->appendChild($configScript);
            $head->appendChild($externalScript);
        }
    }
}