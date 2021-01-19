<?php
namespace LazyBundle\Entity;

use phpDocumentor\Reflection\Types\Scalar;

/**
 * Interface for entities with (unique) identifier requirement
 *
 * @package LazyBundle\Entity
 */
interface IdentifiableEntityInterface extends EntityInterface {
    /**
     * @return string|int|float|null
     */
    public function getId();
}
