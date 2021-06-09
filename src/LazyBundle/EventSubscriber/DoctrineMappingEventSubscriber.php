<?php

namespace LazyBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry as DoctrineManagerRegistry;
use LazyBundle\DBAL\Types\PhpEnumType;
use LazyBundle\DBAL\Types\MyPhpEnumType;
use LazyBundle\Enum\Enum;
use LazyBundle\Manager\ManagerRegistry;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;


class DoctrineMappingEventSubscriber implements EventSubscriberInterface {
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * @var DoctrineManagerRegistry
     */
    protected $doctrineRegistry;

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
     * @var bool
     */
    private $enableEnumTypes = false;

    /**
     * DoctrineMappingEventSubscriber constructor.
     *
     * @param ManagerRegistry $managerRegistry
     * @param DoctrineManagerRegistry $doctrineRegistry
     * @param CacheInterface $cacheEnumType
     */
    public function __construct(ManagerRegistry $managerRegistry, DoctrineManagerRegistry $doctrineRegistry, CacheInterface $cacheEnumType) {
        $this->managerRegistry = $managerRegistry;
        $this->doctrineRegistry = $doctrineRegistry;
        $this->cache = $cacheEnumType;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): \Generator {
        yield KernelEvents::REQUEST => 'onKernelRequest';
        yield ConsoleEvents::COMMAND => 'onConsoleCommand';
        yield KernelEvents::TERMINATE => 'onKernelTerminate';
        yield ConsoleEvents::TERMINATE => 'onConsoleTerminate';
    }

    /**
     * @param array $slcEntityNames
     */
    public function setSlcEntityNames(array $slcEntityNames): void {
        $this->slcEntityNames = $slcEntityNames;
    }

    /**
     * @param string|null $dbalType
     * @param bool $useKey
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform|null $platform
     *
     * @throws DBALException
     */

    protected function registerDbalType(string $dbalType, bool $useKey = false, \Doctrine\DBAL\Platforms\AbstractPlatform $platform = null): void {
        if (\is_string($dbalType) && !array_key_exists($dbalType, $this->registeredEnumTypes) && class_exists($dbalType) && is_a($dbalType, Enum::class, TRUE)) {
            /** @var PhpEnumType $type */
            if ($platform instanceof MySqlPlatform) {
                MyPhpEnumType::registerEnumType($dbalType, null, $useKey);
                $type = MyPhpEnumType::getType($dbalType);
            } else {
                PhpEnumType::registerEnumType($dbalType, null, $useKey);
                $type = PhpEnumType::getType($dbalType);
            }
            $this->registeredEnumTypes[$dbalType] = $type;
            $this->registeredEnumTypesChanged = true;
            if ($platform !== null) {
                $platform->markDoctrineTypeCommented($type);
            }
        }
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws DBALException
     */
    protected function initialize(): void {
        if (!$this->initialized && $this->enableEnumTypes) {
            $this->registeredEnumTypes = $this->cache->get('enum_types', function(ItemInterface $item, &$save) {
                $save = false;
                return [];
            });
            $this->initialized = true;
            if ($this->registeredEnumTypes) {
                $platforms = [];
                /** @var Connection $connection */
                foreach ($this->doctrineRegistry->getConnections() as $connection) {
                    $platform = $connection->getDatabasePlatform();
                    if ($platform !== null) {
                        $platforms[$platform->getName()] = $platform;
                    }
                }
                /** @var PhpEnumType $type */
                foreach ($this->registeredEnumTypes as $dbalTypeName => $type) {
                    if (!PhpEnumType::hasType($dbalTypeName)) {
                        call_user_func(get_class($type).'::registerEnumType', $dbalTypeName, null, $type->isUseKey());
                        foreach ($platforms as $platform) {
                            $platform->markDoctrineTypeCommented($dbalTypeName);
                        }
                    }
                }
            } else {
                // clear metadata cache if no enum types found
                foreach ($this->doctrineRegistry->getManagers() as $manager) {
                    if ($manager instanceof EntityManagerInterface) {
                        $metadataCache = $manager->getConfiguration()->getMetadataCache();
                        if ($metadataCache !== null) {
                            $metadataCache->clear();
                        }
                    }
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
     * @throws DBALException|\Psr\Cache\InvalidArgumentException|\Doctrine\ORM\Mapping\MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void {
        $classMetadata = $eventArgs->getClassMetadata();
        if (\in_array($classMetadata->getName(), $this->slcEntityNames, true)) {
            $classMetadata->enableCache(['usage' => ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE, 'region' => 'user']);
        }
        if ($this->enableEnumTypes) {
            // dynamically register enums from schema
            $platform = $eventArgs->getEntityManager()->getConnection()->getDatabasePlatform();

            foreach ($classMetadata->getFieldNames() as $fieldName) {
                $dbalType = $classMetadata->getTypeOfField($fieldName);
                if ($dbalType !== null) {
                    $fieldMapping = $classMetadata->getFieldMapping($fieldName);
                    $this->registerDbalType($dbalType, !empty($fieldMapping['options']['useKey']), $platform);
                }
            }
            $this->updateCache();
        }
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
     * @deprecated not in use due to $useKey option can't be figured out. Registration is done at startup anyway.
     *
     * @param SchemaColumnDefinitionEventArgs $event
     *
     * @throws DBALException
     */
    public function onSchemaColumnDefinition(SchemaColumnDefinitionEventArgs $event): void {
        $column = $event->getTableColumn();
        $connection = $event->getConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->getSchemaManager();
        if ($schemaManager !== null) {
            $dbalType = $schemaManager->extractDoctrineTypeFromComment($column['Comment'] ?? '', '');
            if (!empty($dbalType)) {
                $this->registerDbalType($dbalType, false, $platform);
            }
        }
    }

    /**
     * Run entity dependency injection by it's manager.
     *
     * @param LifecycleEventArgs $event
     */
    public function postLoad(LifecycleEventArgs $event): void {
        $entity = $event->getObject();
        if (NULL !== $manager = $this->managerRegistry->getManagerForClass($entity)) {
            $manager->injectDependency($entity);
        }
    }

    /**
     * @param bool $enableEnumTypes
     */
    public function setEnableEnumTypes(bool $enableEnumTypes): void {
        $this->enableEnumTypes = $enableEnumTypes;
    }
}
