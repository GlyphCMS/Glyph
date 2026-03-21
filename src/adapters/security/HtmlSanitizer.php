<?php

declare(strict_types=1);

namespace Glyph\adapters\security;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlSanitizer
{
    /** @var list<string> */
    private const BLOCKED_ELEMENTS = [
        'applet',
        'base',
        'embed',
        'frame',
        'frameset',
        'link',
        'math',
        'meta',
        'noscript',
        'object',
        'script',
        'style',
        'svg',
    ];

    /** @var list<string> */
    private const BLOCKED_ATTRIBUTES = [
        'srcdoc',
    ];

    /** @var list<string> */
    private const URL_ATTRIBUTES = [
        'action',
        'background',
        'cite',
        'data',
        'dynsrc',
        'formaction',
        'href',
        'longdesc',
        'lowsrc',
        'poster',
        'src',
        'xlink:href',
    ];

    /** @var list<string> */
    private const URL_LIST_ATTRIBUTES = [
        'imagesrcset',
        'srcset',
    ];

    /** @var list<string> */
    private const SAFE_URL_SCHEMES = [
        'http',
        'https',
        'mailto',
        'tel',
    ];

    public function sanitize(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            return $this->sanitizeWithoutDom($trimmed);
        }

        return $this->sanitizeWithDom($trimmed);
    }

    private function sanitizeWithDom(string $html): string
    {
        libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = '<!doctype html><html><body>' . $html . '</body></html>';

        $loaded = $document->loadHTML(
            $wrappedHtml,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if ($loaded === false) {
            libxml_clear_errors();

            return $this->sanitizeWithoutDom($html);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            libxml_clear_errors();

            return '';
        }

        $this->sanitizeNodes($body);

        $result = '';
        foreach ($body->childNodes as $childNode) {
            $htmlNode = $document->saveHTML($childNode);
            if (is_string($htmlNode)) {
                $result .= $htmlNode;
            }
        }

        libxml_clear_errors();

        return trim($result);
    }

    private function sanitizeWithoutDom(string $html): string
    {
        $blockedPattern = $this->blockedElementsPattern();
        $html = preg_replace('#<(' . $blockedPattern . ')\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(' . $blockedPattern . ')\b[^>]*/?>#is', '', $html) ?? '';

        $tagPattern = '~<\s*(/?)\s*([a-z0-9:_-]+)([^>]*)>~i';
        $attributePattern = '~([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~';

        $sanitized = preg_replace_callback(
            $tagPattern,
            function (array $matches) use ($attributePattern): string {
                $isClosing = $matches[1] === '/';
                $tagName = strtolower($matches[2]);

                if ($this->isBlockedElement($tagName)) {
                    return '';
                }

                if ($isClosing) {
                    return '</' . $tagName . '>';
                }

                $rawAttributes = $matches[3] ?? '';
                $attributes = [];

                if ($rawAttributes !== '') {
                    preg_match_all($attributePattern, $rawAttributes, $attributeMatches, PREG_SET_ORDER);

                    foreach ($attributeMatches as $attributeMatch) {
                        $attributeName = strtolower($attributeMatch[1]);
                        $hasExplicitValue = isset($attributeMatch[2]) && $attributeMatch[2] !== '';
                        $attributeValue = !$hasExplicitValue
                            ? ''
                            : ($attributeMatch[3] !== ''
                                ? $attributeMatch[3]
                                : ($attributeMatch[4] !== '' ? $attributeMatch[4] : $attributeMatch[5]));

                        $sanitizedValue = $this->sanitizeAttributeValue($tagName, $attributeName, $attributeValue);
                        if ($sanitizedValue === null) {
                            continue;
                        }

                        $attributes[$attributeName] = [
                            'value' => $sanitizedValue,
                            'boolean' => !$hasExplicitValue && $sanitizedValue === '',
                        ];
                    }
                }

                $this->enforceBlankTargetRel($tagName, $attributes);

                return '<' . $tagName . $this->renderAttributes($attributes) . '>';
            },
            $html
        );

        return is_string($sanitized) ? trim($sanitized) : '';
    }

    private function sanitizeNodes(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tagName = strtolower($child->tagName);
            if ($this->isBlockedElement($tagName)) {
                $this->removeNode($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tagName);
            $this->sanitizeNodes($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $attributeNames = [];

        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->name;
        }

        foreach ($attributeNames as $attributeName) {
            $lowerAttributeName = strtolower($attributeName);
            $value = $element->getAttribute($attributeName);
            $sanitizedValue = $this->sanitizeAttributeValue($tagName, $lowerAttributeName, $value);

            if ($sanitizedValue === null) {
                $element->removeAttribute($attributeName);
                continue;
            }

            if ($sanitizedValue !== $value) {
                $element->setAttribute($attributeName, $sanitizedValue);
            }
        }

        $this->enforceBlankTargetRelOnElement($tagName, $element);
    }

    private function sanitizeAttributeValue(string $tagName, string $attributeName, string $value): ?string
    {
        if (str_starts_with($attributeName, 'on')) {
            return null;
        }

        if (in_array($attributeName, self::BLOCKED_ATTRIBUTES, true)) {
            return null;
        }

        if ($attributeName === 'style') {
            return $this->sanitizeStyleAttribute($value);
        }

        if (in_array($attributeName, self::URL_LIST_ATTRIBUTES, true)) {
            return $this->sanitizeUrlListAttribute($value);
        }

        if (in_array($attributeName, self::URL_ATTRIBUTES, true)) {
            return $this->isSafeUrl($value) ? trim($value) : null;
        }

        return $value;
    }

    private function sanitizeStyleAttribute(string $value): ?string
    {
        $declarations = preg_split('/;(?![^()]*\))/', $value) ?: [];
        $sanitizedDeclarations = [];

        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            if (!$this->isSafeCssProperty($property)) {
                continue;
            }

            $safeValue = $this->sanitizeCssValue($parts[1]);
            if ($safeValue === null) {
                continue;
            }

            $sanitizedDeclarations[] = $property . ': ' . $safeValue;
        }

        if ($sanitizedDeclarations === []) {
            return null;
        }

        return implode('; ', $sanitizedDeclarations) . ';';
    }

    private function isSafeCssProperty(string $property): bool
    {
        if ($property === '' || in_array($property, ['-moz-binding', 'behavior'], true)) {
            return false;
        }

        return preg_match('/^(--[a-z0-9_-]+|[a-z][a-z0-9_-]*)$/i', $property) === 1;
    }

    private function sanitizeCssValue(string $value): ?string
    {
        $normalizedValue = preg_replace('/\s+/', ' ', trim($value));
        if (!is_string($normalizedValue) || $normalizedValue === '') {
            return null;
        }

        if (preg_match('/expression\s*\(|(?:java|vb)script\s*:|@import|-moz-binding|behavior\s*:|<\/?style/i', $normalizedValue) === 1) {
            return null;
        }

        $matches = [];
        if (preg_match_all('/url\((.*?)\)/i', $normalizedValue, $matches) === false) {
            return $normalizedValue;
        }

        foreach ($matches[1] as $urlCandidate) {
            $candidate = trim((string) $urlCandidate, " \t\n\r\0\x0B'\"");
            if ($candidate === '' || !$this->isSafeUrl($candidate)) {
                return null;
            }
        }

        return $normalizedValue;
    }

    private function sanitizeUrlListAttribute(string $value): ?string
    {
        $candidates = array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $candidate): bool => $candidate !== ''
        );

        $sanitizedCandidates = [];

        foreach ($candidates as $candidate) {
            if (preg_match('/^(\S+)(?:\s+(.+))?$/', $candidate, $matches) !== 1) {
                continue;
            }

            $url = $matches[1];
            $descriptor = isset($matches[2]) ? trim($matches[2]) : '';

            if (!$this->isSafeUrl($url)) {
                continue;
            }

            if ($descriptor !== '' && preg_match('/^[0-9A-Za-z.%+-]+$/', $descriptor) !== 1) {
                continue;
            }

            $sanitizedCandidates[] = $descriptor === '' ? $url : $url . ' ' . $descriptor;
        }

        if ($sanitizedCandidates === []) {
            return null;
        }

        return implode(', ', $sanitizedCandidates);
    }

    private function enforceBlankTargetRelOnElement(string $tagName, DOMElement $element): void
    {
        if (!in_array($tagName, ['a', 'area'], true)) {
            return;
        }

        if (strtolower(trim($element->getAttribute('target'))) !== '_blank') {
            return;
        }

        $element->setAttribute('rel', $this->mergeRelTokens($element->getAttribute('rel')));
    }

    /**
     * @param array<string, array{value: string, boolean: bool}> $attributes
     */
    private function enforceBlankTargetRel(string $tagName, array &$attributes): void
    {
        if (!in_array($tagName, ['a', 'area'], true)) {
            return;
        }

        $target = strtolower(trim((string) ($attributes['target']['value'] ?? '')));
        if ($target !== '_blank') {
            return;
        }

        $attributes['rel'] = [
            'value' => $this->mergeRelTokens((string) ($attributes['rel']['value'] ?? '')),
            'boolean' => false,
        ];
    }

    private function mergeRelTokens(string $value): string
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $parts[] = 'noopener';
        $parts[] = 'noreferrer';

        return implode(' ', array_values(array_unique(array_filter($parts, static fn (string $part): bool => $part !== ''))));
    }

    private function removeNode(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent !== null) {
            $parent->removeChild($element);
        }
    }

    private function isBlockedElement(string $tagName): bool
    {
        return in_array(strtolower($tagName), self::BLOCKED_ELEMENTS, true);
    }

    private function blockedElementsPattern(): string
    {
        return implode('|', array_map(static fn (string $tag): string => preg_quote($tag, '#'), self::BLOCKED_ELEMENTS));
    }

    /**
     * @param array<string, array{value: string, boolean: bool}> $attributes
     */
    private function renderAttributes(array $attributes): string
    {
        $attributeString = '';

        foreach ($attributes as $name => $attribute) {
            if ($attribute['boolean']) {
                $attributeString .= ' ' . $name;
                continue;
            }

            $attributeString .= ' ' . $name . '="' . htmlspecialchars($attribute['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $attributeString;
    }

    private function isSafeUrl(string $url): bool
    {
        $decoded = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === '') {
            return false;
        }

        $decoded = preg_replace('/[\x00-\x1F\x7F]+/u', '', $decoded);
        if (!is_string($decoded) || $decoded === '') {
            return false;
        }

        if (
            str_starts_with($decoded, '//')
            || str_starts_with($decoded, '/')
            || str_starts_with($decoded, '#')
            || str_starts_with($decoded, '?')
        ) {
            return true;
        }

        $scheme = parse_url($decoded, PHP_URL_SCHEME);
        if (!is_string($scheme) || $scheme === '') {
            return true;
        }

        return in_array(strtolower($scheme), self::SAFE_URL_SCHEMES, true);
    }
}
