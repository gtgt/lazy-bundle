<?php

namespace LazyBundle\Entity\Traits;

use Gedmo\Mapping\Annotation as Gedmo;

trait PositionTrait {

    /**
     * @var integer $position
     *
     * @Gedmo\SortablePosition()
     * @ORM\Column(name="position", type="integer")
     */
    private $position;

    /**
     * @return int
     */
    public function getPosition(): ?int {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition(?int $position) {
        $this->position = $position;
    }
}