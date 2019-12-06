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
     * Unserializes the object.
     *
     * @param string $serialized
     *
     * @access public
     * @return void;
     */
    public function unserialize($serialized) {
        $data = json_decode($serialized, TRUE);
        $this->fromArray($data);
    }
}
