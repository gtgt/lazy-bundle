<?php
namespace LazyBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait IdentifiableTrait {
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="string", length=8)
     *
     * @var string|int
     */
    protected $id;

    /**
     * @return int|string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id): self {
        $this->id = $id;
        return $this;
    }
}
