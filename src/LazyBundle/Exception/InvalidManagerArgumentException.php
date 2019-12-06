<?php


namespace LazyBundle\Exception;


class InvalidManagerArgumentException extends \InvalidArgumentException {
    /**
     * @param string $context
     * @param $expected
     * @param mixed $given
     * @param int $parameterIndex
     *
     * @return self
     */
    public static function invalidObject($context, $expected, $given, $parameterIndex = 1)
    {
        return new self(sprintf('%s expects parameter %d to be %s, %s given.', $context, $parameterIndex, $expected, \is_object($given) ? \get_class($given) : \gettype($given)));
    }
}
