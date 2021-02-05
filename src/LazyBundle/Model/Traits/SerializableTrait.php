<?php

namespace LazyBundle\Model\Traits;

use LazyBundle\Entity\ManagerAwareEntityInterface;
use LazyBundle\Model\SerializableInterface;

/**
 * Simple serializer helper for \Serializable implementation
 *
 * IMPORTANT - private properies and those which name is returned by
 * getIgnoredSerializableAttributeNames method will not be serialized!
 */
trait SerializableTrait {

    /**
     * Memory cache for serializable attribute types
     *
     * @var array
     */
    private static $__attrTypeCache = [];

    /**
     * Serializes the object.
     *
     * @access public
     * @return string
     */
    public function serialize(): string {
        return serialize($this->toArray());
    }

    /**
     * @return array
     */
    public function &toArray(): array {
        $data = [];
        foreach ($this->getSerializableAttributeTypesFromCache() as $name => [$type, $isArray, $isClass, $serializableByType]) {
            if ($isClass && !$serializableByType) {
                if ($isArray) {
                    $data[$name] = [];
                    if (\is_array($this->{$name})) {
                        foreach ($this->{$name} as $key => $propertyData) {
                            if ($propertyData instanceof SerializableInterface) {
                                $data[$name][$key] = $propertyData->toArray();
                            } else {
                                throw new \InvalidArgumentException(sprintf('Object with class hint (%s) must implement %s to get serialized within %s class (property: %s).', $type.'[]', SerializableInterface::class, static::class, $name));
                            }
                        }
                    } elseif ($this->{$name} !== NULL) { // null is ok here
                        throw new \InvalidArgumentException(sprintf('No array found within %s class, on property: %s. Maybe you need remove the [] from the type hint (%s).', static::class, $name, $type.'[]'));
                    }
                } elseif ($this->{$name} instanceof SerializableInterface) {
                    $data[$name] = $this->{$name}->toArray();
                } elseif (\is_array($this->{$name})) {
                    throw new \InvalidArgumentException(sprintf('Array found instead of %s type within %s class (property: %s). Maybe you forgot to add [] to the type hint.', $type, static::class, $name));
                } elseif (NULL !== $this->{$name}) { // null is ok here
                    throw new \InvalidArgumentException(sprintf('Object with class hint (%s) must implement %s to get serialized within %s class (property: %s).', $type, SerializableInterface::class, static::class, $name));
                }
            } else {
                $data[$name] = $this->{$name};
            }
        }
        return $data;
    }

    /**
     * Unserializes the object.
     *
     * @param string $serialized
     *
     * @access public
     * @return void;
     */
    public function unserialize($serialized): void {
        $allowedClasses = [];
        foreach ($this->getSerializableAttributeTypesFromCache() as $name => [$type, $isArray, $isClass, $serializableByType]) {
            if ($isClass && $serializableByType) {
                $allowedClasses[$type] = ltrim($type, '\\');
            }
        }
        $data = unserialize($serialized, ['allowed_classes' => array_values($allowedClasses)]);
        $this->fromArray($data);
    }

    /**
     * @param $data
     */
    public function fromArray(array &$data): void {
        foreach ($this->getSerializableAttributeTypesFromCache() as $name => [$type, $isArray, $isClass]) {
            if (\array_key_exists($name, $data)) {
                if ($isClass && is_a($type, SerializableInterface::class, true)) {
                    /** @var SerializableInterface $propertyData */
                    if ($isArray) {
                        $this->{$name} = [];
                        foreach ($data[$name] as $key => $item) {
                            $propertyData = new $type();
                            $propertyData->fromArray($item);
                            $this->{$name}[$key] = $propertyData;
                        }
                    } else {
                        $propertyData = new $type();
                        $propertyData->fromArray($data[$name]);
                        $this->{$name} = $propertyData;
                    }
                } else {
                    $this->{$name} = &$data[$name];
                }
            } else {
                // partial arrays enabled
                continue;
                //throw new \InvalidArgumentException(sprintf('Property [%s] not found in [%s] class!', $name, \get_class($this)), E_USER_NOTICE);
            }
        }
    }

    public static function createFromArray(array $data): self {
        // maybe this should be done by ReflectionClass, without invoking construct
        $object = new self();
        $object->fromArray($data);
        return $object;
    }

    /**
     * Returns serializable property names.
     * It's the public and protected property names by default.
     * Feel free to override.
     *
     * @return array
     */
    protected function getSerializableAttributeNames(): array {
        return array_keys(get_object_vars($this));
    }

    /**
     * Those which you want to exclude from the previous method's result.
     *
     * @return array
     */
    protected function getIgnoredSerializableAttributeNames(): array {
        return $this instanceof ManagerAwareEntityInterface ? ['manager'] : [];
    }

    /**
     * @param $className
     *
     * @return bool
     */
    protected function isSerializableType($className): bool {
        return true;
    }

    /**
     * @return array
     */
    private function getSerializableAttributeTypes(): array {
        $attributes = array_fill_keys(array_diff($this->getSerializableAttributeNames(), $this->getIgnoredSerializableAttributeNames()), 'mixed');
        try {
            $ref = new \ReflectionClass(static::class);
            $ns = $ref->getNamespaceName();
        } catch (\ReflectionException $e) {
            return $attributes;
        }
        foreach ($ref->getProperties() as $property) {
            if (!\array_key_exists($property->getName(), $attributes)) {
                continue;
            }
            $isArray = $isClass = $serializableByType = false;
            if (preg_match('/@var\s+(\S+)/', $property->getDocComment(), $m)) {
                [, $type] = $m;
                $type = ltrim(ltrim(trim(current(explode('|', $type, 2))), '?'));
                if (substr($type, -2) === '[]') {
                    $isArray = true;
                    $type = substr($type, 0, -2);
                }
                if (\in_array($type, ['boolean', 'bool', 'integer', 'int', 'float', 'double', 'string', 'array', 'object', 'callable', 'iterable', 'resource', 'NULL', 'mixed', 'number', 'callback', 'void']) === false) {
                    if (strpos($type, '\\') !== 0) {
                        $type = $ns.'\\'.$type;
                    }
                    $isClass = true;
                    if (!is_a($type, SerializableInterface::class, true)) {
                        $serializableByType = $this->isSerializableType($type);
                    }
                }
            } else {
                $type = 'mixed';
            }

            $attributes[$property->getName()] = [$type, $isArray, $isClass, $serializableByType];
        }
        return $attributes;
    }

    /**
     * Memory (in-process) caches the serializable attribute names and types.
     *
     * @return array
     */
    private function &getSerializableAttributeTypesFromCache(): array {
        $key = \get_class($this);
        if (!isset(self::$__attrTypeCache[$key])) {
            self::$__attrTypeCache[$key] = $this->getSerializableAttributeTypes();
        }
        return self::$__attrTypeCache[$key];
    }
}
