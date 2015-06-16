<?php

namespace Sulu\Component\DocumentManager\Subscriber\Core;

use Sulu\Component\DocumentManager\Event\HydrateEvent;
use Sulu\Component\DocumentManager\Event\PersistEvent;
use Sulu\Component\DocumentManager\Events;
use Sulu\Component\DocumentManager\MetadataFactoryInterface;
use Sulu\Component\DocumentManager\PropertyEncoder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sulu\Component\DocumentManager\DocumentAccessor;
use Sulu\Component\DocumentManager\ProxyFactory;
use PHPCR\NodeInterface;
use Sulu\Component\DocumentManager\DocumentRegistry;
use Sulu\Component\DocumentManager\Event\AbstractMappingEvent;

/**
 * This subscriber uses the field map in the metadata to map fields from
 * the PHPCR nodes to the document and vice-versa.
 */
class MappingSubscriber implements EventSubscriberInterface
{
    private $factory;
    private $encoder;
    private $proxyFactory;
    private $documentRegistry;

    /**
     * @param MetadataFactoryInterface $factory
     * @param PropertyEncoder $encoder
     * @param ProxyFactory $proxyFactory
     * @param DocumentRegistry $documentRegistry
     */
    public function __construct(
        MetadataFactoryInterface $factory,
        PropertyEncoder $encoder,
        ProxyFactory $proxyFactory,
        DocumentRegistry $documentRegistry
    ) {
        $this->factory = $factory;
        $this->encoder = $encoder;
        $this->proxyFactory = $proxyFactory;
        $this->documentRegistry = $documentRegistry;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::HYDRATE => array('handleHydrate', -100),
            Events::PERSIST => array('handlePersist', -100),
        );
    }

    /**
     * @param PersistEvent $event
     */
    public function handlePersist(PersistEvent $event)
    {
        $metadata = $this->factory->getMetadataForClass(get_class($event->getDocument()));
        $locale = $event->getLocale();
        $node = $event->getNode();
        $accessor = $event->getAccessor();

        foreach ($metadata->getFieldMappings() as $fieldName => $fieldMapping) {
            if (false === $fieldMapping['mapped']) {
                continue;
            }

            switch ($fieldMapping['type']) {
                case 'reference':
                    $this->persistReference($node, $accessor, $fieldName, $locale, $fieldMapping);
                    break;
                default:
                    $this->persistGeneric($node, $accessor, $fieldName, $locale, $fieldMapping);
            }
        }
    }

    /**
     * Persist a reference field type
     *
     * @param NodeInterface $node
     * @param DocumentAccessor $accessor
     * @param mixed $fieldName
     * @param mixed $locale
     * @param mixed $fieldMapping
     */
    private function persistReference(NodeInterface $node, DocumentAccessor $accessor, $fieldName, $locale, $fieldMapping)
    {
        $referenceDocument = $accessor->get($fieldName);

        if (!$referenceDocument) {
            return;
        }

        if ($fieldMapping['multiple']) {
            throw new \InvalidArgumentException(sprintf(
                'Mapping references as multiple not currently supported (when mapping "%s"',
                $fieldName
            ));
        }

        $referenceNode = $this->documentRegistry->getNodeForDocument($referenceDocument);
        $phpcrName = $this->encoder->encode($fieldMapping['encoding'], $fieldMapping['property'], $locale);
        $node->setProperty($phpcrName, $referenceNode);
    }

    /**
     * Persist "scalar" field types.
     *
     * @param NodeInterface $node
     * @param DocumentAccessor $accessor
     * @param mixed $fieldName
     * @param mixed $locale
     * @param array $fieldMapping
     */
    private function persistGeneric(NodeInterface $node, DocumentAccessor $accessor, $fieldName, $locale, array $fieldMapping)
    {
        $phpcrName = $this->encoder->encode($fieldMapping['encoding'], $fieldMapping['property'], $locale);
        $value = $accessor->get($fieldName);
        $this->validateFieldValue($value, $fieldName, $fieldMapping);
        $node->setProperty($phpcrName, $value);
    }

    /**
     * @param AbstractMappingEvent $event
     */
    public function handleHydrate(AbstractMappingEvent $event)
    {
        $class = get_class($event->getDocument());

        // TODO: Return false here in case this is for instance an UnknownDocument.
        //       But we should probably map the UnknownDocument and let an Exception be
        //       thrown in other cases.
        if (false === $this->factory->hasMetadataForClass($class)) {
            return;
        }

        $metadata = $this->factory->getMetadataForClass($class);
        $locale = $event->getLocale();
        $node = $event->getNode();
        $accessor = $event->getAccessor();
        $document = $event->getDocument();

        foreach ($metadata->getFieldMappings() as $fieldName => $fieldMapping) {
            if (false === $fieldMapping['mapped']) {
                continue;
            }

            switch ($fieldMapping['type']) {
                case 'reference':
                    $this->hydrateReferenceField($node, $document, $accessor, $fieldName, $locale, $fieldMapping);
                    break;
                default:
                    $this->hydrateGenericField($node, $accessor, $fieldName, $locale, $fieldMapping);
            }
        }
    }

    /**
     * Hydrate reference field types
     *
     * @param NodeInterface $node
     * @param mixed $document
     * @param DocumentAccessor $accessor
     * @param mixed $fieldName
     * @param mixed $locale
     * @param array $fieldMapping
     */
    private function hydrateReferenceField(NodeInterface $node, $document, DocumentAccessor $accessor, $fieldName, $locale, array $fieldMapping)
    {
        $referencedNode = $node->getPropertyValueWithDefault(
            $this->encoder->encode($fieldMapping['encoding'], $fieldMapping['property'], $locale),
            $this->getDefaultValue($fieldMapping)
        );

        if ($referencedNode) {
            $accessor->set(
                $fieldName,
                $this->proxyFactory->createProxyForNode($document, $referencedNode)
            );
        }
    }

    /**
     * Hydrate "scalar" field types
     *
     * @param NodeInterface $node
     * @param DocumentAccessor $accessor
     * @param mixed $fieldName
     * @param mixed $locale
     * @param array $fieldMapping
     */
    private function hydrateGenericField(NodeInterface $node, DocumentAccessor $accessor, $fieldName, $locale, array $fieldMapping)
    {
        $phpcrName = $this->encoder->encode($fieldMapping['encoding'], $fieldMapping['property'], $locale);
        $value = $node->getPropertyValueWithDefault(
            $phpcrName,
            $this->getDefaultValue($fieldMapping)
        );
        $accessor->set($fieldName, $value);
    }

    private function getDefaultValue(array $fieldMapping)
    {
        return $fieldMapping['multiple'] ? array() : null;
    }

    private function validateFieldValue($value, $fieldName, $fieldMapping)
    {
        if ($fieldMapping['multiple'] && !is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s" is mapped as multiple, and therefore must be an array, got "%s"',
                $fieldName, is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }
}