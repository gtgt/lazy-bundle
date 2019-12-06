<?php

namespace LazyBundle\EventListener;

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
    protected $slcEntityNames;

    /**
     * MappingListener constructor.
     *
     * @param ManagerRegistry $managerRegistry
     * @param array $slcEntityNames
     */
    public function __construct(ManagerRegistry $managerRegistry, array $slcEntityNames = []) {
        $this->managerRegistry = $managerRegistry;
        $this->slcEntityNames = $slcEntityNames;
    }


    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     *
     * @throws DBALException
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
            if (\is_string($dbalType) && !PhpEnumType::hasType($dbalType) && class_exists($dbalType) && is_a($dbalType, Enum::class, TRUE)) {
                if ($platform instanceof MySqlPlatform) {
                    MyPhpEnumType::registerEnumType($dbalType);
                } else {
                    PhpEnumType::registerEnumType($dbalType);
                }
                $platform->markDoctrineTypeCommented(PhpEnumType::getType($dbalType));
            }
        }
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
        if (\is_string($dbalType) && !PhpEnumType::hasType($dbalType) && class_exists($dbalType) && is_a($dbalType, Enum::class, TRUE)) {
            if ($platform instanceof MySqlPlatform) {
                MyPhpEnumType::registerEnumType($dbalType);
            } else {
                PhpEnumType::registerEnumType($dbalType);
            }
            $platform->markDoctrineTypeCommented(PhpEnumType::getType($dbalType));
        }
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
