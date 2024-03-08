<?php

declare(strict_types=1);

namespace Empaphy\Repherence;

/**
 * Emulates the allocation of a string of bytes in memory.
 *
 * This makes it possible to simulate the `malloc()` function in C, and C pointers in general.
 */
class Allocation implements AllocationInterface
{
    /**
     * Check how many bits are used to represent an integer, and divide that by half. We do this, so we can split up
     * the bits into two parts: the identifier for the pointer, and the offset from the original reference.
     */
    public const BITS = PHP_INT_SIZE * 8 / 2;

    /**
     * Memory page address this allocation is allocated to.
     */
    public readonly int $page;

    /**
     * The current page index.
     *
     * Used to calculate the next page address.
     */
    private static int $pageIndex = 0;

    /**
     * A list of allocations.
     *
     * @var Allocation[]
     */
    private static array $allocations = [];

    /**
     * Keeps track of chars that have been placed before the reference.
     */
    private string $before;

    /**
     * Current position when iterating through the {@see Allocation} as an array.
     */
    private int $position = 0;

    /**
     * @param  string  $var  A reference to the original variable.
     */
    public function __construct(private string &$var = '')
    {
        $this->page = self::getNextPage();

        self::$allocations[] = $this;
    }

    /**
     * Returns the next page address.
     */
    private static function getNextPage(): int
    {
        // What we want to do is spread the addresses out, so we don't have to worry about collisions.
        // So let's say $bits = 31, then first we start at `floor((-1 + (2**$bits)) / 2)` = 1073741823. After that we want
        // to use the midway points above and below 1073741823. etc.

        $increment  = ++self::$pageIndex;
        $bitsPerInc = ceil(log(1 + $increment, 2));        // Calculate how many bits we need to represent the inc.
        $divisor    = 2 ** $bitsPerInc;                    // Calculate the divisor for the increment.
        $multiplier = 1 + ($increment - $divisor / 2) * 2; // Calculate the multiplier for the increment.

        return (int) (floor((-1 + (2 ** self::BITS - 1) / $divisor)) * $multiplier);
    }

    /**
     * Allocates memory and returns a pointer to it.
     */
    public static function &allocate(int $size): int
    {
        $var        = str_repeat("\0", $size);
        $allocation = new Allocation($var);
        Pointer::declare($pointer);
        $pointer = $allocation->getAddress();

        return $pointer;
    }

    /**
     * Returns the full starting address of this allocation.
     */
    public function getAddress(): int
    {
        return $this->page << self::BITS;
    }

    /**
     * Ensures an {@see Allocation} exists for the provided variable, and returns it.
     */
    public static function ensure(string &$var): Allocation
    {
        $allocation = self::find($var);
        if (null === $allocation) {
            $allocation = new Allocation($var);
        }

        return $allocation;
    }

    /**
     * Finds an {@see Allocation} for the provided variable.
     */
    private static function find(string &$var): ?Allocation
    {
        foreach (self::$allocations as $allocation) {
            if (Util::isReferenceTo($var, $allocation->var)) {
                return $allocation;
            }
        }

        return null;
    }

    /**
     * Return the current byte.
     *
     * @return string Will return a byte.
     */
    public function current(): string
    {
        return $this->offsetGet($this->position);
    }

    /**
     * Returns the byte at specified offset.
     *
     * This method is executed when checking if offset is {@see empty()}.
     *
     * > **Note**:
     * > It's possible for implementations of this method to return by reference. This makes indirect modifications
     * > to the overloaded array dimensions of ArrayAccess objects possible.
     * >
     * > A direct modification is one that replaces completely the value of the array dimension, as in `$obj[6] = 7`.
     * > An indirect modification, on the other hand, only changes part of the dimension, or attempts to assign the
     * > dimension by reference to another variable, as in `$obj[6][7] = 7` or `$var =& $obj[6]`. Increments with `++`
     * > and decrements with `--` are also implemented in a way that requires indirect modification.
     * >
     * > While direct modification triggers a call to {@see self::offsetSet()}, indirect modification triggers a call
     * > to {@see self::offsetGet()}. In that case, the implementation of {@see self::offsetGet()} must be able to
     * > return by reference, otherwise an `E_NOTICE` message is raised.
     *
     * @param  int  $offset  The offset to retrieve.
     * @return string The byte at the specified offset.
     */
    #[\Override]
    public function offsetGet(mixed $offset): string
    {
        $fullString     = $this->__toString();
        $offsetPosition = $this->getOffsetPosition($offset);

        return $fullString[$offsetPosition] ?? "\0";
    }

    /**
     * Returns the bytes in this {@see Allocation} as a string.
     */
    public function __toString(): string
    {
        return $this->before . $this->var;
    }

