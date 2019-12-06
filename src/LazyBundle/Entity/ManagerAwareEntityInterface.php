<?php
namespace LazyBundle\Entity;

use LazyBundle\EventListener\MappingListener;
use LazyBundle\Manager\AbstractManager;

/**
 * Interface ManagerAwareEntityInterface
 *
 * @package LazyBundle\Entity
 * @see MappingListener::postLoad()
 */
interface ManagerAwareEntityInterface {
    public function setManager(AbstractManager $manager);
}
