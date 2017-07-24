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

namespace PhpCsFixer\Fixer\LanguageConstruct;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class NoAmbiguousIndirectExpressionFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Makes indirect expressions evaluation order explicit.',
            [
                new CodeSample('<?php echo $$a;'),
                new CodeSample('$$foo[\'bar\'][\'baz\']'),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_VARIABLE);
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($index = count($tokens) - 1; $index > 0; --$index) {
            if (!$tokens[$index]->isGivenKind(T_VARIABLE)) {
                continue;
            }

            if (PHP_VERSION_ID < 70000) {
                $this->wrapWithCurlyBraces($tokens, $index);

                continue;
            }

            $this->wrapWithParentheses($tokens, $index);
        }
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     */
    private function wrapWithCurlyBraces(Tokens $tokens, $index)
    {
        $prevToken = $tokens[$tokens->getPrevMeaningfulToken($index)];
        if (!$prevToken->equals('$') && !$prevToken->isGivenKind([T_OBJECT_OPERATOR, T_DOUBLE_COLON])) {
            return;
        }

        $endIndex = $index;

        while (true) {
            $nextIndex = $tokens->getNextMeaningfulToken($endIndex);
            $nextToken = $tokens[$nextIndex];
            if (!$nextToken->equals('[')) {
                break;
            }

            $endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE, $nextIndex);
        }

        if ($prevToken->isGivenKind(T_OBJECT_OPERATOR)) {
            $opening = [CT::T_DYNAMIC_PROP_BRACE_OPEN, '{'];
            $closing = [CT::T_DYNAMIC_PROP_BRACE_CLOSE, '}'];
        } elseif ($prevToken->isGivenKind(T_DOUBLE_COLON)) {
            $opening = '{';
            $closing = '}';
        } else {
            $opening = [CT::T_DYNAMIC_VAR_BRACE_OPEN, '{'];
            $closing = [CT::T_DYNAMIC_VAR_BRACE_CLOSE, '}'];
        }

        $tokens->insertAt($endIndex + 1, new Token($closing));
        $tokens->insertAt($index, new Token($opening));
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     */
    private function wrapWithParentheses(Tokens $tokens, $index)
    {
        $startIndex = null;
        $prevIndex = $tokens->getPrevMeaningfulToken($index);
        $prevToken = $tokens[$prevIndex];

        if ($prevToken->equals('$')) {
            $startIndex = $prevIndex;
        } elseif ($prevToken->isGivenKind([T_OBJECT_OPERATOR, T_DOUBLE_COLON])) {
            $startIndex = $tokens->getPrevMeaningfulToken($prevIndex);

            if ($tokens[$startIndex]->isGivenKind(T_STRING)) {
                while (true) {
                    $prevIndex = $tokens->getPrevMeaningfulToken($startIndex);
                    if (!$tokens[$prevIndex]->isGivenKind(T_NS_SEPARATOR)) {
                        break;
                    }

                    $prevIndex = $tokens->getPrevMeaningfulToken($prevIndex);
                    if (!$tokens[$prevIndex]->isGivenKind(T_STRING)) {
                        break;
                    }

                    $startIndex = $prevIndex;
                }
            }
        }

        if (null === $startIndex) {
            return;
        }

        if ($tokens[$startIndex - 1]->equals('(') && $tokens[$index + 1]->equals(')')) {
            return;
        }

        $tokens->insertAt($index + 1, new Token(')'));
        $tokens->insertAt($startIndex, new Token('('));
    }
}
