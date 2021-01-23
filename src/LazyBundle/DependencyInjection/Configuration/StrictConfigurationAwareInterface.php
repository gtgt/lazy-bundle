<?php


namespace LazyBundle\DependencyInjection\Configuration;

/**
 * Interface StrictConfigurationAwareInterface
 *
 * @deprecated Use @required on autowired service properties / setter methods
 *
 * @package LazyBundle\DependencyInjection\Configuration
 */
interface StrictConfigurationAwareInterface {
    /**
     * Return property names to match their value to own "var" annotation when constructing the service in container.
     *
     * @return array
     */
    public function getStrictlyConfiguredProperties(): array;
}
