<?php
namespace LazyBundle\Manager;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ManagerConfigurator
 *
 * @package LazyBundle\Manager
 */
class ManagerConfigurator {
    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * ManagerConfigurator constructor.
     *
     * @param DoctrineManagerRegistry $doctrine
     * @param ValidatorInterface $validator
     */
    public function __construct(DoctrineManagerRegistry $doctrine, ValidatorInterface $validator) {
        $this->doctrine = $doctrine;
        $this->validator = $validator;
    }

    /**
     * @param AbstractManager $manager
     */
    public function configure(AbstractManager $manager): void {
        $manager->setDoctrine($this->doctrine);
        $manager->setValidator($this->validator);
    }
}
