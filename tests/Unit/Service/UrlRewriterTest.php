<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\UrlRewriteContext;
use App\Service\UrlRewriter\DomUrlRewriter;
use App\Service\UrlRewriter\TextUrlRewriter;
use App\Service\UrlRewriter\UrlRewriter;
use PHPUnit\Framework\TestCase;

final class UrlRewriterTest extends TestCase
{
    private UrlRewriter $rewriter;

    protected function setUp(): void
    {
        $textRewriter = new TextUrlRewriter(['https://example.com']);
        $domRewriter = new DomUrlRewriter($textRewriter);
        $this->rewriter = new UrlRewriter($textRewriter, $domRewriter);
    }

    private function makeContext(
        string $proxyBaseUrl = 'https://proxy.local',
        string $targetHost = 'example.com',
        string $targetScheme = 'https',
    ): UrlRewriteContext {
        return new UrlRewriteContext(
            targetScheme: $targetScheme,
            targetHost: $targetHost,
            proxyBaseUrl: $proxyBaseUrl,
        );
    }

    private function makeDocument(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return $doc;
    }

    public function testRewriteInTextReplacesAbsoluteUrl(): void
    {
        $result = $this->rewriter->rewriteInText('https://example.com/page', 'https://proxy.local');
        $this->assertSame('https://proxy.local/page', $result);
    }

    public function testRewriteInTextUnwrapsCachedUrl(): void
    {
        $encoded = urlencode('https://example.com/original');
        $result = $this->rewriter->rewriteInText("cached.php?f=$encoded", 'https://proxy.local');
        $this->assertSame('https://proxy.local/original', $result);
    }

    public function testRewriteInTextHandlesWwwSubdomain(): void
    {
        $result = $this->rewriter->rewriteInText('https://www.example.com/page', 'https://proxy.local');
        $this->assertSame('https://proxy.local/page', $result);
    }

    public function testRewriteInTextHandlesProtocolRelativeUrl(): void
    {
        $result = $this->rewriter->rewriteInText('//example.com/page', 'https://proxy.local');
        $this->assertSame('https://proxy.local/page', $result);
    }

    public function testRewriteInTextDoesNotModifyUnrelatedDomain(): void
    {
        $content = 'https://other.com/page';
        $result = $this->rewriter->rewriteInText($content, 'https://proxy.local');
        $this->assertSame($content, $result);
    }

    public function testRewriteInTextSkipsAlreadyProxiedUrl(): void
    {
        $url = 'https://proxy.local/page';
        $result = $this->rewriter->rewriteInText($url, 'https://proxy.local');
        $this->assertSame($url, $result);
    }

    public function testRewriteInTextHandlesMultipleUrlsInContent(): void
    {
        $content = 'go to https://example.com/a or https://example.com/b';
        $result = $this->rewriter->rewriteInText($content, 'https://proxy.local');
        $this->assertSame('go to https://proxy.local/a or https://proxy.local/b', $result);
    }

    public function testRewriteInDomRewritesHrefAttribute(): void
    {
        $doc = $this->makeDocument('<a href="https://example.com/page">link</a>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $link = $doc->getElementsByTagName('a')->item(0);
        $this->assertSame('https://proxy.local/page', $link->getAttribute('href'));
    }

    public function testRewriteInDomRewritesSrcAttribute(): void
    {
        $doc = $this->makeDocument('<img src="https://example.com/image.jpg">');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $img = $doc->getElementsByTagName('img')->item(0);
        $this->assertSame('https://proxy.local/image.jpg', $img->getAttribute('src'));
    }

    public function testRewriteInDomRewritesSrcset(): void
    {
        $doc = $this->makeDocument('<img srcset="https://example.com/img.jpg 1x, https://example.com/img@2x.jpg 2x">');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $img = $doc->getElementsByTagName('img')->item(0);
        $this->assertSame(
            'https://proxy.local/img.jpg 1x, https://proxy.local/img@2x.jpg 2x',
            $img->getAttribute('srcset')
        );
    }

    public function testRewriteInDomRewritesFormAction(): void
    {
        $doc = $this->makeDocument('<form action="https://example.com/submit"></form>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $form = $doc->getElementsByTagName('form')->item(0);
        $this->assertSame('https://proxy.local/submit', $form->getAttribute('action'));
    }

    public function testRewriteInDomRewritesStyleAttribute(): void
    {
        $doc = $this->makeDocument('<div style="background: url(\'https://example.com/bg.png\')"></div>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $div = $doc->getElementsByTagName('div')->item(0);
        $this->assertStringContainsString('https://proxy.local/bg.png', $div->getAttribute('style'));
    }

    public function testRewriteInDomRewritesInlineScript(): void
    {
        $doc = $this->makeDocument('<script>var url = "https://example.com/api";</script>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $script = $doc->getElementsByTagName('script')->item(0);
        $this->assertStringContainsString('https://proxy.local/api', $script->textContent);
        $this->assertStringNotContainsString('example.com', $script->textContent);
    }

    public function testRewriteInDomRewritesStyleBlock(): void
    {
        $doc = $this->makeDocument('<style>.bg { background: url("https://example.com/bg.png"); }</style>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $style = $doc->getElementsByTagName('style')->item(0);
        $this->assertStringContainsString('https://proxy.local/bg.png', $style->textContent);
    }

    public function testRewriteInDomDoesNotModifyUnrelatedDomain(): void
    {
        $doc = $this->makeDocument('<a href="https://other.com/page">link</a>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $link = $doc->getElementsByTagName('a')->item(0);
        $this->assertSame('https://other.com/page', $link->getAttribute('href'));
    }

    public function testRewriteInDomDoesNotModifyHashLinks(): void
    {
        $doc = $this->makeDocument('<a href="#section">link</a>');
        $this->rewriter->rewriteInDom($doc, $this->makeContext());

        $link = $doc->getElementsByTagName('a')->item(0);
        $this->assertSame('#section', $link->getAttribute('href'));
    }
}