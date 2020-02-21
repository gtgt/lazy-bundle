<?php
namespace LazyBundle\EntityListener\EasyAdmin;

use LazyBundle\Entity\FileEntity;
use LazyBundle\Service\FileUploader;
use Symfony\Component\EventDispatcher\GenericEvent;

class FileEntityListener {
    /**
     * @var FileEntity
     */
    private $entity;

    /**
     * @var FileUploader
     */
    private $fileUploader;

    /**
     * @var string
     */
    private $uploadPath;

    /**
     * FileEntityListener constructor.
     *
     * @param FileUploader $fileUploader
     * @param string $fileEntityUploadPath
     */
    public function __construct(FileUploader $fileUploader, string $fileEntityUploadPath) {
        $this->fileUploader = $fileUploader;
        $this->uploadPath = $fileEntityUploadPath;
    }

    /**
     * @param GenericEvent $event
     */
    public function onEasyAdminPrePersist(GenericEvent $event): void {
        if (!$event->getSubject() instanceof FileEntity) {
            return;
        }

        $this->entity = $event->getSubject();
        $filename = $this->fileUploader->upload($this->entity->getFile(), $this->uploadPath);
        $this->entity->setFilename($filename);
    }

    /**
     * @param GenericEvent $event
     */
    public function onEasyAdminPreUpdate(GenericEvent $event): void {
        if (!$event->getSubject() instanceof FileEntity) {
            return;
        }

        $this->entity = $event->getSubject();
        $filename = $this->fileUploader->upload($this->entity->getFile(), $this->uploadPath);

        $this->entity->setFilename($filename);
        unlink($this->uploadPath.'/'.$this->entity->getTempFilename());
    }

    /**
     * @param GenericEvent $event
     */
    public function onEasyAdminPreRemove(GenericEvent $event): void {
        if (!$event->getSubject() instanceof FileEntity) {
            return;
        }

        $this->entity = $event->getSubject();
        unlink($this->uploadPath.'/'.$this->entity->getTempFilename());
    }
}