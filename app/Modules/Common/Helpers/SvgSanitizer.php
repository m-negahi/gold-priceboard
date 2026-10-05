<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * SVG-e upload-shode rooye hamin origin serve mishe (`/storage/...`), pas yek `<script>` ya
 * `onload` tu SVG yani XSS ba session-e karbar. Ghabl az zakhire pak mishe: element-haye
 * ejrayi/kharji hazf, attribute-haye `on*` hazf, href-haye gheyr-e amn hazf, `<style>` bedoone
 * `url(`/`@import`/`expression(`. Age root `<svg>` nabashe (HTML ba pasvand-e svg), throw.
 */
final readonly class SvgSanitizer
{
    private const FORBIDDEN_ELEMENTS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'set', 'animate', 'animatetransform', 'animatemotion'];

    public static function clean(string $svg): string
    {
        $svg = trim($svg);

        if ($svg === '' || ! preg_match('/<svg[\s>]/i', $svg)) {
            throw new InvalidArgumentException('Not an SVG document.');
        }

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        $previous = libxml_use_internal_errors(true);

        // LIBXML_NONET: hich entity/DTD-e kharji load nashe (XXE).
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $document->documentElement === null || strtolower($document->documentElement->localName ?? '') !== 'svg') {
            throw new InvalidArgumentException('Not an SVG document.');
        }

        // Root ham attribute dare (`<svg onload=...>`), pas aval khodesh, ba'd zir-derakht.
        self::scrubAttributes($document->documentElement);
        self::scrub($document->documentElement);

        return (string) $document->saveXML($document->documentElement);
    }

    private static function scrub(DOMElement $element): void
    {
        // Az akhar be aval ta hazf kardan index ha ro be ham nazane.
        for ($i = $element->childNodes->length - 1; $i >= 0; $i--) {
            $child = $element->childNodes->item($i);

            if ($child === null) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comment, PI, CDATA-e sath-e bala: hich kodum lazem nist va hamlgar-e mXSS-and.
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $element->removeChild($child);
                }

                continue;
            }

            $name = strtolower($child->localName ?? $child->nodeName);

            if (in_array($name, self::FORBIDDEN_ELEMENTS, true)) {
                $element->removeChild($child);

                continue;
            }

            if ($name === 'style') {
                $css = (string) $child->textContent;

                if (preg_match('/url\s*\(|@import|expression\s*\(|javascript:/i', $css) === 1) {
                    $element->removeChild($child);

                    continue;
                }
            }

            self::scrubAttributes($child);
            self::scrub($child);
        }
    }

    private static function scrubAttributes(DOMElement $element): void
    {
        $remove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim((string) $attribute->nodeValue);

            if (str_starts_with($name, 'on') || $name === 'style' && preg_match('/url\s*\(|expression\s*\(|javascript:/i', $value) === 1) {
                $remove[] = $attribute->nodeName;

                continue;
            }

            if (in_array($name, ['href', 'xlink:href', 'src', 'data', 'from', 'to', 'values'], true) && ! self::isSafeReference($value)) {
                $remove[] = $attribute->nodeName;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    /** Faghat `#id`, masir-e nesbi/dakheli ya http(s); data:/javascript:/protocol-relative na. */
    private static function isSafeReference(string $value): bool
    {
        $decoded = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($value)) ?? '');

        if ($decoded === '' || str_starts_with($decoded, '#')) {
            return true;
        }

        if (str_starts_with($decoded, '//') || str_starts_with($decoded, '/\\')) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/', $decoded, $m) === 1) {
            return in_array(rtrim($m[0], ':'), ['http', 'https'], true);
        }

        return true;
    }
}
