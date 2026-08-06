<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Stores the indexer filter conditions tree and invalidates the product indexers when it changes
 */
class Conditions extends Serialized
{
    /**
     * @var string[]
     */
    const INDEXERS_TO_INVALIDATE = ['typesense_products', 'typesense_products_children'];

    /**
     * @var IndexerRegistry
     */
    private IndexerRegistry $indexerRegistry;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param IndexerRegistry $indexerRegistry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context              $context,
        Registry             $registry,
        ScopeConfigInterface $config,
        TypeListInterface    $cacheTypeList,
        IndexerRegistry      $indexerRegistry,
        ?AbstractResource    $resource = null,
        ?AbstractDb          $resourceCollection = null,
        array                $data = [],
        ?Json                $serializer = null
    )
    {
        $this->indexerRegistry = $indexerRegistry;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data,
            $serializer
        );
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        $this->setValue($this->normalizeValue($this->getValue()));

        return parent::beforeSave();
    }

    /**
     * @return $this
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->invalidateIndexers();
        }

        return parent::afterSave();
    }

    /**
     * @return $this
     */
    public function afterDelete()
    {
        $this->invalidateIndexers();

        return parent::afterDelete();
    }

    /**
     * The posted tree is keyed by the conditions prefix, which equals the field id. It is always stored under the
     * "conditions" key so that readers do not have to know about the prefix. A tree holding nothing but the root
     * combination carries no filtering intent and is stored as an empty value.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $fieldId = (string)$this->getData('field');
        $conditions = $value[$fieldId] ?? $value['conditions'] ?? [];

        if (!is_array($conditions) || count($conditions) < 2) {
            return '';
        }

        foreach ($conditions as $id => $condition) {
            if (is_array($condition)) {
                unset($conditions[$id]['new_child']);
            }
        }

        return ['conditions' => $conditions];
    }

    /**
     * @return void
     */
    private function invalidateIndexers(): void
    {
        foreach (self::INDEXERS_TO_INVALIDATE as $indexerId) {
            try {
                $this->indexerRegistry->get($indexerId)->invalidate();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}
