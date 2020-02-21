<?php
namespace LazyBundle\EntityListener;

use LazyBundle\Entity\FileEntity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Asset\Package;

class FileEntityListener implements EntityListenerInterface {
    /**
     * @var Package
     */
    private $assetsHelper;

    public function __construct($assetsHelper) {
        $this->assetsHelper = $assetsHelper;
    }

    public function postLoad(FileEntity $image, LifecycleEventArgs $args): void {
        $image->setWebView(
            $this->assetsHelper->getUrl('var/uploads/images/'.$image->getFilename())
        );

        if (!$image->getTempFilename()) {
            $image->setTempFilename($image->getFilename());
        }
    }
}