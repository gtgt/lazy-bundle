<?php
namespace LazyBundle\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use LazyBundle\Exception\BadMethodCallException;
use LazyBundle\Exception\InvalidManagerArgumentException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ManagerRegistry extends ArrayCollection implements ContainerAwareInterface {
    use ContainerAwareTrait;

    /**
     * @var bool
     */
    private $initialized = false;

    protected function init() {
        foreach ($this as $key => $value) {
            if ($value instanceof  AbstractManager && !is_numeric($key)) {
                continue;
            }
            $this->offsetUnset($key);
            /** @var AbstractManager $manager */
            $manager = \is_string($value) ? $this->container->get($value) : $value;
            $this->checkValue($manager);
            $newKey = !$manager instanceof BasicManager ? $manager->getEntityClass() : null;
            $this->set($newKey, $manager);
        }
        $this->initialized = true;
    }

    /**
     * @param $value
     */
    protected function checkValue($value): void {
        if (!$value instanceof AbstractManager) {
            throw new InvalidManagerArgumentException(sprintf('Only %s type managers can be registered into %s.', \get_class($value), static::class));
        }
    }

    public function get($key) {
        if ($key === null) {
            throw new BadMethodCallException(sprintf('You can\'t get %s directly. Use %s->getManagerForClass() method instead!', BasicManager::class, static::class));
        }
        return parent::get($key);
    }

    /**
     * @param AbstractManager $element
     *
     * @return void
     */
    public function add($element) {
        $this->initialized = false;
        parent::add($element);
    }

    /**
     * Load manager services (value) and associate with entity classes (key).
     * Used by init()
     *
     * @param $key
     * @param AbstractManager $value
     */
    public function set($key, $value) {
        $this->initialized = false;
        parent::set($key, $value);
    }

    /**
     * @param string $entityClass
     *
     * @return BasicManager
     */
    protected function createBasicManager(string $entityClass): BasicManager {
        /** @var BasicManager $manager */
        $manager = clone parent::get(null);
        $manager->setEntityClass($entityClass);
        // next time give the same...
        $this->set($entityClass, $manager);
        return $manager;
    }

    /**
     * @param string|object $classOrObject
     *
     * @return AbstractManager
     */
    public function getManagerForClass($classOrObject): AbstractManager {
        if (false === $this->initialized) {
            $this->init();
        }
        if (\is_object($classOrObject)) {
            $classOrObject = \get_class($classOrObject);
        }
        return $this->containsKey($classOrObject) ? $this->get($classOrObject) : $this->createBasicManager($classOrObject);
    }
}
