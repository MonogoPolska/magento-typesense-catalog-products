<?php
declare(strict_types=1);

namespace Monogo\TypesenseCatalogProducts\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized;

class Schema extends Serialized
{
    public function beforeSave()
    {
        $values = $this->getValue();
        if (is_array($values)) {
            unset($values['__empty']);
        }

        $this->setValue($values);

        return parent::beforeSave();
    }
}
