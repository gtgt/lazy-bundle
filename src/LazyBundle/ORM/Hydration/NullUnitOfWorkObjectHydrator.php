<?php
namespace LazyBundle\ORM\Hydration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\ObjectHydrator;
use Doctrine\ORM\UnitOfWork;

class NullUnitOfWorkObjectHydrator extends ObjectHydrator {

    public const NAME = 'NullUnitOfWorkObjectHydrator';

    public function __construct(EntityManagerInterface $em) {
        parent::__construct($em);
        $this->_uow = new UnitOfWork($this->_em);
    }
}
