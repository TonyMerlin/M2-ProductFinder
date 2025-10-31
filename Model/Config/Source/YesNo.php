<?php
namespace Merlin\ProductFinder\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class YesNo implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => __('Yes')],
            ['value' => 0, 'label' => __('No')]
        ];
    }
}
