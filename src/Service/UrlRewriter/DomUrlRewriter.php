<?php

declare(strict_types=1);

namespace App\Service\UrlRewriter;

use App\DTO\UrlRewriteContext;

final class DomUrlRewriter
{
    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'data', 'poster', 'srcset'];

    public function __construct(
        private readonly TextUrlRewriter $textRewriter,
    ) {
    }

    public function rewrite(\DOMDocument $document, UrlRewriteContext $context): void
    {
        $xpath = new \DOMXPath($document);

        $this->rewriteUrlAttributes($xpath, $context);
        $this->rewriteOtherAttributes($xpath, $context);
        $this->rewriteStyleAttributes($xpath, $context);
        $this->rewriteInlineScriptsAndStyles($xpath, $context);
    }

    private function rewriteUrlAttributes(\DOMXPath $xpath, UrlRewriteContext $context): void
    {
        $query = '// *[' . implode(' or ', array_map(fn($a) => "@$a", self::URL_ATTRIBUTES)) . ']';

        foreach ($xpath->query($query) as $element) {
            foreach (self::URL_ATTRIBUTES as $attr) {
                if (!$element->hasAttribute($attr)) {
                    continue;
                }

                $value = $element->getAttribute($attr);
                $newValue = $attr === 'srcset'
                    ? $this->textRewriter->rewriteSrcset($value, $context)
                    : $this->textRewriter->rewriteUrl($value, $context);

                if ($value !== $newValue) {
                    $element->setAttribute($attr, $newValue);
                }
            }
        }
    }

    private function rewriteOtherAttributes(\DOMXPath $xpath, UrlRewriteContext $context): void
    {
        $targetHostBase = preg_replace('/^www\./', '', $context->targetHost);
        if (empty($targetHostBase)) {
            return;
        }

        foreach ($xpath->query("//@*[contains(., '$targetHostBase')]") as $attrNode) {
            if (in_array($attrNode->name, self::URL_ATTRIBUTES, true)) {
                continue;
            }

            $newValue = $this->textRewriter->rewrite($attrNode->value, $context->proxyBaseUrl);

            if ($newValue !== $attrNode->value) {
                $attrNode->value = $newValue;
            }
        }
    }

    private function rewriteStyleAttributes(\DOMXPath $xpath, UrlRewriteContext $context): void
    {
        foreach ($xpath->query('//*[@style]') as $el) {
            $style = $el->getAttribute('style');
            $newStyle = $this->textRewriter->rewriteInlineStyle($style, $context);
            $el->setAttribute('style', $newStyle);
        }
    }

    private function rewriteInlineScriptsAndStyles(\DOMXPath $xpath, UrlRewriteContext $context): void
    {
        foreach ($xpath->query('//script[not(@src)]|//style') as $node) {
            if ($node->nodeValue) {
                $newValue = $this->textRewriter->rewrite($node->nodeValue, $context->proxyBaseUrl);
                if ($newValue !== $node->nodeValue) {
                    $node->textContent = $newValue;
                }
            }
        }
    }
}