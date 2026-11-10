<?php

namespace App\Security\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Allow-list HTML sanitiser for rich-text fields rendered with v-html.
 *
 * No external dependency: strips scripts, event handlers, dangerous URL
 * schemes, CSS expressions, iframes/objects and PHP/SSI tags. Plain text
 * (the overwhelmingly common case) is returned byte-for-byte unchanged.
 */
final class HtmlSanitizer
{
    /** @var array<string, string[]> */
    private array $allowed = [
        'p' => ['class'], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [],
        'u' => [], 's' => [], 'strike' => [], 'ul' => [], 'ol' => ['start', 'type'],
        'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [], 'hr' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'span' => ['class'], 'div' => ['class'], 'table' => ['class'],
        'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'], 'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'], 'sub' => [], 'sup' => [],
    ];

    /** @var string[] */
    private array $urlAttributes = ['href', 'src'];

    public function clean(mixed $value): mixed
    {
        if (! is_string($value) || ! str_contains($value, '<')) {
            return $value;
        }

        // Fast reject for embedded server-side code before DOM parsing.
        if (preg_match('/<\?(?:php|=)|<%|<!\[CDATA\[|<script\b/i', $value) === 1) {
            $value = preg_replace('/<\?(?:php|=)?[\s\S]*?(?:\?>|$)|<%[\s\S]*?%>|<!\[CDATA\[[\s\S]*?\]\]>/i', '', $value) ?? '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><html><body><div id="__sec_root">'.$value.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__sec_root');
        if (! $root) {
            return '';
        }

        $this->walk($dom, $root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        // DOMDocument wraps a lot of boilerplate; normalise self-closing tags.
        return trim(str_replace('<br></br>', '<br>', $html));
    }

    private function walk(DOMDocument $dom, DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if ($tag === 'script' || $tag === 'style' || $tag === 'iframe'
                    || $tag === 'object' || $tag === 'embed' || $tag === 'form'
                    || $tag === 'input' || $tag === 'meta' || $tag === 'base'
                    || $tag === 'link' || $tag === 'svg' || $tag === 'math') {
                    $node->removeChild($child);
                    continue;
                }

                if (! isset($this->allowed[$tag])) {
                    // Unknown tag: keep its text, drop the markup.
                    $this->unwrap($dom, $node, $child);
                    continue;
                }

                $this->scrubAttributes($child, $tag);
                $this->walk($dom, $child);
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function scrubAttributes(DOMElement $element, string $tag): void
    {
        $allowedAttrs = $this->allowed[$tag];

        /** @var \DOMAttr[] $attributes */
        $attributes = [];
        foreach ($element->attributes as $attr) {
            $attributes[] = $attr;
        }

        foreach ($attributes as $attr) {
            $name  = strtolower($attr->name);
            $value = trim($attr->value);

            $isEvent = str_starts_with($name, 'on');
            $isStyle = $name === 'style';
            $isUrl   = in_array($name, $this->urlAttributes, true);

            if (! in_array($name, $allowedAttrs, true) || $isEvent || $isStyle
                || ($isUrl && ! $this->safeUrl($value))) {
                $element->removeAttributeNode($attr);
                continue;
            }

            if ($isUrl && str_starts_with(strtolower($value), 'http')) {
                // Open in a new tab safely.
                if ($tag === 'a') {
                    $element->setAttribute('rel', 'noopener noreferrer nofollow');
                }
            }

            if ($name === 'class') {
                $element->setAttribute('class', preg_replace('/[^a-zA-Z0-9_\- ]/', '', $value) ?? '');
            }
        }
    }

    private function safeUrl(string $url): bool
    {
        $url = strtolower(trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8')));
        $url = (string) preg_replace('/[\x00-\x20]+/', '', $url);

        if ($url === '' || str_starts_with($url, '#')) {
            return true;
        }

        if (preg_match('/^(?:https?:|mailto:|tel:|\/(?!\/)|data:image\/(?:png|jpe?g|gif|webp);base64,)/', $url) === 1) {
            return ! str_contains($url, 'javascript:') && ! str_contains($url, 'vbscript:');
        }

        // Relative URLs without a scheme are fine; everything else is dropped.
        return preg_match('/^[\w.\-]+(?:\/|$|\?)/', $url) === 1;
    }

    private function unwrap(DOMDocument $dom, DOMNode $parent, DOMElement $element): void
    {
        $this->walk($dom, $element);

        $fragment = $dom->createDocumentFragment();
        while ($element->firstChild) {
            $fragment->appendChild($element->firstChild);
        }
        $parent->replaceChild($fragment, $element);
    }
}