    /**
     * Returns the position in the full string for the provided offset.
     */
    private function getOffsetPosition(int $offset = 0): int
    {
        return strlen($this->before) + $offset;
    }

    /**
     * Returns the key of the current byte.
     *
     * @return int|null Returns `int` on success, or `null` on failure.
     */
    public function key(): ?int
    {
        return $this->position;
    }

    /**
     * Moves the current position to the next byte.
     *
     * This method is called after each `foreach` loop.
     *
     * @return void Any returned value is ignored.
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Rewinds back to the first character.
     *
     * This is the _first_ method called when starting a `foreach` loop.
     * It will _not_ be executed _after_ `foreach` loops.
     *
     * @return void Any returned value is ignored.
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    public static function forAddress(int $address): ?Allocation
    {
        foreach (self::$allocations as $allocation) {
            if (self::pageForAddress($address) === $allocation->page) {
                return $allocation;
            }
        }

        return null;
    }

    public static function pageForAddress(int $address): int
    {
        return $address >> self::BITS;
    }

    /**
     * Sets bytes at the specified offset.
     *
     * > **Note**:
     * > The `offset` parameter will be set to `null` if another value is not available, like in the following example.
     * >
     * > ```php
     * > <?php
     * > $arrayaccess[] = "first value";
     * > $arrayaccess[] = "second value";
     * > print_r($arrayaccess);
     * > ?>
     * > ```
     * >
     * > The above example will output:
     * >
     * > ```
     * > Array
     * > (
     * >     [0] => first value
     * >     [1] => second value
     * > )
     *
     * > **Note**:
     * > This function is not called in assignments by reference and otherwise indirect changes to array dimensions
     * > overloaded with {@see ArrayAccess} (indirect in the sense they are made not by changing the dimension directly,
     * > but by changing a sub-dimension or sub-property or assigning the array dimension by reference to another
     * > variable). Instead, {@see self::offsetGet()} is called. The operation will only be successful if that method
     * > returns by reference.
     *
     * @param  int     $offset  The offset to set the byte at.
     * @param  string  $value   The bytes to set.
     * @return void No value is returned.
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offsetPosition = $this->getOffsetPosition($offset);

        // If the offset is negative, add null bytes to the beginning of the $before string to compensate.
        if ($offsetPosition < 0) {
            $this->before   = str_repeat("\0", -$offsetPosition) . $this->before;
            $offsetPosition = 0;
            assert('$this->getOffsetPosition($offset) === $offsetPosition');
        }

//        // If the offset is greater than the full string, add null bytes to the end of the $after string to compensate.
//        if ($offsetPosition >= $this->count()) {
//            $this->after .= str_repeat("\0", $offsetPosition - $this->count() + 1);
//        }

        $fullString = substr_replace($this->__toString(), $value, $offsetPosition);

        $this->before = substr($fullString, 0, strlen($this->before));
        $this->var    = substr($fullString, strlen($this->before));
        // TODO: perhaps `trim()` null characters from the start of `$this->before`?
//        $this->var    = substr($fullString, strlen($this->before), strlen($this->var));
//        $this->after  = substr($fullString, strlen($this->before) + strlen($this->var));
    }

    /**
     * Checks if current position is valid.
     *
     * This method is called after {@see self::rewind()} and {@see self::next()}
     * to check if the current position is valid.
     *
     * @return bool The return value will be cast to bool and then evaluated.
     *              Returns `true` on success or `false` on failure.
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Unsets an offset.
     *
     * > **Note**:
     * > This method will _not_ be called when type-casting to `(unset)`.
     *
     * @param  int  $offset
     * @return void No value is returned.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, "\0");
    }

    /**
     * Returns whether an offset exists.
     *
     * This method is executed when using {@see isset()} or {@see empty()} on an {@see Allocation}.
     *
     * > **Note**:
     * > When using {@see empty()}, {@see self::offsetGet()} will be called and
     * > checked if empty only if {@see self::offsetExists()} returns `true`.
     *
     * @param  int  $offset  An offset to check for.
     * @return bool Returns `true` on success or `false` on failure.
     *                       The return value will be cast to bool if non-boolean was returned.
     */
    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        $offsetPosition = $this->getOffsetPosition($offset);

        if ($offsetPosition < 0) {
            return false;
        }

        if ($offsetPosition >= $this->count()) {
            return false;
        }

        return $this->offsetGet($offset) !== "\0";
    }

    /**
     * Count elements of an object.
     *
     * This method is executed when using the {@see count()} function on an object implementing {@see Countable}.
     *
     * @return int The custom count. The return value is cast to an `int`.
     */
    public function count(): int
    {
        return strlen($this->before) + strlen($this->var);
    }
}
