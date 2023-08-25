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

class Schema extends AbstractTable
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
                'label' => 'Fields',
                'values' => function () {
                    $options = [];

                    $tableFields = $this->getTableFields();
                    foreach ($tableFields as $key => $label) {
                        $options[$key] = $label;
                    }

                    return $options;
                },
            ],
            'type' => [
                'label' => __('Field type'),
                'values' => [
                    'string' => __('String values'),
                    'string[]' => __('Array of strings'),
                    'int32' => __('Integer values up to 2,147,483,647'),
                    'int32[]' => __('Array of int32'),
                    'int64' => __('Integer values larger than 2,147,483,647'),
                    'int64[]' => __('Array of int64'),
                    'float' => __('Floating point / decimal numbers'),
                    'float[]' => __('Array of floating point / decimal numbers'),
                    'bool' => __('true or false'),
                    'bool[]' => __('Array of booleans'),
                    'geopoint' => __('Latitude and longitude specified as [lat, lng]'),
                    'geopoint[]' => __('Arrays of Latitude and longitude specified as [[lat1, lng1], [lat2, lng2]]'),
                    'object' => __('Nested objects'),
                    'object[]' => __('Arrays of nested objects'),
                    'string*' => __('Special type that automatically converts values to a string or string[]'),
                    'auto' => __('Special type that automatically attempts to infer the data type based on the documents added to the collection'),
                ],
            ],
            'facet' => [
                'label' => __('Facet'),
                'values' => [0 => __('No'), 1 => __('Yes')],
            ],
            'optional' => [
                'label' => __('Is optional'),
                'values' => [0 => __('No'), 1 => __('Yes')],
            ],
            'index' => [
                'label' => __('Index field'),
                'values' => [0 => __('No'), 1 => __('Yes')],
            ],
            'infix' => [
                'label' => __('Infix Search'),
                'values' => [0 => __('No'), 1 => __('Yes')],
            ],
            'sort' => [
                'label' => __('Sort field'),
                'values' => [0 => __('No'), 1 => __('Yes')],
            ],
            'locale' => [
                'label' => __('Locale'),
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
