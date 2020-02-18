<?php

namespace LazyBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use LazyBundle\Exception\BadMethodCallException;

trait GeneratedIntegerIdentifiableTrait  {
    use IntegerIdentifiableTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @param $id
     * @return $this
     */
    public function setId(int $id): self {
        throw new BadMethodCallException('Id is a generated value, you shouldn\'t set it.');
    }

}
