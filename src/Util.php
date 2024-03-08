<?php
declare(strict_types=1);

namespace Empaphy\Repherence;

class Util
{
    private const REFERENCE_CHECK_STRING_VALUE_PREFIX = '__empaphy_repherence_check_';
    private const REFERENCE_CHECK_INT_VALUE_OFFSET    = 1337;

    /**
     * Checks if the two provided variables are references to the same value.
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public static function isReferenceTo(&$a, &$b): bool
    {
        if ($a !== $b) {
            return false;
        }

        $type = gettype($a);
        if ($type !== gettype($b)) {
            return false;
        }

        switch ($type) {
            case 'object':
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                return $a === $b;

            case 'string':
                $checkValue = self::REFERENCE_CHECK_STRING_VALUE_PREFIX . ++self::$referenceCheckValue;
                $originalValue = $a;
                assert('$checkValue !== $originalValue');
                $a = $checkValue;
                $isReference = $b === $checkValue;
                $a = $originalValue;
                assert('$a === $b');

                return $isReference;

            case 'integer':
                $a++;
                $isReference = $a === $b;
                $a--;
                assert('$a === $b');

                return $isReference;

            // TODO: implement float
            // TODO: implement arrays

            default;
                throw new \RuntimeException("{$type} is not supported.");
        }
    }
}
