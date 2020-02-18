<?php

namespace LazyBundle\Enum;

use MyCLabs\Enum\Enum as BaseEnum;

/**
 * Create an enum by implementing this class and adding class constants.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Daniel Costa <danielcosta@gmail.com>
 * @author Mirosław Filip <mirfilip@gmail.com>
 * @author Tamás Gere (GT) <gt||gtdev.eu>
 *
 * @package LazyBundle\Enum
 */
abstract class Enum extends BaseEnum {
    /**
     * Enum value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Store existing constants in a static cache per object.
     *
     * @var array
     */
    protected static $cache = [];

    /**
     * Creates a new value of some type
     *
     * @param mixed $value
     *
     * @throws \UnexpectedValueException if incompatible type is given.
     *
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct($value) {
        if (\is_object($value)) {
            $value = (string)$value;
        }
        if (!self::isValid($value)) {
            throw new \UnexpectedValueException("Value '$value' is not part of the enum ".\get_called_class());
        }

        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Returns the enum key (i.e. the constant name).
     *
     * @return mixed
     */
    public function getKey() {
        return static::search($this->value);
    }

    /**
     * @return string
     */
    public function __toString() {
        return (string)$this->value;
    }

    /**
     * Returns the names (keys) of all constants in the Enum class
     *
     * @return array
     */
    public static function keys(): array {
        return \array_keys(static::toArray());
    }

    /**
     * Returns instances of the Enum class of all Enum constants
     *
     * @return static[] Constant name in key, Enum instance in value
     */
    public static function values(): array {
        $values = array();

        foreach (static::toArray() as $key => $value) {
            $values[$key] = new static($value);
        }

        return $values;
    }

    /**
     * Check if is valid enum value
     *
     * @param $value
     *
     * @return bool
     */
    public static function isValid($value): bool {
        return \in_array($value, static::toArray(), true);
    }

    /**
     * Check if is valid enum key
     *
     * @param $key
     *
     * @return bool
     */
    public static function isValidKey($key): bool {
        $array = static::toArray();

        return isset($array[$key]) || \array_key_exists($key, $array);
    }

    /**
     * Return key for value
     *
     * @param $value
     *
     * @return mixed
     */
    public static function search($value) {
        return \array_search($value, static::toArray(), true);
    }

    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a class constant
     *
     * @param string $name
     * @param array $arguments
     *
     * @return static
     * @throws \BadMethodCallException
     */
    public static function __callStatic($name, $arguments) {
        $array = static::toArray();
        if (isset($array[$name]) || \array_key_exists($name, $array)) {
            return new static($array[$name]);
        }

        throw new \BadMethodCallException("No static method or enum constant '$name' in class ".\get_called_class());
    }

    /**
     * Specify data which should be serialized to JSON. This method returns data that can be serialized by json_encode()
     * natively.
     *
     * @return mixed
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     */
    public function jsonSerialize() {
        return $this->getValue();
    }

    /**
     * Creates an enum instance by value (alternative to "new" keyword or magic method)
     *
     * @param $value
     *
     * @return Enum
     */
    public static function create($value): self {
        return new static($value);
    }

    /**
     * Creates an enum instance by key (alternative to "new" keyword or magic method)
     *
     * @param $value
     *
     * @return Enum
     */
    public static function createByKey($value): self {
        return static::__callStatic($value, []);
    }

    /**
     * Checks a value, throws exception if not found.
     *
     * @param $value
     */
    public static function checkValue($value): void {
        if (!static::isValid($value)) {
            throw new \UnexpectedValueException(sprintf('Value "%s" is not part of the enum.', $value).static::class);
        }
    }

    /**
     * Checks a key, throws exception if not found.
     *
     * @param $key
     */
    public static function checkKey($key): void {
        if (!static::isValidKey($key)) {
            throw new \UnexpectedValueException(sprintf('Key "%s" is not part of the enum.', $key).static::class);
        }
    }

    /**
     * Returns all possible values as an array (only public constants)
     *
     * @return array Constant name in key, constant value in value
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function toArray(): array {
        /* Only before PHP 7.1...
        if (!class_exists('\ReflectionClassConstant')) {
            $class = \get_called_class();
            if (!isset(static::$cache[$class])) {
                $reflection = new \ReflectionClass($class);
                static::$cache[$class] = $reflection->getConstants();
            }

            return static::$cache[$class];
        }*/
        if (!isset(static::$cache[static::class])) {
            $reflectionClass = new \ReflectionClass(static::class);
            static::$cache[static::class] = [];
            foreach ($reflectionClass->getReflectionConstants() as $reflectionConstant) {
                if ($reflectionConstant->isPublic()) {
                    static::$cache[static::class][$reflectionConstant->getName()] = $reflectionConstant->getValue();
                }
            }
        }

        return static::$cache[static::class];
    }

    /**
     * Returns true if the enum equals one of the variables
     *
     * @param BaseEnum ...$value
     *
     * @return bool
     */
    public function equalsOneOf(BaseEnum ...$value): bool {
        foreach ($value as $v) {
            if ($this->equals($v)) {
                return true;
            }
        }
        return false;
    }
}
