<?php

declare(strict_types=1);

namespace Empaphy\Repherence;

use ArrayAccess;
use Iterator;

/**
 * This class emulates a classical pointer in C/C++. (`char*`)
 *
 * @todo Implement {@see \Serializable}
 *
 * @implements ArrayAccess<int, string>
 * @implements Iterator<int, string>
 */
class Pointer implements PointerInterface
{
    public static int $referenceCheckValue = 0;

    public static ?int $nullReference = null;

    /**
     * The current position of PHP's array iteration pointer.
     */
    private int $position = 0;

    /**
     * A list of pointer references.
     *
     * Basically used to identify variables that are know to be actual pointers.
     *
     * @todo Investigate if using {@see \WeakMap} would be better.
     *
     * @var int[]
     */
    private static array $pointers = [];

    /**
     * @param  int|null  $address  A reference to the variable.
     */
    public function __construct(public ?int &$address)
    {
        if (! self::isPointer($address)) {
            self::$pointers[] = &$address;
        }
    }

    /**
     * Checks whether the provided address reference is a pointer.
     */
    public static function isPointer(int &$address): bool
    {
        foreach (self::$pointers as &$pointer) {
            if (Util::isReferenceTo($address, $pointer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allocates a new Pointer and returns a reference to the address.
     */
    public static function declare(int &$value = null): self
    {
        return new Pointer($value);
    }

    /**
     * Returns the current character.
     *
     * @return string The character at the current position.
     */
    #[\Override]
    public function current(): string
    {
        return $this->offsetGet($this->position);
    }

    /**
     * Returns the character at specified position.
     *
     * This method is executed when checking if position is {@see empty()}.
     *
     * @param  mixed  $offset  The offset to retrieve.
     * @return string The character at the specified offset.
     */
    #[\Override]
    public function offsetGet(mixed $offset): string
    {
        return $this->__toString()[$offset] ?? "\0";
    }

    /**
     * Moves the current position to the next character.
     *
     * > Note:
     * > This method is called after each `foreach` loop.
     *
     * @return void Any returned value is ignored.
     */
    #[\Override]
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Returns the position of the current character.
     *
     * @return int|null Returns position on success, or null on failure.
     */
    #[\Override]
    public function key(): ?int
    {
        return $this->position;
    }

    /**
     * Checks if the current position is valid.
     *
     * This method is called after {@see Ptr::rewind()} and {@see Ptr::next()}
     * to check if the current position is valid.
     *
     * @return bool The return value will be cast to bool and then evaluated.
     *              Returns {@see true} on success or {@see false} on failure.
     */
    #[\Override]
    public function valid(): bool
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Returns whether an offset exists.
     *
     * This method is executed when using {@see isset()} or {@see empty()} on a {@see Pointer}.
     *
     * > Note:
     * > When using {@see empty()}, {@see self::offsetGet()} will be called and
     * > checked if empty only if {@see self::offsetExists()} returns true.
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

        if ($offsetPosition >= $this->getFullStringLength()) {
            return false;
        }

        return $this->__toString()[$offset] !== "\0";
    }

    /**
     * Rewinds back to the first position of the {@see Ptr}.
     *
     * > Note:
     * > This is the _first_ method called when starting a `foreach` loop. It
     * > will _not_ be executed _after_ `foreach` loops.
     *
     * @return void Any returned value is ignored.
     */
    #[\Override]
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Unsets an offset.
     *
     * > Note:
     * > This method will _not_ be called when type-casting to `(unset)`.
     *
     * @param  int  $offset  The offset to unset.
     * @return void No value is returned.
     */
    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, "\0");
    }

    /**
     * Assigns a value to the specified position.
     *
     * @param  int     $offset  The offset to assign the value to.
     * @param  string  $value   The value to set.
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

        // If the offset is greater than the full string, add null bytes to the end of the $after string to compensate.
        if ($offsetPosition >= $this->getFullStringLength()) {
            $this->after .= str_repeat("\0", $offsetPosition - $this->getFullStringLength() + 1);
        }

        $fullString = substr_replace($this->getFullString(), $value, $offsetPosition);

        $this->before   = substr($fullString, 0, strlen($this->before));
        $this->original = substr($fullString, strlen($this->before), strlen($this->original));
        $this->after    = substr($fullString, strlen($this->before) + strlen($this->original));
    }

    public function count(): int
    {
        return strlen($this->__toString());
    }

    /**
     * Returns the characters in this character pointer as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $fullString = $this->dereference()->__toString();

        return substr($fullString, $this->getRelativeAddress());
    }

    /**
     * Dereferences the pointer and returns the {@see Allocation} it points to.
     */
    public function dereference(): Allocation
    {
        if (null === $this->address) {
            throw new \RuntimeException("The pointer is null.");
        }

        $allocation = Allocation::forAddress($this->address);
        if (null === $allocation) {
            throw new \RuntimeException("The address {$this->address} is not allocated.");
        }

        return $allocation;
    }

    private function getRelativeAddress(): int
    {
        return $this->address - self::getBaseAddress();
    }

    private static function getBaseAddress(): int
    {
        return 2 ** Allocation::BITS / 2;
    }

    private function getOffsetPosition(int $offset = 0): int
    {
        return $this->getRelativeAddress() + $offset;
    }
}
