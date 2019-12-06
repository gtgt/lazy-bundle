<?php
namespace LazyBundle\Model;


interface SerializableInterface extends \Serializable {
    /**
     * @return array
     */
    public function &toArray(): array;

    /**
     * @param array $data
     */
    public function fromArray(array &$data): void;
}
