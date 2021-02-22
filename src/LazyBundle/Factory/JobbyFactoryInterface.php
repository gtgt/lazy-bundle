<?php
namespace LazyBundle\Factory;

use LazyBundle\Util\Jobby;

interface JobbyFactoryInterface {
    public function generate(): Jobby;
}
