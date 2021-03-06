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

namespace PhpCsFixer\Tests\Tokenizer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author SpacePossum
 *
 * @internal
 */
final class TokensTest extends \PHPUnit_Framework_TestCase
{
    public function testReadFromCacheAfterClearing()
    {
        $code = '<?php echo 1;';
        $tokens = Tokens::fromCode($code);

        $countBefore = $tokens->count();

        for ($i = 0; $i < $countBefore; ++$i) {
            $tokens[$i]->clear();
        }

        $tokens = Tokens::fromCode($code);

        $this->assertSame($countBefore, $tokens->count());
    }

    /**
     * @dataProvider provideFindSequence
     */
    public function testFindSequence($source, $expected, array $params)
    {
        $tokens = Tokens::fromCode($source);

        $this->assertEqualsTokensArray($expected, call_user_func_array(array($tokens, 'findSequence'), $params));
    }

    public function provideFindSequence()
    {
        return array(
            array(
                '<?php $x = 1;',
                null,
                array(array(
                    new Token(';'),
                ), 7),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$y'),
                )),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$x'),
                )),
            ),

            array(
                '<?php $x = 1;',
                array(
                    3 => new Token('='),
                    5 => new Token(array(T_LNUMBER, '1')),
                    6 => new Token(';'),
                ),
                array(array(
                    '=',
                    array(T_LNUMBER, '1'),
                    ';',
                )),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$x'),
                ), 0),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$x'),
                ), 1),
            ),

            array(
                '<?php $x = 1;',
                array(
                    3 => new Token('='),
                    5 => new Token(array(T_LNUMBER, '1')),
                    6 => new Token(';'),
                ),
                array(array(
                    '=',
                    array(T_LNUMBER, '1'),
                    ';',
                ), 3, 6),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    '=',
                    array(T_LNUMBER, '1'),
                    ';',
                ), 4, 6),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    '=',
                    array(T_LNUMBER, '1'),
                    ';',
                ), 3, 5),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$x'),
                ), 0, 1, true),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, true),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, array(true, true)),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, false),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, array(true, false)),
            ),

            array(
                '<?php $x = 1;',
                array(
                    0 => new Token(array(T_OPEN_TAG, '<?php ')),
                    1 => new Token(array(T_VARIABLE, '$x')),
                ),
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, array(1 => false)),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_OPEN_TAG),
                    array(T_VARIABLE, '$X'),
                ), 0, 1, array(2 => false)),
            ),

            array(
                '<?php $x = 1;',
                null,
                array(array(
                    array(T_VARIABLE, '$X'),
                    '=',
                ), 0, 10),
            ),
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider provideFindSequenceExceptions
     */
    public function testFindSequenceException($message, $sequence)
    {
        $tokens = Tokens::fromCode('<?php $x = 1;');
        try {
            $tokens->findSequence($sequence);
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
            throw $e;
        }
    }

    public function provideFindSequenceExceptions()
    {
        $emptyToken = new Token('!');
        $emptyToken->clear();

        return array(
            array('Invalid sequence.', array()),
            array('Non-meaningful token at position: 0.', array(
                array(T_WHITESPACE, '   '),
            )),
            array('Non-meaningful token at position: 1.', array(
                '{', array(T_COMMENT, '// Foo'), '}',
            )),
            array('Non-meaningful token at position: 2.', array(
                '{', '!', $emptyToken, '}',
            )),
        );
    }

    public function testClearRange()
    {
        $source = <<<'PHP'
<?php
class FooBar
{
    public function foo()
    {
        return 'bar';
    }

    public function bar()
    {
        return 'foo';
    }
}
PHP;

        $tokens = Tokens::fromCode($source);
        list($fooIndex, $barIndex) = array_keys($tokens->findGivenKind(T_PUBLIC));

        $tokens->clearRange($fooIndex, $barIndex - 1);

        $newPublicIndexes = array_keys($tokens->findGivenKind(T_PUBLIC));
        $this->assertSame($barIndex, reset($newPublicIndexes));

        for ($i = $fooIndex; $i < $barIndex; ++$i) {
            $this->assertTrue($tokens[$i]->isWhitespace());
        }
    }

    /**
     * @dataProvider provideMonolithicPhpDetection
     *
     * @param string $source
     * @param bool   $monolithic
     */
    public function testMonolithicPhpDetection($source, $monolithic)
    {
        $tokens = Tokens::fromCode($source);
        $this->assertSame($monolithic, $tokens->isMonolithicPhp());
    }

    public function provideMonolithicPhpDetection()
    {
        return array(
            array("<?php\n", true),
            array("<?php\n?>", true),
            array('', false),
            array(' ', false),
            array("#!/usr/bin/env php\n<?php\n", false),
            array(" <?php\n", false),
            array("<?php\n?> ", false),
            array("<?php\n?><?php\n", false),
        );
    }

    /**
     * @dataProvider provideShortOpenTagMonolithicPhpDetection
     *
     * @param string $source
     * @param bool   $monolithic
     */
    public function testShortOpenTagMonolithicPhpDetection($source, $monolithic)
    {
        /*
         * short_open_tag setting is ignored by HHVM
         * @see https://github.com/facebook/hhvm/issues/4758
         */
        if (!ini_get('short_open_tag') && !defined('HHVM_VERSION')) {
            // Short open tag is parsed as T_INLINE_HTML
            $monolithic = false;
        }

        $tokens = Tokens::fromCode($source);
        $this->assertSame($monolithic, $tokens->isMonolithicPhp());
    }

    public function provideShortOpenTagMonolithicPhpDetection()
    {
        return array(
            array("<?\n", true),
            array("<?\n?>", true),
            array(" <?\n", false),
            array("<?\n?> ", false),
            array("<?\n?><?\n", false),
            array("<?\n?><?php\n", false),
            array("<?\n?><?=' ';\n", false),
            array("<?php\n?><?\n", false),
            array("<?=' '\n?><?\n", false),
        );
    }

    /**
     * @dataProvider provideShortOpenTagEchoMonolithicPhpDetection
     *
     * @param string $source
     * @param bool   $monolithic
     */
    public function testShortOpenTagEchoMonolithicPhpDetection($source, $monolithic)
    {
        /*
         * short_open_tag setting is ignored by HHVM
         * @see https://github.com/facebook/hhvm/issues/4758
         */
        if (!ini_get('short_open_tag') && 50400 > PHP_VERSION_ID && !defined('HHVM_VERSION')) {
            // Short open tag echo is parsed as T_INLINE_HTML
            $monolithic = false;
        }

        $tokens = Tokens::fromCode($source);
        $this->assertSame($monolithic, $tokens->isMonolithicPhp());
    }

    public function provideShortOpenTagEchoMonolithicPhpDetection()
    {
        return array(
            array("<?=' ';\n", true),
            array("<?=' '?>", true),
            array(" <?=' ';\n", false),
            array("<?=' '?> ", false),
            array("<?php\n?><?=' ';\n", false),
            array("<?=' '\n?><?php\n", false),
            array("<?=' '\n?><?=' ';\n", false),
        );
    }

    public function testTokenKindsFound()
    {
        $code = <<<'EOF'
<?php

class Foo
{
    public $foo;
}

if (!function_exists('bar')) {
    function bar()
    {
        return 'bar';
    }
}
EOF;

        $tokens = Tokens::fromCode($code);

        $this->assertTrue($tokens->isTokenKindFound(T_CLASS));
        $this->assertTrue($tokens->isTokenKindFound(T_RETURN));
        $this->assertFalse($tokens->isTokenKindFound(T_INTERFACE));
        $this->assertFalse($tokens->isTokenKindFound(T_ARRAY));

        $this->assertTrue($tokens->isAllTokenKindsFound(array(T_CLASS, T_RETURN)));
        $this->assertFalse($tokens->isAllTokenKindsFound(array(T_CLASS, T_INTERFACE)));

        $this->assertTrue($tokens->isAnyTokenKindsFound(array(T_CLASS, T_RETURN)));
        $this->assertTrue($tokens->isAnyTokenKindsFound(array(T_CLASS, T_INTERFACE)));
        $this->assertFalse($tokens->isAnyTokenKindsFound(array(T_INTERFACE, T_ARRAY)));
    }

    public function testFindGivenKind()
    {
        $source = <<<'PHP'
<?php
class FooBar
{
    public function foo()
    {
        return 'bar';
    }

    public function bar()
    {
        return 'foo';
    }
}
PHP;
        $tokens = Tokens::fromCode($source);
        /** @var Token[] $found */
        $found = $tokens->findGivenKind(T_CLASS);
        $this->assertInternalType('array', $found);
        $this->assertCount(1, $found);
        $this->assertArrayHasKey(1, $found);
        $this->assertSame(T_CLASS, $found[1]->getId());

        /** @var array $found */
        $found = $tokens->findGivenKind(array(T_CLASS, T_FUNCTION));
        $this->assertCount(2, $found);
        $this->assertArrayHasKey(T_CLASS, $found);
        $this->assertInternalType('array', $found[T_CLASS]);
        $this->assertCount(1, $found[T_CLASS]);
        $this->assertArrayHasKey(1, $found[T_CLASS]);
        $this->assertSame(T_CLASS, $found[T_CLASS][1]->getId());

        $this->assertArrayHasKey(T_FUNCTION, $found);
        $this->assertInternalType('array', $found[T_FUNCTION]);
        $this->assertCount(2, $found[T_FUNCTION]);
        $this->assertArrayHasKey(9, $found[T_FUNCTION]);
        $this->assertSame(T_FUNCTION, $found[T_FUNCTION][9]->getId());
        $this->assertArrayHasKey(26, $found[T_FUNCTION]);
        $this->assertSame(T_FUNCTION, $found[T_FUNCTION][26]->getId());

        // test offset and limits of the search
        $found = $tokens->findGivenKind(array(T_CLASS, T_FUNCTION), 10);
        $this->assertCount(0, $found[T_CLASS]);
        $this->assertCount(1, $found[T_FUNCTION]);
        $this->assertArrayHasKey(26, $found[T_FUNCTION]);

        $found = $tokens->findGivenKind(array(T_CLASS, T_FUNCTION), 2, 10);
        $this->assertCount(0, $found[T_CLASS]);
        $this->assertCount(1, $found[T_FUNCTION]);
        $this->assertArrayHasKey(9, $found[T_FUNCTION]);
    }

    /**
     * @param string  $source
     * @param Token[] $expected tokens
     * @param int[]   $indexes  to clear
     *
     * @dataProvider getClearTokenAndMergeSurroundingWhitespaceCases
     */
    public function testClearTokenAndMergeSurroundingWhitespace($source, array $indexes, array $expected)
    {
        $this->doTestClearTokens($source, $indexes, $expected);
        if (count($indexes) > 1) {
            $this->doTestClearTokens($source, array_reverse($indexes), $expected);
        }
    }

    public function getClearTokenAndMergeSurroundingWhitespaceCases()
    {
        $clearToken = new Token(array(null, ''));
        $clearToken->clear();

        return array(
            array(
                '<?php if($a){}else{}',
                array(7, 8, 9),
                array(
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(array(T_IF, 'if')),
                    new Token('('),
                    new Token(array(T_VARIABLE, '$a')),
                    new Token(')'),
                    new Token('{'),
                    new Token('}'),
                    $clearToken,
                    $clearToken,
                    $clearToken,
                ),
            ),
            array(
                '<?php $a;/**/;',
                array(2),
                array(
                    // <?php $a /**/;
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(array(T_VARIABLE, '$a')),
                    $clearToken,
                    new Token(array(T_COMMENT, '/**/')),
                    new Token(';'),
                ),
            ),
            array(
                '<?php ; ; ;',
                array(3),
                array(
                    // <?php ;  ;
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(';'),
                    new Token(array(T_WHITESPACE, '  ')),
                    $clearToken,
                    $clearToken,
                    new Token(';'),
                ),
            ),
            array(
                '<?php ; ; ;',
                array(1, 5),
                array(
                    // <?php  ;
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(array(T_WHITESPACE, ' ')),
                    $clearToken,
                    new Token(';'),
                    new Token(array(T_WHITESPACE, ' ')),
                    $clearToken,
                ),
            ),
            array(
                '<?php ; ; ;',
                array(1, 3),
                array(
                    // <?php   ;
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(array(T_WHITESPACE, '  ')),
                    $clearToken,
                    $clearToken,
                    $clearToken,
                    new Token(';'),
                ),
            ),
            array(
                '<?php ; ; ;',
                array(1),
                array(
                    // <?php  ; ;
                    new Token(array(T_OPEN_TAG, '<?php ')),
                    new Token(array(T_WHITESPACE, ' ')),
                    $clearToken,
                    new Token(';'),
                    new Token(array(T_WHITESPACE, ' ')),
                    new Token(';'),
                ),
            ),
        );
    }

    /**
     * @dataProvider provideTokenOfKindSiblingCases
     */
    public function testTokenOfKindSibling(
        $expectedIndex,
        $direction,
        $index,
        array $findTokens,
        $caseSensitive = true
    ) {
        $source =
            '<?php
                $a = function ($b) {
                    return $b;
                };

                echo $a(1);
                // test
                return 123;';

        Tokens::clearCache();
        $tokens = Tokens::fromCode($source);
        if (1 === $direction) {
            $this->assertSame($expectedIndex, $tokens->getNextTokenOfKind($index, $findTokens, $caseSensitive));
        } else {
            $this->assertSame($expectedIndex, $tokens->getPrevTokenOfKind($index, $findTokens, $caseSensitive));
        }

        $this->assertSame($expectedIndex, $tokens->getTokenOfKindSibling($index, $direction, $findTokens, $caseSensitive));
    }

    public function provideTokenOfKindSiblingCases()
    {
        return array(
            // find next cases
            array(
                35, 1, 34, array(';'),
            ),
            array(
                14, 1, 0, array(array(T_RETURN)),
            ),
            array(
                32, 1, 14, array(array(T_RETURN)),
            ),
            array(
                6, 1, 0, array(array(T_RETURN), array(T_FUNCTION)),
            ),
            // find previous cases
            array(
                14, -1, 32, array(array(T_RETURN), array(T_FUNCTION)),
            ),
            array(
                6, -1, 7, array(array(T_FUNCTION)),
            ),
            array(
                null, -1, 6, array(array(T_FUNCTION)),
            ),
        );
    }

    /**
     * @param int    $expectedIndex
     * @param string $source
     * @param int    $type
     * @param int    $searchIndex
     *
     * @dataProvider provideFindBlockEndCases
     */
    public function testFindBlockEnd($expectedIndex, $source, $type, $searchIndex)
    {
        Tokens::clearCache();
        $tokens = Tokens::fromCode($source);

        $this->assertSame($expectedIndex, $tokens->findBlockEnd($type, $searchIndex, true));
        $this->assertSame($searchIndex, $tokens->findBlockEnd($type, $expectedIndex, false));

        $detectedType = Tokens::detectBlockType($tokens[$searchIndex]);
        $this->assertInternalType('array', $detectedType);
        $this->assertArrayHasKey('type', $detectedType);
        $this->assertArrayHasKey('isStart', $detectedType);
        $this->assertSame($type, $detectedType['type']);
        $this->assertTrue($detectedType['isStart']);

        $detectedType = Tokens::detectBlockType($tokens[$expectedIndex]);
        $this->assertInternalType('array', $detectedType);
        $this->assertArrayHasKey('type', $detectedType);
        $this->assertArrayHasKey('isStart', $detectedType);
        $this->assertSame($type, $detectedType['type']);
        $this->assertFalse($detectedType['isStart']);
    }

    public function provideFindBlockEndCases()
    {
        return array(
            array(4, '<?php ${$bar};', Tokens::BLOCK_TYPE_DYNAMIC_VAR_BRACE, 2),
            array(4, '<?php test(1);', Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, 2),
            array(4, '<?php $a{1};', Tokens::BLOCK_TYPE_ARRAY_INDEX_CURLY_BRACE, 2),
            array(4, '<?php $a[1];', Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE, 2),
            array(6, '<?php [1, "foo"];', Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, 1),
            array(5, '<?php $foo->{$bar};', Tokens::BLOCK_TYPE_DYNAMIC_PROP_BRACE, 3),
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Invalid param type: -1\.$/
     */
    public function testFindBlockEndInvalidType()
    {
        Tokens::clearCache();
        $tokens = Tokens::fromCode('<?php ');
        $tokens->findBlockEnd(-1, 0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Invalid param \$startIndex - not a proper block start\.$/
     */
    public function testFindBlockEndInvalidStart()
    {
        Tokens::clearCache();
        $tokens = Tokens::fromCode('<?php ');
        $tokens->findBlockEnd(Tokens::BLOCK_TYPE_DYNAMIC_VAR_BRACE, 0);
    }

    /**
     * @param Token[]|null $expected
     * @param Token[]|null $input
     */
    private function assertEqualsTokensArray(array $expected = null, array $input = null)
    {
        if (null === $expected) {
            $this->assertNull($input);

            return;
        } elseif (null === $input) {
            $this->fail('While "input" is <null>, "expected" is not.');
        }

        $this->assertSame(array_keys($expected), array_keys($input), 'Both arrays need to have same keys.');

        foreach ($expected as $index => $expectedToken) {
            $this->assertTrue(
                $expectedToken->equals($input[$index]),
                sprintf('The token at index %d should be %s, got %s', $index, $expectedToken->toJson(), $input[$index]->toJson())
            );
        }
    }

    /**
     * @param string  $source
     * @param int[]   $indexes
     * @param Token[] $expected
     */
    private function doTestClearTokens($source, array $indexes, array $expected)
    {
        Tokens::clearCache();
        $tokens = Tokens::fromCode($source);
        foreach ($indexes as $index) {
            $tokens->clearTokenAndMergeSurroundingWhitespace($index);
        }

        $this->assertSame(count($expected), $tokens->count());
        foreach ($expected as $index => $expectedToken) {
            $token = $tokens[$index];
            $expectedPrototype = $expectedToken->getPrototype();
            if (is_array($expectedPrototype)) {
                unset($expectedPrototype[2]); // don't compare token lines as our token mutations don't deal with line numbers
            }

            $this->assertTrue($token->equals($expectedPrototype), sprintf('The token at index %d should be %s, got %s', $index, json_encode($expectedPrototype), $token->toJson()));
        }
    }
}
