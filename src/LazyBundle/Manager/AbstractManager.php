<?php
namespace LazyBundle\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Tools\Pagination\Paginator;
use LazyBundle\DependencyInjection\Configuration\StrictConfigurationAwareInterface;
use LazyBundle\Entity\EntityInterface;
use LazyBundle\Entity\IdentifiableEntityInterface;
use LazyBundle\Entity\ManagerAwareEntityInterface;
use LazyBundle\Exception\BadMethodCallException;
use LazyBundle\Exception\EntityValidationFailedException;
use LazyBundle\Exception\InvalidManagerArgumentException;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use LazyBundle\Service\PaginationService;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class AbstractManager
 *
 * @package LazyBundle\Manager
 *
 * @property-read EntityManager $em Use getEm() instead!
 * @property-read EntityManager $_em Use getEm() instead!
 */
abstract class AbstractManager implements StrictConfigurationAwareInterface {

    /**
     * Validate before save
     */
    public const FLAG_VALIDATE = 1;

    /**
     * Merge before save (Doctrine UnitOfWork)
     */
    public const FLAG_MERGE = 2;

    /**
     * Don't flush Doctrine UnitOfWork after save
     */
    public const FLAG_NO_AUTO_FLUSH = 4;

    /**
     * Auto flush only entity in subject (argument)
     */
    public const FLAG_AUTO_FLUSH_ONLY_ENTITY = 8;

    /**
     * When FLAG_VALIDATE is used, you can define which validation groups you want validate against
     */
    public const OPTION_VALIDATION_GROUPS = 'validation_groups';

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var ObjectManager|EntityManager
     */
    private $_em;

    /**
     * @var ObjectRepository|EntityRepository
     */
    private $repository;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var PaginationService
     */
    private $paginationService;

    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var string
     */
    private $entityAlias;

    public function getStrictlyConfiguredProperties(): array {
        return ['doctrine', 'validator'];
    }

    /**
     * @param Registry $doctrine
     */
    public function setDoctrine(Registry $doctrine): void {
        $this->doctrine = $doctrine;
    }

    /**
     * @return ObjectManager|EntityManager
     */
    protected function getEm() {
        if (null === $this->_em) {
            $entityClass = $this->getEntityClass();
            $em = $this->doctrine->getManagerForClass($entityClass);
            if (null === $em) {
                throw new InvalidConfigurationException(sprintf('%s entity has no doctrine entity manager.', $entityClass));
            }
            $this->_em = $em;
        }
        return $this->_em;
    }

    /** @noinspection MagicMethodsValidityInspection */
    /**
     * @deprecated Compatibility for $this->em and $this->_em, but use getEm().
     *
     * @param $name
     *
     * @return ObjectManager|EntityManager
     */
    public function __get($name) {
        switch ($name) {
            case 'em':
            case '_em':
                return $this->getEm();
        }
    }

    protected function getRepository() {
        if (null === $this->repository) {
            if (null !== $this->_em) {
                $this->repository = $this->_em->getRepository($this->getEntityClass());
            } else {
                $this->repository = $this->doctrine->getRepository($this->getEntityClass());
            }
        }
        return $this->repository;
    }

    /**
     * @return ClassMetadata
     */
    public function getClassMetadata(): ClassMetadata {
        return $this->getEm()->getClassMetadata($this->getEntityClass());
    }


    /**
     * Override this to link to custom entity class
     *
     * @return string
     */
    public function getEntityClass(): string {
        if (!$this->entityClass) {
            $this->entityClass = preg_replace('/\\\(?:Manager|Services)\\\([a-z\\\]+)Manager$/i', '\\Entity\\\$1', static::class);
        }
        return $this->entityClass;
    }

    /**
     * Can be set only once!
     *
     * @param string $entityClass
     */
    public function setEntityClass(string $entityClass): void {
        if ($this->entityClass) {
            throw new BadMethodCallException(sprintf('You can\'t modify manager\'s (%s) entity class (%s) to %s once set (manually or automatically)', static::class, $this->entityClass, $entityClass));
        }
        $this->entityClass = $entityClass;
    }

