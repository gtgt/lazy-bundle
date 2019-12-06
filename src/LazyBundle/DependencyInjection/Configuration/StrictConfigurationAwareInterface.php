<?php


namespace LazyBundle\DependencyInjection\Configuration;


interface StrictConfigurationAwareInterface {
    public function getStrictlyConfiguredProperties(): array;
}
