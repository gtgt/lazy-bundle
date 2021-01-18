<?php
namespace LazyBundle\Entity;

/**
 * Interface for entities with (unique) identifier requirement
 *
 * @package LazyBundle\Entity
 */
interface IdentifiableEntityInterface extends EntityInterface {
    public function getId();
}
