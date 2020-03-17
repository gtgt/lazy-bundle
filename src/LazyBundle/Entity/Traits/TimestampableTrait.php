<?php

namespace LazyBundle\Entity\Traits;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use LazyBundle\Exception\ShouldNotHappenException;

/**
 * @ORM\HasLifecycleCallbacks()
 */
trait TimestampableTrait {
    /**
     * @Assert\Type(type="\DateTimeInterface")
     * @ORM\Column(type="datetime", nullable=true)
     *
     * @var \DateTimeInterface
     */
    protected $created_at;

    /**
     * @Assert\Type(type="\DateTimeInterface")
     * @ORM\Column(type="datetime", nullable=true)
     *
     * @var \DateTimeInterface
     */
    protected $updated_at;

    /**
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface {
        return $this->created_at;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface {
        return $this->updated_at;
    }

    /**
     * @param DateTimeInterface $created_at
     */
    public function setCreatedAt(DateTimeInterface $created_at): void {
        $this->created_at = $created_at;
    }

    /**
     * @param \DateTimeInterface $updated_at
     */
    public function setUpdatedAt(DateTimeInterface $updated_at): void {
        $this->updated_at = $updated_at;
    }

    /**
     * Updates createdAt and updatedAt timestamps.
     *
     * @ORM\PreFlush()
     * @ORM\PreUpdate()
     */
    public function updateTimestamps(): void {
        // Create a datetime with microseconds
        $dateTime = DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));

        if ($dateTime === false) {
            throw new ShouldNotHappenException();
        }

        $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));

        if ($this->created_at === null) {
            $this->created_at = $dateTime;
        }

        $this->updated_at = $dateTime;
    }
}