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

namespace PhpCsFixer;

use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Carlos Cirello <carlos.cirello.nl@gmail.com>
 *
 * @internal
 */
abstract class AbstractAlignFixer extends AbstractFixer
{
    /**
     * @const Placeholder used as anchor for right alignment.
     */
    const ALIGNABLE_PLACEHOLDER = "\x2 ALIGNABLE%d \x3";

    /**
     * Keep track of the deepest level ever achieved while
     * parsing the code. Used later to replace alignment
     * placeholders with spaces.
     *
     * @var int
     */
    protected $deepestLevel;

    /**
     * Look for group of placeholders, and provide vertical alignment.
     *
     * @param Tokens $tokens
     *
     * @return string
     */
    protected function replacePlaceholder(Tokens $tokens)
    {
        $tmpCode = $tokens->generateCode();

        for ($j = 0; $j <= $this->deepestLevel; ++$j) {
            $placeholder = sprintf(self::ALIGNABLE_PLACEHOLDER, $j);

            if (false === strpos($tmpCode, $placeholder)) {
                continue;
            }

            $lines = explode("\n", $tmpCode);
            $linesWithPlaceholder = array();
            $blockSize = 0;

            $linesWithPlaceholder[$blockSize] = array();

            foreach ($lines as $index => $line) {
                if (substr_count($line, $placeholder) > 0) {
                    $linesWithPlaceholder[$blockSize][] = $index;
                } else {
                    ++$blockSize;
                    $linesWithPlaceholder[$blockSize] = array();
                }
            }

            foreach ($linesWithPlaceholder as $group) {
                if (count($group) < 1) {
                    continue;
                }

                $rightmostSymbol = 0;
                foreach ($group as $index) {
                    $rightmostSymbol = max($rightmostSymbol, strpos(utf8_decode($lines[$index]), $placeholder));
                }

                foreach ($group as $index) {
                    $line = $lines[$index];
                    $currentSymbol = strpos(utf8_decode($line), $placeholder);
                    $delta = abs($rightmostSymbol - $currentSymbol);

                    if ($delta > 0) {
                        $line = str_replace($placeholder, str_repeat(' ', $delta).$placeholder, $line);
                        $lines[$index] = $line;
                    }
                }
            }

            $tmpCode = str_replace($placeholder, '', implode("\n", $lines));
        }

        return $tmpCode;
    }
}
