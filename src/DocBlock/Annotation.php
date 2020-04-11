<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\DocBlock;

use PhpCsFixer\Preg;

/**
 * This represents an entire annotation from a docblock.
 *
 * @author Graham Campbell <graham@alt-three.com>
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @final
 */
class Annotation
{
    /**
     * Regex to match any types, shall be used with `x` modifier.
     *
     * @internal
     */
    const REGEX_TYPES = '
    (?<types> # alternation of several types separated by `|`
        (?<type> # single type
            \?? # optionally nullable
            (?:
                (?<object_like_array>
                    array\h*\{
                        (?<object_like_array_key>
                            \h*[^?:\h]+\h*\??\h*:\h*(?&types)
                        )
                        (?:\h*,(?&object_like_array_key))*
                    \h*\}
                )
                |
                (?<callable> # callable syntax, e.g. `callable(string): bool`
                    (?:callable|Closure)\h*\(\h*
                        (?&types)
                        (?:
                            \h*,\h*
                            (?&types)
                        )*
                    \h*\)
                    (?:
                        \h*\:\h*
                        (?&types)
                    )?
                )
                |
                (?<generic> # generic syntax, e.g.: `array<int, \Foo\Bar>`
                    (?&name)+
                    \h*<\h*
                        (?&types)
                        (?:
                            \h*,\h*
                            (?&types)
                        )*
                    \h*>
                )
                |
                (?<class_constant> # class constants with optional wildcard, e.g.: `Foo::*`, `Foo::CONST_A`, `FOO::CONST_*`
                    (?&name)::(\*|\w+\*?)
                )
                |
                (?<array> # array expression, e.g.: `string[]`, `string[][]`
                    (?&name)(\[\])+
                )
                |
                (?<constant> # single constant value (case insensitive), e.g.: 1, `\'a\'`
                    (?i)
                    null | true | false
                    | [\d.]+
                    | \'[^\']+?\' | "[^"]+?"
                    | [@$]?(?:this | self | static)
                    (?-i)
                )
                |
                (?<name> # single type, e.g.: `null`, `int`, `\Foo\Bar`
                    [\\\\\w-]++
                )
            )
            (?: # intersection
                \h*&\h*
                (?&type)
            )*
        )
        (?:
            \h*\|\h*
            (?&type)
        )*
    )
    ';

    /**
     * All the annotation tag names with types.
     *
     * @var string[]
     */
    private static $tags = [
        'method',
        'param',
        'property',
        'property-read',
        'property-write',
        'return',
        'throws',
        'type',
        'var',
    ];

    /**
     * The lines that make up the annotation.
     *
     * @var Line[]
     */
    private $lines;

    /**
     * The position of the first line of the annotation in the docblock.
     *
     * @var int
     */
    private $start;

    /**
     * The position of the last line of the annotation in the docblock.
     *
     * @var int
     */
    private $end;

    /**
     * The associated tag.
     *
     * @var null|Tag
     */
    private $tag;

    /**
     * Lazy loaded, cached types content.
     *
     * @var null|string
     */
    private $typesContent;

    /**
     * The cached types.
     *
     * @var null|string[]
     */
    private $types;

    /**
     * Create a new line instance.
     *
     * @param Line[] $lines
     */
    public function __construct(array $lines)
    {
        $this->lines = array_values($lines);

        $keys = array_keys($lines);

        $this->start = $keys[0];
        $this->end = end($keys);
    }

    /**
     * Get the string representation of object.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * Get all the annotation tag names with types.
     *
     * @return string[]
     */
    public static function getTagsWithTypes()
    {
        return self::$tags;
    }

    /**
     * Get the start position of this annotation.
     *
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Get the end position of this annotation.
     *
     * @return int
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Get the associated tag.
     *
     * @return Tag
     */
    public function getTag()
    {
        if (null === $this->tag) {
            $this->tag = new Tag($this->lines[0]);
        }

        return $this->tag;
    }

    /**
     * Get the types associated with this annotation.
     *
     * @return string[]
     */
    public function getTypes()
    {
        if (null === $this->types) {
            $this->types = [];

            $content = $this->getTypesContent();

            while ('' !== $content && false !== $content) {
                Preg::match(
                    '{^'.self::REGEX_TYPES.'$}x',
                    $content,
                    $matches
                );

                $this->types[] = $matches['type'];
                $content = Preg::replace(
                    '/^'.preg_quote($matches['type'], '/').'(\h*\|\h*)?/',
                    '',
                    $content
                );
            }
        }

        return $this->types;
    }

    /**
     * Set the types associated with this annotation.
     *
     * @param string[] $types
     */
    public function setTypes(array $types)
    {
        $pattern = '/'.preg_quote($this->getTypesContent(), '/').'/';

        $this->lines[0]->setContent(Preg::replace($pattern, implode('|', $types), $this->lines[0]->getContent(), 1));

        $this->clearCache();
    }

    /**
     * Get the normalized types associated with this annotation, so they can easily be compared.
     *
     * @return string[]
     */
    public function getNormalizedTypes()
    {
        $normalized = array_map(static function ($type) {
            return strtolower($type);
        }, $this->getTypes());

        sort($normalized);

        return $normalized;
    }

    /**
     * Remove this annotation by removing all its lines.
     */
    public function remove()
    {
        foreach ($this->lines as $line) {
            if ($line->isTheStart() && $line->isTheEnd()) {
                // Single line doc block, remove entirely
                $line->remove();
            } elseif ($line->isTheStart()) {
                // Multi line doc block, but start is on the same line as the first annotation, keep only the start
                $content = Preg::replace('#(\s*/\*\*).*#', '$1', $line->getContent());

                $line->setContent($content);
            } elseif ($line->isTheEnd()) {
                // Multi line doc block, but end is on the same line as the last annotation, keep only the end
                $content = Preg::replace('#(\s*)\S.*(\*/.*)#', '$1$2', $line->getContent());

                $line->setContent($content);
            } else {
                // Multi line doc block, neither start nor end on this line, can be removed safely
                $line->remove();
            }
        }

        $this->clearCache();
    }

    /**
     * Get the annotation content.
     *
     * @return string
     */
    public function getContent()
    {
        return implode('', $this->lines);
    }

    public function supportTypes()
    {
        return \in_array($this->getTag()->getName(), self::$tags, true);
    }

    /**
     * Get the current types content.
     *
     * Be careful modifying the underlying line as that won't flush the cache.
     *
     * @return string
     */
    private function getTypesContent()
    {
        if (null === $this->typesContent) {
            $name = $this->getTag()->getName();

            if (!$this->supportTypes()) {
                throw new \RuntimeException('This tag does not support types.');
            }

            $matchingResult = Preg::match(
                '{^(?:\s*\*|/\*\*)\s*@'.$name.'\s+'.self::REGEX_TYPES.'(?:[*\h].*)?$}sx',
                $this->lines[0]->getContent(),
                $matches
            );

            $this->typesContent = 1 === $matchingResult
                ? $matches['types']
                : '';
        }

        return $this->typesContent;
    }

    private function clearCache()
    {
        $this->types = null;
        $this->typesContent = null;
    }
}
