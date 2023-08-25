<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\ConfigurableProductGraphQl\Model\Options\Collection as OptionCollection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\SwatchesGraphQl\Model\Resolver\Product\Options\DataProvider\SwatchDataProvider;

class OptionsData
{
    const OPTION_TYPE = 'configurable';

    /**
     * @var OptionCollection
     */
    private OptionCollection $optionCollection;

    /**
     * @var MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var SwatchDataProvider
     */
    private SwatchDataProvider $swatchDataProvider;

    /**
     * @var string
     */
    private string $linkField = "";

    /**
     * @param OptionCollection $optionCollection
     * @param MetadataPool $metadataPool
     * @param SwatchDataProvider $swatchDataProvider
     */
    public function __construct(
        OptionCollection   $optionCollection,
        MetadataPool       $metadataPool,
        SwatchDataProvider $swatchDataProvider
    )
    {
        $this->optionCollection = $optionCollection;
        $this->metadataPool = $metadataPool;
        $this->swatchDataProvider = $swatchDataProvider;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getLinkField(): string
    {
        if (empty($this->linkField)) {
            $this->linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        }
        return $this->linkField;
    }

    /**
     * @param Collection $collection
     * @return void
     * @throws \Exception
     */
    public function setProducts(Collection $collection): void
    {
        foreach ($collection as $product) {
            if ($product->getTypeId() == Type::TYPE_CODE && !empty($product->getData($this->getLinkField()))) {
                $this->optionCollection->addProductId((int)$product->getData($this->getLinkField()));
            }
        }
    }

    /**
     * @param Product $product
     * @return array
     * @throws \Exception
     */
    public function getOptions(Product $product): array
    {
        if ($product->getTypeId() !== Type::TYPE_CODE || empty($product->getData($this->getLinkField()))) {
            return [];
        }
        $this->optionCollection->addProductId((int)$product->getData($this->getLinkField()));
        $result = $this->optionCollection->getAttributesByProductId((int)$product->getData($this->getLinkField()));
        foreach ($result as $key => $item) {

            foreach ($item['values'] ?? [] as $valueId => $value) {
                $valueIndex = $result[$key]['values'][$valueId]['value_index'] ?? null;
                if ($valueIndex) {
                    $result[$key]['values'][$valueId]['swatch_data'] = $this->swatchDataProvider->getData($valueIndex);
                    $result[$key]['values'][$valueId]['uid'] = $this->getAttributeUuid($result[$key]['values'][$valueId]);
                }
            }
        }
        return $result;
    }

    /**
     * @param $value
     * @return string
     */
    public function getAttributeUuid($value): string
    {
        $optionDetails = [
            self::OPTION_TYPE,
            $value['attribute_id'] ?? 0,
            $value['value_index']
        ];
        $content = implode('/', $optionDetails);
        return base64_encode($content);
    }

}
