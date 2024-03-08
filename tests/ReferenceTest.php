<?php

declare(strict_types=1);

use Empaphy\Repherence\Allocation;
use PHPUnit\Framework\TestCase;

class ReferenceTest extends TestCase
{
    public function testIsReferenceTo()
    {
        $a = 'foo';
        $b = &$a;
        $c = 'foo';

        $this->assertTrue(Allocation::isReferenceTo($a, $b));
        $this->assertFalse(Allocation::isReferenceTo($a, $c));
    }

    public function testFindReferenceTo()
    {
        $foo = 'foo';
        $bar = 'bar';
        $haystack[1] = &$foo;
        $haystack['two'] = &$bar;
        $haystack[] = 'baz';

        $this->assertSame(1,     Allocation::findReferenceTo($foo, $haystack));
        $this->assertSame('two', Allocation::findReferenceTo($bar, $haystack));
    }
}
