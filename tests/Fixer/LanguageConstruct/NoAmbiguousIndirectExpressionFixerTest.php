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

namespace PhpCsFixer\Tests\Fixer\LanguageConstruct;

use PhpCsFixer\Test\AbstractFixerTestCase;

/**
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\LanguageConstruct\NoAmbiguousIndirectExpressionFixer
 */
class NoAmbiguousIndirectExpressionFixerTest extends AbstractFixerTestCase
{
    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @dataProvider getFixCases
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function getFixCases()
    {
        return [
            [
                '<?php echo ${$a};',
                '<?php echo $$a;',
            ],
        ];
    }

    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @dataProvider getFixPhp5Cases
     * @requires PHP <7.0
     */
    public function testFixPhp5($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function getFixPhp5Cases()
    {
        return [
            [
                '<?php echo ${$foo[\'bar\'][\'baz\']};',
                '<?php echo $$foo[\'bar\'][\'baz\'];',
            ],
            [
                '<?php echo $foo->{$bar[\'baz\']};',
                '<?php echo $foo->$bar[\'baz\'];',
            ],
            [
                '<?php echo $foo->{$bar[\'baz\']}();',
                '<?php echo $foo->$bar[\'baz\']();',
            ],
            [
                '<?php echo Foo::{$bar[\'baz\']}();',
                '<?php echo Foo::$bar[\'baz\']();',
            ],
        ];
    }

    /**
     * @param string      $expected
     * @param null|string $input
     *
     * @dataProvider getFixPhp7Cases
     * @requires PHP 7.0
     */
    public function testFixPhp7($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function getFixPhp7Cases()
    {
        return [
            [
                '<?php echo ($$foo)[\'bar\'][\'baz\'];',
                '<?php echo $$foo[\'bar\'][\'baz\'];',
            ],
            [
                '<?php echo ($foo->$bar)[\'baz\'];',
                '<?php echo $foo->$bar[\'baz\'];',
            ],
            [
                '<?php echo ($foo->$bar)[\'baz\']();',
                '<?php echo $foo->$bar[\'baz\']();',
            ],
            [
                '<?php echo (Foo::$bar)[\'baz\']();',
                '<?php echo Foo::$bar[\'baz\']();',
            ],
            [
                '<?php echo (Foo\Bar::$baz)[\'qux\']();',
                '<?php echo Foo\Bar::$baz[\'qux\']();',
            ],
            [
                '<?php echo (($$foo)[\'bar\'][\'baz\']);',
                '<?php echo ($$foo[\'bar\'][\'baz\']);',
            ],
        ];
    }
}
