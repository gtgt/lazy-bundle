<?php

namespace LazyBundle\Form\Type\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Configurator\TypeConfiguratorInterface;
use LazyBundle\Enum\Enum;
use LazyBundle\Manager\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormConfigInterface;

class EnumTypeConfigurator implements TypeConfiguratorInterface {

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * @param ClassMetadataFactory $managerRegistry
     */
    public function __construct(ManagerRegistry $managerRegistry) {
        $this->managerRegistry = $managerRegistry;
    }


    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig): array {
        if (isset($options['choices'])) {
            return $options;
        }
        $manager = $this->managerRegistry->getManagerForClass($parentConfig->getDataClass());
        if (!$manager) {
            return $options;
        }
        $classMetadata = $manager->getClassMetadata();
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