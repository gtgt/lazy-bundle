<?php

namespace LazyBundle\Form\Type\Configurator;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Configurator\TypeConfiguratorInterface;
use LazyBundle\Enum\Enum;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormConfigInterface;

class EnumTypeConfigurator implements TypeConfiguratorInterface {

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry) {
        $this->registry = $registry;
    }


    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig): array {
        $enumClass = null;
        /** @var EntityManager $manager */
        $entityClass = $parentConfig->getDataClass();
        if ($entityClass) {
            $manager = $this->registry->getManagerForClass($entityClass);
            if ($manager instanceof EntityManager) {
                $classMetadata = $manager->getClassMetadata($entityClass);
                $enumClass = $classMetadata->getTypeOfField($name);
            }
        }
        if (!$enumClass && class_exists($metadata['type'])) {
            $enumClass = $metadata['type'];
        }
        if (!is_a($enumClass, Enum::class, true)) {
            return $options;
        }
        /** @var Enum $enumClass */
        $origChoices = $options['choices'] ?? [];
        $options['choices'] = array_combine($enumClass::toArray(), $enumClass::values());

        $options['choice_value'] = 'value';
        $options['choice_label'] = function(?Enum $enum) use ($origChoices) {
            if ($enum === null) {
                return '';
            }
            $choice = array_search($enum->getValue(), $origChoices, true);
            return $choice ?: $enum->getValue();
        };
        $options['choice_attr'] = function(?Enum $enum) {
            return $enum ? ['class' => 'enum_'.strtolower($enum->getKey())] : [];
        };

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, array $options, array $metadata): bool {
        if (class_exists($type) && is_a($type, Enum::class, true)) {
            return true;
        }
        return \in_array($type, ['choice', ChoiceType::class], true);
    }
}