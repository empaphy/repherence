<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class helpersTest extends TestCase
{
    public function testPointer()
    {
        // char str[20] = "Hello, world!";
        $str = "Hello, world!";

        // char *ptr; // declare ptr as a char pointer
        •($ptr);

        // str = *ptr; // dereference ptr and assign it to str
        $str = •($ptr);

        // ptr = &str; // assign the address of str to ptr
        $ptr = ¶($str);

        // char *s;
        •($s);

        // char *s = foo; // foo is a char pointer
        •($s, "foo");
    }
}
