<?php
namespace LazyBundle\Model;


interface JsonSerializableInterface extends SerializableInterface, \JsonSerializable {
    /**
     * @return array
     */
    public function &toArray(): array;

    /**
     * @param array $data
     */
    public function fromArray(array &$data): void;
}
