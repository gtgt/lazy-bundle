<?php

namespace LazyBundle\DBAL\Types;

use InvalidArgumentException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LazyBundle\Enum\Enum;

class PhpEnumType extends Type {
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $enumClass = Enum::class;

    /**
     * @var bool
     */
    protected $useKey = false;

    public function __sleep() {
        return ['name', 'enumClass', 'useKey'];
    }

    /**
     * Gets the name of this type.
     *
     * @return string
     */
    public function getName() {
        return $this->name ?: 'enum';
    }

    /**
     * @return bool
     */
    public function isUseKey(): bool {
        return $this->useKey;
    }

    /**
     * @return string
     */
    public function getEnumClass(): string {
        return $this->enumClass;
    }

    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param array $fieldDeclaration The field declaration.
     * @param AbstractPlatform $platform The currently used database platform.
     *
     * @return string
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) {
        return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @param string|null $value
     * @param AbstractPlatform $platform
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform) {
        if ($value === null) {
            return null;
        }

        // If the enumeration provides a casting method, apply it
        if (\method_exists($this->enumClass, $this->useKey ? 'castKeyIn' : 'castValueIn')) {
            /** @var callable $castValueIn */
            $castValueIn = [$this->enumClass, $this->useKey ? 'castKeyIn' : 'castValueIn'];
            $value = $castValueIn($value);
        }

        // Check if the value is valid for this enumeration
        /** @var callable $isValidCallable */
        $isValidCallable = [$this->enumClass, $this->useKey ? 'isValidKey' : 'isValid'];
        $isValid = $isValidCallable($value);
        if (!$isValid) {
            /** @var callable $toArray */
            $toArray = [$this->enumClass, $this->useKey ? 'keys' : 'toArray'];
            throw new InvalidArgumentException(\sprintf(
                'The value "%s" is not valid for the enum "%s". Expected one of ["%s"]',
                $value,
                $this->enumClass,
                \implode('", "', $toArray())
            ));
        }

        return $this->useKey ? call_user_func([$this->enumClass, 'createByKey'], $value) : new $this->enumClass($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform) {
        if ($value === null || !$value instanceof Enum) {
            return null;
        }

        // If the enumeration provides a casting method, apply it
        if (\method_exists($this->enumClass, $this->useKey ? 'castKeyOut' : 'castValueOut')) {
            /** @var callable $castValueOut */
            $castValueOut = [$this->enumClass, $this->useKey ? 'castKeyOut' : 'castValueOut'];
            return $castValueOut($value);
        }

        // Otherwise, cast to string
        return $this->useKey ? $value->getKey() : $value->getValue();
    }

    /**
     * Gets an array of database types that map to this Doctrine type.
     *
     * @return string[]
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform) {
        return [];
    }

    /**
     * @param string $typeNameOrEnumClass
     * @param string|null $enumClass
     * @param bool $useKey
     *
     * @throws DBALException
     */
    public static function registerEnumType($typeNameOrEnumClass, $enumClass = null, $useKey = false): void {
        $typeName = $typeNameOrEnumClass;
        $enumClass = $enumClass ?: $typeNameOrEnumClass;
        if (self::getTypeRegistry()->has($typeName)) {
            return;
        }

        if (!\is_subclass_of($enumClass, Enum::class)) {
            throw new InvalidArgumentException(\sprintf(
                'Provided enum class "%s" is not valid. Enums must extend "%s"',
                $enumClass,
                Enum::class
            ));
        }

        // Register and customize the type
        self::addType($typeName, static::class);
        /** @var PhpEnumType $type */
        $type = self::getType($typeName);
        $type->name = $typeName;
        $type->enumClass = $enumClass;
        $type->useKey = $useKey;
    }

    /**
     * @param array $types
     *
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    public static function registerEnumTypes(array $types): void {
        foreach ($types as $typeName => $enumClass) {
            $typeName = \is_string($typeName) ? $typeName : $enumClass;
            static::registerEnumType($typeName, $enumClass);
        }
    }

    /**
     * @param AbstractPlatform $platform
     *
     * @return boolean
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform) {
        return true;
    }
}
