<?php
namespace Merlin\ProductFinder\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Sortable extends Field
{
    protected $_template = 'Merlin_ProductFinder::system/config/sortable.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData(['element' => $element]);
        return $this->_toHtml();
    }
}
