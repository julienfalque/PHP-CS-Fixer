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

namespace PhpCsFixer\Tests\Fixer\ArrayNotation;

use PhpCsFixer\Test\AbstractFixerTestCase;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
final class NormalizeIndexBraceFixerTest extends AbstractFixerTestCase
{
    /**
     * @dataProvider provideFixCases
     */
    public function testFix($expected, $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function provideFixCases()
    {
        return array(
            array(
                '<?php echo $arr[$index];',
                '<?php echo $arr{$index};',
            ),
            array(
                '<?php echo $nestedArray[$index][$index2][$index3][$index4];',
                '<?php echo $nestedArray{$index}{$index2}[$index3]{$index4};',
            ),
            array(
                '<?php echo $array[0]->foo . $collection->items[1]->property;',
                '<?php echo $array{0}->foo . $collection->items{1}->property;',
            ),
        );
    }
}