    /**
     * Override this to set a custom entity alias
     */
    public function getEntityAlias(): string {
        if (!$this->entityAlias) {
            $entityClass = $this->getEntityClass();
            $this->entityAlias = strtolower(preg_replace('/[^A-Z]/', '', substr($entityClass, strrpos($entityClass, '\\') + 1)));
        }
        return $this->entityAlias;
    }

    /**
     * Check if entity can be handled by repository of the manager
     *
     * @param EntityInterface $entity
     */
    protected function checkEntityClass(EntityInterface $entity): void {
        $entityClass = $this->getEntityClass();
        // allow proxies...
        if (substr(\get_class($entity), -1 * \strlen($entityClass)) !== $entityClass) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = end($caller);
            throw InvalidManagerArgumentException::invalidObject($caller['class'].$caller['type'].$caller['function'] ?? __CLASS__, $entityClass, $entity);
        }
    }

    /**
     * @return ValidatorInterface
     */
    public function getValidator(): ValidatorInterface {
        return $this->validator;
    }

    /**
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator): void {
        $this->validator = $validator;
    }

    /**
     * @return PaginationService
     */
    public function getPaginationService(): PaginationService {
        return $this->paginationService;
    }

    /**
     * @param PaginationService $paginationService
     */
    public function setPaginationService(PaginationService $paginationService): void {
        $this->paginationService = $paginationService;
    }

    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     *
     * @param string|null $alias
     * @param string|null $indexBy The index for the from.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias = null, string $indexBy = null): QueryBuilder {
        if (method_exists($this->getRepository(), 'createQueryBuilder')) {
            if (null === $alias) {
                $alias = $this->getEntityAlias();
            }
            return $this->getRepository()->createQueryBuilder($alias, $indexBy);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Creates a new Query instance based on a predefined metadata named query.
     *
     * @param string $queryName
     *
     * @return Query
     */
    public function createNamedQuery(string $queryName): Query {
        if (method_exists($this->getRepository(), 'createNamedQuery')) {
            return $this->getRepository()->createNamedQuery($queryName);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Creates a native SQL query.
     *
     * @param string $queryName
     *
     * @return NativeQuery
     */
    public function createNativeNamedQuery(string $queryName): NativeQuery {
        if (method_exists($this->getRepository(), 'clear')) {
            return $this->getRepository()->createNativeNamedQuery($queryName);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Returns a partial reference, which is an entity object with only the identifiers set as they are passed to the first argument.
     * All other fields are null.
     *
     * @param $id
     *
     * @return EntityInterface|null
     */
    public function getReference($id): ?EntityInterface {
        return $this->getEm()->getPartialReference($this->getEntityClass(), $id);
    }

    /**
     * Clears the repository, causing all managed entities to become detached.
     *
     * @return void
     */
    public function clear(): void {
        if (method_exists($this->getRepository(), 'clear')) {
            $this->getRepository()->clear();
            return;
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @param int|null $lockMode One of the \Doctrine\DBAL\LockMode::* constants
     *                              or NULL if no specific lock mode should be used
     *                              during the search. (Works only when repository is an EntityRepositotry)
     * @param int|null $lockVersion The lock version. (Works only when repository is an EntityRepositotry)
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findById($id, $lockMode = null, $lockVersion = null) {
        return $this->getRepository()->find($id, $lockMode, $lockVersion);
    }

    /**
     * Finds all entities in the repository.
     *
     * @return array The entities.
     */
    public function findAll(): array {
        return $this->getRepository()->findAll();
    }

    /**
     * Finds entities by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array The objects.
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findOneBy(array $criteria, array $orderBy = null) {
        return $this->getRepository()->findOneBy($criteria, $orderBy);
    }

    /**
     * Select all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param Criteria $criteria
     *
     * @return Collection
     */
    public function matching(Criteria $criteria): Collection {
        if (method_exists($this->getRepository(), 'matching')) {
            return $this->getRepository()->matching($criteria);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Create paginated result from Criteria.
     *
     * @param Criteria $criteria
     * @param int $limit
     * @param string|null $alias
     * @param string|null $indexBy
     *
     * @return Paginator
     *
     * @throws Query\QueryException
     */
    public function matchingPaginator(Criteria $criteria, $limit = 10, string $alias = null, string $indexBy = null): Paginator {
        $queryBuilder = $this->createQueryBuilder($alias, $indexBy)->addCriteria($criteria);
        return $this->getPaginationService()->paginate($queryBuilder, $limit);
    }

    /**
     * Counts entities by a set of criteria.
     *
     * @param array $criteria
     *
     * @return int The cardinality of the objects that match the given criteria.
     */
    public function count(array $criteria): int {
        if (method_exists($this->getRepository(), 'count')) {
            return $this->getRepository()->count($criteria);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Adds support for magic method calls.
     *
     * @param string $method
     * @param array $arguments
     *
     * @return mixed The returned value from the resolved method.
     *
     * @throws \BadMethodCallException If the method called is invalid
     * @throws ORMException
     */
    public function __call($method, $arguments) {
        if (method_exists($this->getRepository(), '__call')) {
            return $this->getRepository()->__call($method, $arguments);
        }
        throw new \BadMethodCallException(sprintf('%s not implemented.', __METHOD__));
    }

    /**
     * Checks database connection by ping and reconnects if necessary
     */
    public function checkConnection(): void {
        $connection = $this->getEm()->getConnection();
        if ($connection->isConnected() !== true || $connection->ping() !== true) {
            $connection->close();
            $connection->connect();
        }
    }

    /**
     * Closes connection
     */
    public function closeConnection(): void {
        $this->getEm()->getConnection()->close();
    }

    /**
     * Is Em closed?
     */
    public function isClosed(): bool {
        return !$this->getEm()->isOpen();
    }

    /**
     * @param bool $enable
     */
    public function setBufferedQueryUse(bool $enable): void {
        $this->getEm()->getConnection()->getWrappedConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $enable);
    }

    /**
     * @param EntityInterface|EntityInterface[] $entity
     * @param array|null $validationGroups
     *
     * @return ConstraintViolationListInterface
     */
    public function validate($entity, array $validationGroups = null): ConstraintViolationListInterface {
        if (!\is_iterable($entity)) {
            return $this->validator->validate($entity, null, $validationGroups);
        }
        $violations = new ConstraintViolationList();
        foreach ($entity as $object) {
            $this->checkEntityClass($object);
            $violations->addAll($this->validator->validate($object, null, $validationGroups));
        }
        return $violations;
    }

    /**
     * @param EntityInterface|EntityInterface[] $entity
     * @param int $flags Can be: self::FLAG_VALIDATE, self::FLAG_MERGE, self::FLAG_NO_AUTO_FLUSH, self::FLAG_AUTO_FLUSH_ONLY_ENTITY
     * @param array $options Keys are: self::OPTION_VALIDATION_GROUPS
     *
     * @throws ORMException|EntityValidationFailedException
     */
    public function save($entity, $flags = 0, $options = []): void {
        $em = $this->getEm();
        if ($flags & self::FLAG_VALIDATE) {
            $violations = $this->validate($entity, $options[self::OPTION_VALIDATION_GROUPS] ?? null);
            if ($violations->count()) {
                throw new EntityValidationFailedException($entity, $violations);
            }
        }
        foreach (\is_iterable($entity) ? $entity : [$entity] as $object) {
            $this->checkEntityClass($object);
            if ($flags & self::FLAG_MERGE) {
                $em->merge($object);
            } else {
                /** @noinspection PhpUnhandledExceptionInspection */
                $em->persist($object);
            }
        }
        if ($flags ^ self::FLAG_NO_AUTO_FLUSH) {
            $this->flush(($flags & self::FLAG_AUTO_FLUSH_ONLY_ENTITY) ? $entity : null);
        }
    }

    /**
     * @param EntityInterface|EntityInterface[] $entity
     * @param int $flags
     *
     * @throws ORMException
     */
    public function delete(EntityInterface $entity, $flags = 0): void {
        $em = $this->getEm();
        foreach (\is_iterable($entity) ? $entity : [$entity] as $object) {
            $this->checkEntityClass($object);
            $em->remove($object);
        }
        if ($flags ^ self::FLAG_NO_AUTO_FLUSH) {
            $this->flush(($flags & self::FLAG_AUTO_FLUSH_ONLY_ENTITY) ? $entity : null);
        }
    }

    /**
     * @param null|EntityInterface|EntityInterface[] $entity
     *
     * @throws ORMException
     */
    public function flush($entity = null): void {
        $em = $this->getEm();
        if ($entity === null) {
            $em->flush();
            return;
        }
        foreach (\is_iterable($entity) ? $entity : [$entity] as $object) {
            $this->checkEntityClass($object);
            $em->flush($object);
        }
    }

    /**
     * @param null|EntityInterface|EntityInterface[] $entity
     *
     * @throws ORMException
     */
    public function refresh($entity = null): void {
        $em = $this->getEm();
        foreach (\is_iterable($entity) ? $entity : [$entity] as $object) {
            $this->checkEntityClass($object);
            $em->refresh($object);
        }
    }

    /**
     * @param bool $onlyMemory
     * @param bool $fullClear
     */
    public function flushCache(bool $onlyMemory = false, $fullClear = false): void {
        if ($onlyMemory === false && (null !== $cache = $this->getEm()->getCache())) {
            $cache->evictQueryRegions();
            if ($fullClear) {
                $cache->evictEntityRegions();
                $cache->evictCollectionRegions();
            } else {
                $cache->evictEntityRegion($this->getEntityClass());
                foreach ($this->getClassMetadata()->getAssociationNames() as $association) {
                    $cache->evictCollectionRegion($this->getEntityClass(), $association);
                }
            }
        }
        $this->getEm()->clear($fullClear ? null : $this->getEntityClass());
    }

    /**
     * @param callable $func Gets EntityManager by argument.
     *
     * @return bool|mixed
     * @throws \Throwable
     */
    public function transactional(callable $func) {
        return $this->getEm()->transactional($func);
    }

    /**
     * @param EntityInterface $entity
     *
     * @return void
     */
    public function injectDependency(EntityInterface $entity): void {
        if ($entity instanceof ManagerAwareEntityInterface) {
            $entity->setManager($this);
        }
    }

    /**
     * @return EntityInterface
     */
    public function createNew(): EntityInterface {
        $class = $this->getEntityClass();
        $entity = new $class();
        $this->injectDependency($entity);
        return $entity;
    }

    /**
     * Determines that an enity is new or not (by UnitOfWork state)
     *
     * @param EntityInterface $entity
     *
     * @return bool
     */
    final public function isNewEntity(EntityInterface $entity): bool {
        return $this->getEm()->getUnitOfWork()->getEntityState($entity) === UnitOfWork::STATE_NEW;
    }

    /**
     * @param EntityInterface $entity
     */
    public function resetId(EntityInterface $entity): void {
        $metaData = $this->getClassMetadata();
        foreach ($metaData->getIdentifierFieldNames() as $idFieldName) {
            $entity->{$idFieldName} = null;
        }
    }

    /**
     * De-registers (detach) an entity from UnitOfWork
     *
     * @param EntityInterface $entity
     */
    final public function detachEntity(EntityInterface $entity): void {
        $this->getEm()->detach($entity);
    }

    /**
     * Attach (register as managed) an entity into UnitOfWork
     *
     * @param IdentifiableEntityInterface $entity
     * @param array $data
     */
    final public function attachEntity(IdentifiableEntityInterface $entity, array $data = []): void {
        $this->getEm()->getUnitOfWork()->registerManaged($entity, ['id' => $entity->getId()], !empty($data) ? $data : (array)$entity);
    }

    /**
     * Clones an (uow attached) entity into a non-uow attached object
     *
     * @param EntityInterface $entity
     *
     * @return EntityInterface
     */
    final public function cloneEntity(EntityInterface $entity): EntityInterface {

        $this->checkEntityClass($entity);

        // first, detach the original
        $oldData = $this->getEm()->getUnitOfWork()->getOriginalEntityData($entity);
        $this->detachEntity($entity);

        // clone
        $newEntity = clone $entity;
        $this->resetId($entity);

        // re-attach the original
        $this->attachEntity($entity, $oldData);

        return $this->doCloneEntity($entity, $newEntity);
    }

    /**
     * Hookable clone action
     *
     * @param EntityInterface $oldEntity
     * @param EntityInterface $newEntity
     *
     * @return EntityInterface
     */
    protected function doCloneEntity(EntityInterface $oldEntity, EntityInterface $newEntity): EntityInterface {
        return $newEntity;
    }

    /**
     * Return garbage entities
     *
     * @return Collection
     */
    public function findAllGarbage(): Collection {
        return new ArrayCollection();
    }

}
