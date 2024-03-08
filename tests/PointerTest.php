<?php
declare(strict_types=1);

use Empaphy\Repherence\Pointer;
use PHPUnit\Framework\TestCase;

class PointerTest extends TestCase
{
    public function testConstructor()
    {
        $pointer = new Pointer($address);
        $this->assertNull($address);
    }

    public function testDeclare()
    {
        $pointer = Pointer::declare($address);
        $this->assertNull($address);
    }
}
