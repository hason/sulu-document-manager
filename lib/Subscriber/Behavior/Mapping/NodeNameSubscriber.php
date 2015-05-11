<?php

/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\DocumentManager\Subscriber\Behavior\Mapping;

use Sulu\Component\DocumentManager\Behavior\Mapping\NodeNameBehavior;
use Sulu\Component\DocumentManager\Event\AbstractMappingEvent;
use Sulu\Component\DocumentManager\Event\HydrateEvent;
use Sulu\Component\DocumentManager\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maps the node name.
 */
class NodeNameSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::HYDRATE => 'handleNodeName',
            Events::PERSIST => 'handleNodeName',
        );
    }

    /**
     * @param HydrateEvent $event
     */
    public function handleNodeName(AbstractMappingEvent $event)
    {
        $document = $event->getDocument();

        if (!$document instanceof NodeNameBehavior) {
            return false;
        }

        $node = $event->getNode();
        $accessor = $event->getAccessor();
        $accessor->set(
            'nodeName',
            $node->getName()
        );
    }
}
