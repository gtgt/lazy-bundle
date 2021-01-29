<?php


namespace LazyBundle\Model\Traits;


trait JsonSerializableTrait {
    use SerializableTrait;
    /**
     * Serializes the object.
     *
     * @access public
     * @return array
     */
    public function jsonSerialize() {
        // JSON_THROW_ON_ERROR (7.3)
        return $this->toArray();
    }

    /**
     * @param $className
     * @return bool
     */
    protected function isSerializableType($className): bool {
        return false;
    }

    /**
     * Unserializes the object.
     *
     * @param string $serialized
     *
     * @access public
     * @return void;
     */
    public function unserialize($serialized) {
        $flags = JSON_INVALID_UTF8_SUBSTITUTE;
        if (defined('JSON_THROW_ON_ERROR')) {
            $flags |= JSON_THROW_ON_ERROR;
        }
        $data = json_decode($serialized, TRUE, $flags);
        $this->fromArray($data);
    }
}
