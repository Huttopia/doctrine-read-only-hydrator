<?php

namespace steevanb\DoctrineReadOnlyHydrator\EventSubscriber;

use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;
use steevanb\DoctrineReadOnlyHydrator\Hydrator\SimpleObjectHydrator;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use steevanb\DoctrineReadOnlyHydrator\Entity\ReadOnlyEntityInterface;
use steevanb\DoctrineReadOnlyHydrator\Exception\ReadOnlyEntityCantBeFlushedException;
use steevanb\DoctrineReadOnlyHydrator\Exception\ReadOnlyEntityCantBePersistedException;

class ReadOnlySubscriber
{
    /**
     * @param PrePersistEventArgs $args
     * @throws ReadOnlyEntityCantBePersistedException
     */
    public function prePersist(PrePersistEventArgs $args)
    {
        if ($this->isReadOnlyEntity($args->getObject())) {
            throw new ReadOnlyEntityCantBePersistedException($args->getObject());
        }
    }

    /**
     * @param PreFlushEventArgs $args
     * @throws ReadOnlyEntityCantBeFlushedException
     */
    public function preFlush(PreFlushEventArgs $args)
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entities = array_merge(
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions()
        );
        foreach ($entities as $entity) {
            if ($this->isReadOnlyEntity($entity)) {
                throw new ReadOnlyEntityCantBeFlushedException($entity);
            }
        }
    }

    /** @param OnClassMetadataNotFoundEventArgs $eventArgs */
    public function onClassMetadataNotFound(OnClassMetadataNotFoundEventArgs $eventArgs)
    {
        try {
            if (\in_array(
                'steevanb\\DoctrineReadOnlyHydrator\\Entity\\ReadOnlyEntityInterface',
                class_implements($eventArgs->getClassName())
            )) {
                $eventArgs->setFoundMetadata(
                    $eventArgs->getObjectManager()->getClassMetadata(get_parent_class($eventArgs->getClassName()))
                );
            }
        } catch (\Exception $exception) {}
    }

    /** @param PostLoadEventArgs $eventArgs */
    public function postLoad(PostLoadEventArgs $eventArgs)
    {
        if ($eventArgs->getObject() instanceof ReadOnlyEntityInterface) {
            // add ReadOnlyProxy to classMetada list
            // without it, you can't use Doctrine automatic id finder
            // like $queryBuilder->setParameter('foo', $foo)
            // instead of  $queryBuilder->setParameter('foo', $foo->getId())
            $eventArgs->getObjectManager()->getClassMetadata(get_class($eventArgs->getObject()));
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    protected function isReadOnlyEntity($entity)
    {
        return
            $entity instanceof ReadOnlyEntityInterface
            || isset($entity->{SimpleObjectHydrator::READ_ONLY_PROPERTY});
    }
}
