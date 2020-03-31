<?php

namespace LazyBundle\EntityListener;


interface EntityListenerInterface {
    /** @ORM\PrePersist */
    //public function prePersistHandler(User $user, LifecycleEventArgs $event);

    /** @ORM\PostPersist */
    //public function postPersistHandler(User $user, LifecycleEventArgs $event);

    /** @ORM\PreUpdate */
    //public function preUpdateHandler(User $user, PreUpdateEventArgs $event);

    /** @ORM\PostUpdate */
    //public function postUpdateHandler(User $user, LifecycleEventArgs $event);

    /** @ORM\PostRemove */
    //public function postRemoveHandler(User $user, LifecycleEventArgs $event);

    /** @ORM\PreRemove */
    //public function preRemoveHandler(User $user, LifecycleEventArgs $event);

    /** @ORM\PreFlush */
    //public function preFlushHandler(User $user, PreFlushEventArgs $event);

    /** @ORM\PostLoad */
    //public function postLoadHandler(User $user, LifecycleEventArgs $event);
}