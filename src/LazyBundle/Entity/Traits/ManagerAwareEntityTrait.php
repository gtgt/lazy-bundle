<?php
namespace LazyBundle\Entity\Traits;

use LazyBundle\Manager\AbstractManager;

trait ManagerAwareEntityTrait {
    /**
     * @var AbstractManager
     */
    protected $manager;

    /**
     * @param AbstractManager $manager
     */
    public function setManager(AbstractManager $manager): void {
        $this->manager = $manager;
    }

    /**
     * Reset id on clone
     */
    public function __clone() {
        if (null !== $this->manager) {
            $this->manager->resetId($this);
        }
    }
}
