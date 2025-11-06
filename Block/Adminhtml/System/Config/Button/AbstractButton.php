<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Adminhtml\System\Config\Button;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

abstract class AbstractButton extends Field
{
    protected $_template = 'Merlin_ProductFinder::system/config/button.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData([
            'html_id'   => $element->getHtmlId(),
            'button_id' => $this->getButtonId(),
            'button_lbl'=> $this->getButtonLabel(),
            'action_url'=> $this->getActionUrl(),
        ]);
        return $this->_toHtml();
    }

    abstract protected function getButtonId(): string;
    abstract protected function getButtonLabel(): string;
    abstract protected function getActionUrl(): string;
}
