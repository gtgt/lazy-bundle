<?php
namespace LazyBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseController;
use LazyBundle\Manager\ManagerRegistry;

class EasyAdminController extends BaseController {
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    public function __construct(ManagerRegistry $registry) {
        $this->registry = $registry;
    }

    protected function createNewEntity() {
        $entityFullyQualifiedClassName = $this->entity['class'];
        $manager = $this->registry->getManagerForClass($entityFullyQualifiedClassName);
        if ($manager) {
            return $manager->createNew();
        }
        return new $entityFullyQualifiedClassName();
    }

    public function persistEntity($entity): void {
        if (method_exists($entity, 'updateTimestamps')) {
            $entity->updateTimestamps(new \DateTime());
        }
        parent::persistEntity($entity);
    }
    public function updateEntity($entity): void {
        if (method_exists($entity, 'updateTimestamps')) {
            $entity->updateTimestamps(new \DateTime());
        }
        parent::updateEntity($entity);
    }
}