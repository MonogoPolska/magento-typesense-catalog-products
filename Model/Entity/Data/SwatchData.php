<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\Swatches\Helper\Data as SwatchesHelperData;
use Magento\Swatches\Helper\Media as SwatchesMediaHelper;
use Magento\Swatches\Model\Swatch;

class SwatchData
{
    /**
     * @var string
     */
    public const HEX_SUFFIX = '_swatch_hex';

    /**
     * @var string
     */
    public const IMAGE_SUFFIX = '_swatch_image';

    /**
     * @var SwatchesHelperData
     */
    protected SwatchesHelperData $swatchesHelperData;

    /**
     * @var SwatchesMediaHelper
     */
    protected SwatchesMediaHelper $swatchesMediaHelper;

    /**
     * @param SwatchesHelperData $swatchesHelperData
     * @param SwatchesMediaHelper $swatchesMediaHelper
     */
    public function __construct(
        SwatchesHelperData  $swatchesHelperData,
        SwatchesMediaHelper $swatchesMediaHelper
    )
    {
        $this->swatchesHelperData = $swatchesHelperData;
        $this->swatchesMediaHelper = $swatchesMediaHelper;
    }

    /**
     * @param AttributeResource $attributeResource
     * @return bool
     */
    public function isSwatchAttribute(AttributeResource $attributeResource): bool
    {
        return $this->swatchesHelperData->isSwatchAttribute($attributeResource);
    }

    /**
     * Adds {attribute}_swatch_hex and/or {attribute}_swatch_image next to the indexed option labels.
     *
     * @param array $productData
     * @param array $attribute
     * @param AttributeResource $attributeResource
     * @param mixed $rawValue
     * @return void
     */
    public function addSwatchData(
        array             &$productData,
        array             $attribute,
        AttributeResource $attributeResource,
        mixed             $rawValue
    ): void
    {
        if (!$this->isSwatchAttribute($attributeResource)) {
            return;
        }

        $hexValues = [];
        $imageValues = [];

        foreach ($this->getSwatches($rawValue) as $swatch) {
            $value = (string)($swatch['value'] ?? '');
            if ($value === '') {
                continue;
            }

            switch ((int)($swatch['type'] ?? Swatch::SWATCH_TYPE_EMPTY)) {
                case Swatch::SWATCH_TYPE_VISUAL_COLOR:
                    $hexValues[] = $value;
                    break;
                case Swatch::SWATCH_TYPE_VISUAL_IMAGE:
                    $imageValues[] = $this->swatchesMediaHelper->getSwatchMediaUrl() . $value;
                    break;
            }
        }

        $indexAsArray = str_contains($attribute['type'], '[]');
        $this->setSwatchValues($productData, $attribute['name'] . self::HEX_SUFFIX, $hexValues, $indexAsArray);
        $this->setSwatchValues($productData, $attribute['name'] . self::IMAGE_SUFFIX, $imageValues, $indexAsArray);
    }

    /**
     * @param array $productData
     * @param string $field
     * @param array $values
     * @param bool $indexAsArray
     * @return void
     */
    protected function setSwatchValues(array &$productData, string $field, array $values, bool $indexAsArray): void
    {
        if (empty($values)) {
            return;
        }

        $values = array_values(array_unique($values));
        $productData[$field] = $indexAsArray ? $values : reset($values);
    }

    /**
     * @param mixed $rawValue
     * @return array
     */
    protected function getSwatches(mixed $rawValue): array
    {
        $optionIds = $this->extractOptionIds($rawValue);
        if (empty($optionIds)) {
            return [];
        }

        return $this->swatchesHelperData->getSwatchesByOptionsId($optionIds);
    }

    /**
     * Option ids are stored as a single id or as a comma separated list for multiselect attributes.
     *
     * @param mixed $rawValue
     * @return array
     */
    protected function extractOptionIds(mixed $rawValue): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        $optionIds = [];
        foreach (is_array($rawValue) ? $rawValue : [$rawValue] as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            foreach (explode(',', (string)$value) as $optionId) {
                $optionId = trim($optionId);
                if ($optionId !== '' && ctype_digit($optionId)) {
                    $optionIds[] = $optionId;
                }
            }
        }

        return array_values(array_unique($optionIds));
    }
}
