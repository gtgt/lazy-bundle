<?php
namespace LazyBundle\Entity;

use LazyBundle\EventSubscriber\DoctrineMappingEventSubscriber;
use LazyBundle\Manager\AbstractManager;

/**
 * Interface for entities which needs their manager attached (done automatically by given manager).
 *
 * @package LazyBundle\Entity
 * @see DoctrineMappingEventSubscriber::postLoad()
 */
interface ManagerAwareEntityInterface extends EntityInterface {
    public function setManager(AbstractManager $manager);
}
