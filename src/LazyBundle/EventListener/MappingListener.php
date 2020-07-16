<?php

namespace LazyBundle\EventListener;

use Doctrine\DBAL\Types\TypeRegistry;
use LazyBundle\DBAL\Types\PhpEnumType;
use LazyBundle\DBAL\Types\MyPhpEnumType;
use LazyBundle\Enum\Enum;
use LazyBundle\Manager\ManagerRegistry;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;


class MappingListener {
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * Entity names to autoconfigure second level cache
     *
     * @var array
     */
    protected $slcEntityNames = [];

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var array
     */
    protected $registeredEnumTypes = [];

    /**
     * @var bool
     */
    protected $registeredEnumTypesChanged = false;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * MappingListener constructor.
     *
     * @param ManagerRegistry $managerRegistry
     * @param CacheInterface $enumTypeCache
     */
    public function __construct(ManagerRegistry $managerRegistry, CacheInterface $enumTypeCache) {
        $this->managerRegistry = $managerRegistry;
        $this->cache = $enumTypeCache;
    }

    /**
     * @param array $slcEntityNames
     */
    public function setSlcEntityNames(array $slcEntityNames): void {
        $this->slcEntityNames = $slcEntityNames;
    }

    /**
     * @param string|null $dbalType
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @throws DBALException
     */
    protected function registerDbalType(string $dbalType, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): void {
        if (\is_string($dbalType) && !array_key_exists($dbalType, $this->registeredEnumTypes) && class_exists($dbalType) && is_a($dbalType, Enum::class, TRUE)) {
            if ($platform instanceof MySqlPlatform) {
                MyPhpEnumType::registerEnumType($dbalType);
                $this->registeredEnumTypes[$dbalType] = MyPhpEnumType::class;
            } else {
                PhpEnumType::registerEnumType($dbalType);
                $this->registeredEnumTypes[$dbalType] = PhpEnumType::class;
            }
            $this->registeredEnumTypesChanged = true;
            $platform->markDoctrineTypeCommented(PhpEnumType::getType($dbalType));
        }
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function initialize(): void {
        if (!$this->initialized) {
            $this->registeredEnumTypes += $this->cache->get('enum_types', function(ItemInterface $item, &$save) {
                $save = false;
                return [];
            });
            $this->initialized = true;
            foreach ($this->registeredEnumTypes as $dbalType => $enumType) {
                if (!PhpEnumType::hasType($dbalType)) {
                    call_user_func($enumType.'::registerEnumType', $dbalType);
                }
            }
        }
    }


    /**
     * @param RequestEvent $args
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function onKernelRequest(RequestEvent $args): void {
        $this->initialize();
    }

    /**
     * @param ConsoleCommandEvent $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void {
        $this->initialize();
    }

    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @throws DBALException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void {
        $classMetadata = $eventArgs->getClassMetadata();
        if (\in_array($classMetadata->getName(), $this->slcEntityNames, true)) {
            $classMetadata->enableCache(['usage' => ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE, 'region' => 'user']);
        }

        // dynamically register enums from schema
        $platform = $eventArgs->getEntityManager()->getConnection()->getDatabasePlatform();

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            $dbalType = $classMetadata->getTypeOfField($fieldName);
            if ($dbalType !== null) {
                $this->registerDbalType($dbalType, $platform);
            }
        }
        $this->updateCache();
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function updateCache(): void {
        if ($this->registeredEnumTypesChanged === true) {
            $this->cache->get('enum_types', function(CacheItem $item, &$save) {
                $save = true;
                return $this->registeredEnumTypes;
            }, INF);
        }
        $this->registeredEnumTypesChanged = false;
    }

    /**
     * @param TerminateEvent $event
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function onKernelTerminate(TerminateEvent $event) {
        $this->updateCache();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event) {
        $this->updateCache();
    }

    /**
     * @param SchemaColumnDefinitionEventArgs $event
     *
     * @throws DBALException
     */
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $event): void {
        $column = $event->getTableColumn();
        $connection = $event->getConnection();
        $platform = $connection->getDatabasePlatform();
        $dbalType = $connection->getSchemaManager()->extractDoctrineTypeFromComment($column['Comment'] ?? '', '');
        $this->registerDbalType($dbalType, $platform);
    }

    /**
     * Inject manager into entity
     *
     * @param LifecycleEventArgs $event
     */
    public function postLoad(LifecycleEventArgs $event): void {
        $entity = $event->getObject();
        if (NULL !== $manager = $this->managerRegistry->getManagerForClass($entity)) {
            $manager->injectDependency($entity);
        }
    }
}
