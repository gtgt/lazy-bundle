<?php
namespace LazyBundle\Entity;

/**
 * Interface IdentifiableEntityInterface
 *
 * @package LazyBundle\Entity
 */
interface IdentifiableEntityInterface extends EntityInterface {
    public function getId();
}
