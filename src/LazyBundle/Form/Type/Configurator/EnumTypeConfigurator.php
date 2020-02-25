<?php

namespace LazyBundle\Form\Type\Configurator;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
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
        if (isset($options['choices'])) {
            return $options;
        }
        /** @var EntityManager $manager */
        $manager = $this->registry->getManagerForClass($parentConfig->getDataClass());
        if (!$manager instanceof EntityManager) {
            return $options;
        }
        $classMetadata = $manager->getClassMetadata($parentConfig->getDataClass());
        if (!is_a($class = $classMetadata->getTypeOfField($name), Enum::class, true)) {
            return $options;
        }
        /** @var Enum $class */
        $options['choices'] = array_combine($class::toArray(), $class::values());

        $options['choice_value'] = 'value';
        $options['choice_label'] = function(?Enum $enum) {
            return $enum ? $enum->getValue() : '';
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
        return \in_array($type, ['choice', ChoiceType::class], true);
    }
}