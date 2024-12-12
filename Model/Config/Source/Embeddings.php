<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Config\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;
use Monogo\TypesenseCore\Model\Config\Source\AbstractTable;

class Embeddings extends AbstractTable
{
    /**
     * @var Config
     */
    protected Config $eavConfig;

    /**
     * @var array
     */
    protected array $productAttributes = [];

    /**
     * @param Context $context
     * @param ConfigService $configService
     * @param ResourceConnection $connection
     * @param Config $eavConfig
     * @param array $data
     * @param array $additionalData
     */
    public function __construct(
        Context            $context,
        ConfigService      $configService,
        ResourceConnection $connection,
        Config             $eavConfig,
        array              $data = [],
        array              $additionalData = []
    )
    {
        parent::__construct(
            $context,
            $configService,
            $connection,
            $data,
            $additionalData
        );
        $this->eavConfig = $eavConfig;
    }

    /**
     * @return array[]
     */
    protected function getTableData(): array
    {
        return [
            'name' => [
                'label' => 'Embed from attributes',
                'values' => function () {
                    $options = [];

                    $tableFields = $this->getTableFields();
                    foreach ($tableFields as $key => $label) {
                        $options[$key] = $label;
                    }

                    return $options;
                },
            ],
        ];
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    protected function getTableFields(): array
    {
        $tableSchema = [];
        $fields = $this->connection->getConnection()->describeTable('catalog_product_entity');
        foreach ($fields as $field) {
            $tableSchema[$field['COLUMN_NAME']] = $field['COLUMN_NAME'] . ' (' . $field['DATA_TYPE'] . ')';
        }
        unset($tableSchema['entity_id']);

        return array_merge($this->getProductAttributes(), $tableSchema);
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    protected function getProductAttributes(): array
    {
        $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_product');
        $productAttributes = array_merge($allAttributes, ['product_count']);

        $excludedAttributes = [];
        $productAttributes = array_diff($productAttributes, $excludedAttributes);

        foreach ($productAttributes as $attributeCode) {
            /** @var Attribute $attribute */
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
            $this->productAttributes[$attributeCode] =
                $attributeCode . ' (' .
                str_replace('catalog_product_entity_', '', $attribute->getBackendTable()) . ')';
        }

        return $this->productAttributes;
    }
}
