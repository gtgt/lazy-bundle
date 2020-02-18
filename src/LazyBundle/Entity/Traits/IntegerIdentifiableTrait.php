<?php

namespace LazyBundle\Entity\Traits;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\ORM\Mapping as ORM;

trait IntegerIdentifiableTrait  {
    use IdentifiableTrait {
        setId as parentSetId;
    }

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="integer")
     * @Required()
     *
     * @var int|null
     */
    protected $id;

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId(int $id): self {
        return $this->parentSetId($id);
    }

}
