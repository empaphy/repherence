<?php

declare(strict_types=1);

use Empaphy\Repherence\Pointer;
use Empaphy\Repherence\Allocation;

/**
 * Declares the provided variable as a pointer, and returns a {@see Pointer} instance.
 */
function •(int &$address = null): Pointer
{
    return Pointer::declare($address);
}

/**
 * Returns the address of a variable.
 *
 * This creates a {@see Allocation} to the variable in the progress, if it doesn't exist yet, and return's it's address.
 */
function ¶(&$var): int
{
    return Allocation::ensure($var)->getAddress();
}

/**
 * Allocates memory and returns a pointer to it.
 *
 * @param int $size
 * @return int
 */
function &malloc(int $size): int
{
    return Allocation::allocate($size);
}
