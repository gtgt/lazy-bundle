<?php
namespace LazyBundle\Manager;
use Doctrine\Persistence\ManagerRegistry as DoctrineManagerRegistry;
use LazyBundle\Service\PaginationService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ManagerConfigurator
 *
 * @package LazyBundle\Manager
 */
class ManagerConfigurator {
    /**
     * @var DoctrineManagerRegistry
     */
    protected $doctrine;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var PaginationService
     */
    protected $paginationService;

    /**
     * ManagerConfigurator constructor.
     *
     * @param DoctrineManagerRegistry $doctrine
     * @param ValidatorInterface $validator
     * @param PaginationService $paginationService
     */
    public function __construct(DoctrineManagerRegistry $doctrine, ValidatorInterface $validator, PaginationService $paginationService) {
        $this->doctrine = $doctrine;
        $this->validator = $validator;
        $this->paginationService = $paginationService;
    }

    /**
     * @param AbstractManager $manager
     */
    public function configure(AbstractManager $manager): void {
        $manager->setDoctrine($this->doctrine);
        $manager->setValidator($this->validator);
        $manager->setPaginationService($this->paginationService);
    }
}
