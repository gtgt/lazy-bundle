<?php

namespace LazyBundle\Form\Type\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Configurator\CollectionTypeConfigurator as BaseConfigurator;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EasyAdminFormType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Util\FormTypeHelper;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class CollectionTypeConfigurator extends BaseConfigurator {
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null) {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig) {
        if (\in_array($metadata['fieldType'], ['collection', CollectionType::class])) {
            $options['entry_type'] = FormTypeHelper::getTypeClass(isset($options['entry_type']) ? $options['entry_type'] : 'easyadmin');
            if ($options['entry_type'] === EasyAdminFormType::class) {
                $options['entry_options']['view'] = 'edit';
            }
            if (!isset($options['by_reference'])) {
                $options['by_reference'] = false;
            }
        }

        return $options;
    }
}