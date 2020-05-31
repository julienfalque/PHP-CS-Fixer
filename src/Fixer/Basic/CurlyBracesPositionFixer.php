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

namespace PhpCsFixer\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\VersionSpecification;
use PhpCsFixer\FixerDefinition\VersionSpecificCodeSample;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

final class CurlyBracesPositionFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface, WhitespacesAwareFixerInterface
{
    /**
     * @internal
     */
    const NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END = 'next_line_unless_newline_at_signature_end';

    /**
     * @internal
     */
    const SAME_LINE = 'same_line';

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Curly braces must be placed as configured.',
            [
                new CodeSample(
                    '<?php
class Foo {
}

function foo() {
}

$foo = function()
{
};

if (foo())
{
    bar();
}
'
                ),
                new VersionSpecificCodeSample(
                    '<?php
$foo = new class
{
};
',
                    new VersionSpecification(70000)
                ),
                new CodeSample(
                    '<?php
if (foo()) {
    bar();
}
',
                    ['control_structures_opening_brace' => self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END]
                ),
                new CodeSample(
                    '<?php
function foo()
{
}
',
                    ['functions_opening_brace' => self::SAME_LINE]
                ),
                new CodeSample(
                    '<?php
$foo = function () {
};
',
                    ['anonymous_functions_opening_brace' => self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END]
                ),
                new CodeSample(
                    '<?php
class Foo
{
}
',
                    ['classes_opening_brace' => self::SAME_LINE]
                ),
                new VersionSpecificCodeSample(
                    '<?php
$foo = new class {
};
',
                    new VersionSpecification(70000),
                    ['anonymous_classes_opening_brace' => self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END]
                ),
                new VersionSpecificCodeSample(
                    '<?php
$foo = new class { };
$bar = new class { private $baz; };
',
                    new VersionSpecification(70000),
                    ['allow_single_line_empty_anonymous_classes' => true]
                ),
                new CodeSample(
                    '<?php
$foo = function () { return true; };
$bar = function () { $result = true;
    return $result; };
',
                    ['allow_single_line_anonymous_functions' => true]
                ),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound('{');
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        $allowSingleLineUntil = null;

        for ($index = 0, $max = $tokens->count(); $index < $max; ++$index) {
            $token = $tokens[$index];

            $allowSingleLine = false;
            $allowSingleLineIfEmpty = false;

            if ($token->isGivenKind([T_CLASS, T_INTERFACE, T_TRAIT])) {
                $openBraceIndex = $tokens->getNextTokenOfKind($index, ['{']);

                if ($tokensAnalyzer->isAnonymousClass($index)) {
                    $allowSingleLineIfEmpty = $this->configuration['allow_single_line_empty_anonymous_classes'];
                    $positionOption = 'anonymous_classes_opening_brace';
                } else {
                    $positionOption = 'classes_opening_brace';
                }
            } elseif ($token->isGivenKind(T_FUNCTION)) {
                $openBraceIndex = $tokens->getNextTokenOfKind($index, ['{', ';']);

                if ($tokens[$openBraceIndex]->equals(';')) {
                    continue;
                }

                if ($tokensAnalyzer->isLambda($index)) {
                    $allowSingleLine = $this->configuration['allow_single_line_anonymous_functions'];
                    $positionOption = 'anonymous_functions_opening_brace';
                } else {
                    $positionOption = 'functions_opening_brace';
                }
            } elseif ($token->isGivenKind([T_DECLARE, T_DO, T_ELSE, T_ELSEIF, T_FINALLY, T_FOR, T_FOREACH, T_IF, T_WHILE, T_TRY, T_CATCH, T_SWITCH])) {
                $parenthesisEndIndex = $this->findParenthesisEnd($tokens, $index);
                $openBraceIndex = $tokens->getNextMeaningfulToken($parenthesisEndIndex);

                if (!$tokens[$openBraceIndex]->equals('{')) {
                    continue;
                }

                $positionOption = 'control_structures_opening_brace';
            } else {
                continue;
            }

            $closeBraceIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBraceIndex);

            $addNewlinesInsideBraces = true;
            if ($allowSingleLine || $allowSingleLineIfEmpty || $index < $allowSingleLineUntil) {
                $addNewlinesInsideBraces = false;

                for ($indexInsideBraces = $openBraceIndex + 1; $indexInsideBraces < $closeBraceIndex; ++$indexInsideBraces) {
                    $tokenInsideBraces = $tokens[$indexInsideBraces];

                    if (
                        ($allowSingleLineIfEmpty && !$tokenInsideBraces->isWhitespace() && !$tokenInsideBraces->isComment())
                        || ($tokenInsideBraces->isWhitespace() && 1 === Preg::match('/\R/', $tokenInsideBraces->getContent()))
                    ) {
                        $addNewlinesInsideBraces = true;

                        break;
                    }
                }

                if (!$addNewlinesInsideBraces && null === $allowSingleLineUntil) {
                    $allowSingleLineUntil = $closeBraceIndex;
                }
            }

            if (
                $addNewlinesInsideBraces
                && !$this->isFollowedByNewLine($tokens, $openBraceIndex)
                && !$this->hasCommentOnSameLine($tokens, $openBraceIndex)
                && !$tokens[$tokens->getNextMeaningfulToken($openBraceIndex)]->isGivenKind(T_CLOSE_TAG)
            ) {
                $whitespace = $this->whitespacesConfig->getLineEnding().$this->getLineIndentation($tokens, $openBraceIndex);
                if ($tokens->ensureWhitespaceAtIndex($openBraceIndex + 1, 0, $whitespace)) {
                    ++$closeBraceIndex;
                }
            }

            $whitespace = ' ';
            if (self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END === $this->configuration[$positionOption]) {
                $whitespace = $this->whitespacesConfig->getLineEnding().$this->getLineIndentation($tokens, $index);

                $previousTokenIndex = $openBraceIndex;
                do {
                    $previousTokenIndex = $tokens->getPrevMeaningfulToken($previousTokenIndex);
                } while ($tokens[$previousTokenIndex]->isGivenKind([CT::T_TYPE_COLON, T_STRING, T_NS_SEPARATOR, CT::T_ARRAY_TYPEHINT]));

                if ($tokens[$previousTokenIndex]->equals(')')) {
                    if ($tokens[--$previousTokenIndex]->isComment()) {
                        --$previousTokenIndex;
                    }
                    if (
                        $tokens[$previousTokenIndex]->isWhitespace()
                        && 1 === Preg::match('/\R/', $tokens[$previousTokenIndex]->getContent())
                    ) {
                        $whitespace = ' ';
                    }
                }
            }

            $moveBraceToIndex = null;

            if (' ' === $whitespace) {
                $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($openBraceIndex);
                for ($indexBeforeOpenBrace = $openBraceIndex - 1; $indexBeforeOpenBrace > $previousMeaningfulIndex; --$indexBeforeOpenBrace) {
                    if (!$tokens[$indexBeforeOpenBrace]->isComment()) {
                        continue;
                    }

                    $tokenBeforeOpenBrace = $tokens[--$indexBeforeOpenBrace];
                    if ($tokenBeforeOpenBrace->isWhitespace()) {
                        $moveBraceToIndex = $indexBeforeOpenBrace;
                    } elseif ($indexBeforeOpenBrace === $previousMeaningfulIndex) {
                        $moveBraceToIndex = $previousMeaningfulIndex + 1;
                    }
                }
            } elseif (!$tokens[$openBraceIndex - 1]->isWhitespace() || !Preg::match('/\R/', $tokens[$openBraceIndex - 1]->getContent())) {
                for ($indexAfterOpenBrace = $openBraceIndex + 1; $indexAfterOpenBrace < $closeBraceIndex; ++$indexAfterOpenBrace) {
                    if ($tokens[$indexAfterOpenBrace]->isWhitespace() && Preg::match('/\R/', $tokens[$indexAfterOpenBrace]->getContent())) {
                        break;
                    }

                    if ($tokens[$indexAfterOpenBrace]->isComment() && 0 !== strpos($tokens[$indexAfterOpenBrace]->getContent(), '/*')) {
                        $moveBraceToIndex = $indexAfterOpenBrace + 1;
                    }
                }
            }

            if (null !== $moveBraceToIndex) {
                /** @var Token $movedToken */
                $movedToken = clone $tokens[$openBraceIndex];

                $delta = $openBraceIndex < $moveBraceToIndex ? 1 : -1;

                if ($tokens[$openBraceIndex + $delta]->isWhitespace()) {
                    if (1 === $delta) {
                        $tokens->clearAt($openBraceIndex - 1);
                    } elseif ($tokens[$openBraceIndex - 1]->isWhitespace() && Preg::match('/\R/', $tokens[$openBraceIndex - 1]->getContent())) {
                        $content = Preg::replace('/^(\h*?\R)?\h*/', '', $tokens[$openBraceIndex + 1]->getContent());
                        if ('' !== $content) {
                            $tokens[$openBraceIndex + 1] = new Token([T_WHITESPACE, $content]);
                        } else {
                            $tokens->clearAt($openBraceIndex + 1);
                        }
                    }
                }

                for (; $openBraceIndex !== $moveBraceToIndex; $openBraceIndex += $delta) {
                    /** @var Token $siblingToken */
                    $siblingToken = $tokens[$openBraceIndex + $delta];
                    $tokens[$openBraceIndex] = $siblingToken;
                }

                $tokens[$openBraceIndex] = $movedToken;

                $openBraceIndex = $moveBraceToIndex;
            }

            if ($tokens->ensureWhitespaceAtIndex($openBraceIndex - 1, 1, $whitespace)) {
                ++$closeBraceIndex;
                if (null !== $allowSingleLineUntil) {
                    ++$allowSingleLineUntil;
                }
            }

            if (
                !$addNewlinesInsideBraces
                || $tokens[$tokens->getPrevMeaningfulToken($closeBraceIndex)]->isGivenKind(T_OPEN_TAG)
            ) {
                continue;
            }

            for ($prevIndex = $closeBraceIndex - 1; $tokens->isEmptyAt($prevIndex); --$prevIndex);

            $prevToken = $tokens[$prevIndex];
            if ($prevToken->isWhitespace() && 1 === Preg::match('/\R/', $prevToken->getContent())) {
                continue;
            }

            if ($addNewlinesInsideBraces) {
                $whitespace = $this->whitespacesConfig->getLineEnding().$this->getLineIndentation($tokens, $openBraceIndex);
            } else {
                $whitespace = ' ';
            }

            $tokens->ensureWhitespaceAtIndex($prevIndex, 1, $whitespace);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('control_structures_opening_brace', 'the position of the opening brace of control structures body.'))
                ->setAllowedValues([self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END, self::SAME_LINE])
                ->setDefault(self::SAME_LINE)
                ->getOption(),
            (new FixerOptionBuilder('functions_opening_brace', 'the position of the opening brace of functions body.'))
                ->setAllowedValues([self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END, self::SAME_LINE])
                ->setDefault(self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END)
                ->getOption(),
            (new FixerOptionBuilder('anonymous_functions_opening_brace', 'the position of the opening brace of anonymous functions body.'))
                ->setAllowedValues([self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END, self::SAME_LINE])
                ->setDefault(self::SAME_LINE)
                ->getOption(),
            (new FixerOptionBuilder('classes_opening_brace', 'the position of the opening brace of classes body.'))
                ->setAllowedValues([self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END, self::SAME_LINE])
                ->setDefault(self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END)
                ->getOption(),
            (new FixerOptionBuilder('anonymous_classes_opening_brace', 'the position of the opening brace of anonymous classes body.'))
                ->setAllowedValues([self::NEXT_LINE_UNLESS_NEWLINE_AT_SIGNATURE_END, self::SAME_LINE])
                ->setDefault(self::SAME_LINE)
                ->getOption(),
            (new FixerOptionBuilder('allow_single_line_empty_anonymous_classes', 'allow anonymous classes to have opening and closing braces on the same line.'))
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
            (new FixerOptionBuilder('allow_single_line_anonymous_functions', 'allow anonymous functions to have opening and closing braces on the same line.'))
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
        ]);
    }

    /**
     * @param int $structureTokenIndex
     *
     * @return int
     */
    private function findParenthesisEnd(Tokens $tokens, $structureTokenIndex)
    {
        $nextIndex = $tokens->getNextMeaningfulToken($structureTokenIndex);
        $nextToken = $tokens[$nextIndex];

        // return if next token is not opening parenthesis
        if (!$nextToken->equals('(')) {
            return $structureTokenIndex;
        }

        return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextIndex);
    }

    private function isFollowedByNewLine(Tokens $tokens, $index)
    {
        for (++$index, $max = \count($tokens) - 1; $index < $max; ++$index) {
            $token = $tokens[$index];
            if (!$token->isComment()) {
                return $token->isWhitespace() && 1 === Preg::match('/\R/', $token->getContent());
            }
        }

        return false;
    }

    private function hasCommentOnSameLine(Tokens $tokens, $index)
    {
        $token = $tokens[$index + 1];

        if ($token->isWhitespace() && 1 !== Preg::match('/\R/', $token->getContent())) {
            $token = $tokens[$index + 2];
        }

        return $token->isComment();
    }

    private function getLineIndentation(Tokens $tokens, $index)
    {
        $newlineTokenIndex = $this->getPreviousNewlineTokenIndex($tokens, $index);

        if (null === $newlineTokenIndex) {
            return '';
        }

        return $this->extractIndent($this->computeNewLineContent($tokens, $newlineTokenIndex));
    }

    private function extractIndent($content)
    {
        if (Preg::match('/\R([\t ]*)[^\r\n]*$/D', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function getPreviousNewlineTokenIndex(Tokens $tokens, $index)
    {
        while ($index > 0) {
            $index = $tokens->getPrevTokenOfKind($index, [[T_WHITESPACE], [T_INLINE_HTML]]);

            if (null === $index) {
                break;
            }

            if ($this->isNewLineToken($tokens, $index)) {
                return $index;
            }
        }

        return null;
    }

    private function isNewLineToken(Tokens $tokens, $index)
    {
        if (!$tokens[$index]->equalsAny([[T_WHITESPACE], [T_INLINE_HTML]])) {
            return false;
        }

        return (bool) Preg::match('/\R/', $this->computeNewLineContent($tokens, $index));
    }

    private function computeNewLineContent(Tokens $tokens, $index)
    {
        $content = $tokens[$index]->getContent();

        if (0 !== $index && $tokens[$index - 1]->equalsAny([[T_OPEN_TAG], [T_CLOSE_TAG]])) {
            $content = Preg::replace('/\S/', '', $tokens[$index - 1]->getContent()).$content;
        }

        return $content;
    }
}
