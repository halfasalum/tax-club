<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for rich text written in the admin editor before it
 * is rendered on public pages. Unknown elements are unwrapped (their text is
 * kept), dangerous elements are dropped, and only safe attributes survive.
 */
final class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        's' => [], 'sub' => [], 'sup' => [], 'mark' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'hr' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    /** @var list<string> */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript'];

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');

        if (! $root) {
            return e(strip_tags($html));
        }

        self::cleanChildren($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::cleanChildren($node);

            if (! array_key_exists($tag, self::ALLOWED)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            self::cleanAttributes($node, self::ALLOWED[$tag]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function cleanAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            $keep = in_array($name, $allowed, true)
                && (! in_array($name, ['href', 'src'], true) || self::isSafeUrl($value, $name));

            if (! $keep) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($element->tagName === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'nofollow noopener');
        }

        if ($element->tagName === 'img') {
            $element->setAttribute('loading', 'lazy');
        }
    }

    private static function isSafeUrl(string $url, string $attribute): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedSchemes = $attribute === 'src' ? ['https'] : ['https', 'http', 'mailto', 'tel'];

        return in_array($scheme, $allowedSchemes, true);
    }
}
