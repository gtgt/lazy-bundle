<?php
namespace LazyBundle\DependencyInjection\Configuration;

use Doctrine\Common\Annotations\PhpParser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StrictConfigurationChecker {

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var StrictConfigurationAwareInterface[]
     */
    private $services = [];

    /**
     * StrictConfigurationChecker constructor.
     *
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator) {
        $this->validator = $validator;
    }

    /**
     * @param StrictConfigurationAwareInterface $service
     * @param null $serviceId
     */
    public function addService(StrictConfigurationAwareInterface $service, $serviceId = null): void {
        $this->services[$serviceId ?? get_class($service)] = $service;
    }


    /**
     * Checks strict configuration services
     */
    public function check(): void {
        $phpParser = new PhpParser();
        foreach ($this->services as $serviceId => $service) {
            $propertyNames = $service->getStrictlyConfiguredProperties();
            $refObject = new \ReflectionObject($service);

            $imports = [];
            $parent = $refObject;
            do {
                $imports += $phpParser->parseClass($parent);
                $parent = $parent->getParentClass();
            } while (false !== $parent);

            foreach ($propertyNames as $propertyName => $type) {
                $types = [];
                if (is_numeric($propertyName)) {
                    $propertyName = $type;
                    $type = null;
                }
                if (null === $refProperty = $this->getProperty($refObject, $propertyName)) {
                    throw new InvalidConfigurationException(sprintf('Property ($%s) doesn\'t exists in strictly configured service object (%s).', $propertyName, $serviceId));
                }
                if (null === $type) {
                    if (preg_match('/@var([^*]+)/i', $refProperty->getDocComment(), $m)) {
                        foreach (explode('|', $m[1]) as $varType) {
                            $types[] = trim($varType);
                        }
                    }
                } else {
                    $types = (array)$type;
                }
                $refProperty->setAccessible(true);
                $value = $refProperty->getValue($service);
                $refProperty->setAccessible(false);
                foreach ($types as &$checkType) {
                    if (array_key_exists(strtolower($checkType), $imports)) {
                        $checkType = $imports[strtolower($checkType)];
                    }
                    if (!$this->validator->validate($value, new Type(['type' => $checkType]))->count()) {
                        // pass
                        continue 2;
                    }
                }
                throw new InvalidConfigurationException(sprintf('The value of strictly configured service object (%s) property (%s) should be one of the following types: %s..', $propertyName, get_class($service), implode(', ', $types)));
            }
        }
    }

    /**
     * @param \ReflectionClass $refClass
     * @param string $propertyName
     *
     * @return \ReflectionProperty|null
     */
    protected function getProperty(\ReflectionClass $refClass, string $propertyName): ?\ReflectionProperty {
        do {
            if ($refClass->hasProperty($propertyName)) {
                try {
                    return $refClass->getProperty($propertyName);
                } catch (\ReflectionException $e) {
                }
            }
            $refClass = $refClass->getParentClass();
        } while (false !== $refClass);
        return null;
    }
}
