<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Content\App\Manifest\Xml;

use Shopware\Core\Framework\Struct\Struct;

class XmlElement extends Struct
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration
     */
    protected static function mapTranslatedTag(\DOMElement $child, array $values): array
    {
        if (!array_key_exists($child->tagName, $values)) {
            $values[self::snakeCaseToCamelCase($child->tagName)] = [];
        }

        // psalm would fail if it can't infer type from nested array
        /** @var array<string, string> $tagValues */
        $tagValues = $values[self::snakeCaseToCamelCase($child->tagName)];
        $tagValues[self::getLocaleCodeFromElement($child)] = $child->nodeValue;
        $values[self::snakeCaseToCamelCase($child->tagName)] = $tagValues;

        return $values;
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration
     */
    protected static function parseChildNodes(\DOMElement $child, callable $transformer): array
    {
        $values = [];
        foreach ($child->childNodes as $field) {
            if (!$field instanceof \DOMElement) {
                continue;
            }

            $values[] = $transformer($field);
        }

        return $values;
    }

    protected static function snakeCaseToCamelCase(string $string): string
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    private static function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }
}