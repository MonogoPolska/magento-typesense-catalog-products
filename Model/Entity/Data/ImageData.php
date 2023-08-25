<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Entity\Data;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ProductTypeConfigurable;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;
use Monogo\TypesenseCatalogProducts\Services\ConfigService;
use Monogo\TypesenseCore\Logger\Logger;

class ImageData extends Image
{
    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param Context $context
     * @param ImageFactory $productImageFactory
     * @param Repository $assetRepo
     * @param ConfigInterface $viewConfig
     * @param Logger $logger
     * @param ConfigService $configService
     */
    public function __construct(
        Context         $context,
        ImageFactory    $productImageFactory,
        Repository      $assetRepo,
        ConfigInterface $viewConfig,
        Logger          $logger,
        ConfigService   $configService
    )
    {
        parent::__construct($context, $productImageFactory, $assetRepo, $viewConfig);
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * @param ProductCollection $products
     * @return void
     * @throws LocalizedException
     */
    public function addImageDataToCollection(ProductCollection $products): void
    {
        $products->addMediaGalleryData();
        /** @var Product $product */
        foreach ($products as $product) {
            $mediaGallery = $this->getProductImages($product);
            foreach ($mediaGallery as $key => $value) {
                $product->setData($key, $value);
            }
            $product->unsetData('media_gallery');
        }
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getProductImages(Product $product): array
    {
        $mediaGallery = [];
        try {
            if (!$product->hasData('media_gallery')) {
                $product->load('media_gallery');
            }
            $images = $product->getMediaGalleryEntries();
            if ($images) {
                foreach ($images as $image) {
                    if (!empty($image->getFile())) {
                        foreach ($image->getTypes() as $type) {
                            $imageUrl = $product->getMediaConfig()->getMediaUrl($image->getFile());
                            if ((empty($imageUrl) || $imageUrl == 'no_selection') &&
                                $product->getTypeId() == ProductTypeConfigurable::TYPE_CODE) {
                                $configurableImages = $this->getConfigurableProductImage($product, $type);
                                $imageUrl = !empty($configurableImages) ? $configurableImages : null;
                            }
                            if (is_array($imageUrl)) {
                                $mediaGallery[$type]['children'] = $imageUrl;
                            } else {
                                $mediaGallery[$type] = $imageUrl;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                'Unable to load Media Entities to product ' .
                $product->getId() .
                ' Full message: ' .
                $e->getMessage());
        }
        return $mediaGallery;
    }


    /**
     * @param Product $product
     * @param string $imageType
     * @return array
     */
    private function getConfigurableProductImage(Product $product, string $imageType): array
    {
        $childProducts = $product->getTypeInstance()->getUsedProducts($product);
        $mediaGallery = [];
        /** @var Product $childProduct */
        foreach ($childProducts as $childProduct) {
            try {
                if (!$childProduct->hasData('media_gallery')) {
                    $childProduct->load('media_gallery');
                }
                $images = $childProduct->getMediaGalleryEntries();
                if ($images) {
                    foreach ($images as $image) {
                        foreach ($image->getTypes() as $type) {
                            if ($type == $imageType && !empty($image->getFile())) {
                                $imageUrl = $childProduct->getMediaConfig()->getMediaUrl($image->getFile());
                                $mediaGallery[$childProduct->getId()] = $imageUrl;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug(
                    'Unable to load Media Entities to child product ' .
                    $product->getId() .
                    ' Full message: ' .
                    $e->getMessage());
            }
            $childProduct->unsetData('media_gallery');
        }

        return array_unique($mediaGallery);
    }

    /**
     * @param Product $product
     * @return array
     * @throws LocalizedException
     */
    public function getMediaGallery(Product $product): array
    {
        $mediaGalleryEntries = [];

        if ($product->getTypeId() == 'configurable') {
            $usedProducts = $product->getTypeInstance()->getUsedProductCollection($product)->getItems();
            foreach ($usedProducts as $usedProduct) {
//                if (in_array($usedProduct->getId(), $availableSelectionProducts)) {
                    foreach ($usedProduct->getMediaGalleryEntries() ?? [] as $key => $entry) {
                        $entry->setFile($usedProduct->getMediaConfig()->getMediaUrl($entry->getFile()));
                        $entryData = $entry->getData();
                        $initialIndex = $usedProduct->getId() . '_' . $key;
                        $index = $this->prepareIndex($entryData, $initialIndex);
                        $mediaGalleryEntries[$index] = $entryData;
                        if ($entry->getExtensionAttributes() && $entry->getExtensionAttributes()->getVideoContent()) {
                            $mediaGalleryEntries[$index]['video_content']
                                = $entry->getExtensionAttributes()->getVideoContent()->getData();
                        }
                    }
//                }
            }
        } else {
            foreach ($product->getMediaGalleryEntries() ?? [] as $key => $entry) {
                $entry->setFile($product->getMediaConfig()->getMediaUrl($entry->getFile()));
                $mediaGalleryEntries[$key] = $entry->getData();
                if ($entry->getExtensionAttributes() && $entry->getExtensionAttributes()->getVideoContent()) {
                    $mediaGalleryEntries[$key]['video_content']
                        = $entry->getExtensionAttributes()->getVideoContent()->getData();
                }
            }
        }
        return $mediaGalleryEntries;
    }

    /**
     * Formulate an index to have unique set of media entries
     *
     * @param array $entryData
     * @param string $initialIndex
     * @return string
     */
    private function prepareIndex(array $entryData, string $initialIndex) : string
    {
        $index = $initialIndex;
        if (isset($entryData['media_type'])) {
            $index = $entryData['media_type'];
        }
        if (isset($entryData['file'])) {
            $index = $index.'_'.$entryData['file'];
        }
        if (isset($entryData['position'])) {
            $index = $index.'_'.$entryData['position'];
        }
        return $index;
    }
}
