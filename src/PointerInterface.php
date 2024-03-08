<?php

declare(strict_types=1);

namespace Empaphy\Repherence;

/**
 * @template-extends \ArrayAccess<int, string>
 * @template-extends \Iterator<int, string>
 */
interface PointerInterface extends \ArrayAccess, \Iterator, \Countable
{
    public static function declare(int &$value = null): self;
}
