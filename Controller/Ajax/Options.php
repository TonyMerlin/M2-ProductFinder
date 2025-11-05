<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Eav\Model\Config as EavConfig;

class Options extends Action
{
    private JsonFactory $resultJsonFactory;
    private EavConfig $eavConfig;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        EavConfig $eavConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->eavConfig = $eavConfig;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $code = (string)$this->getRequest()->getParam('code');
        if ($code === '') {
            return $result->setData(['ok' => false, 'options' => []]);
        }

        $attr = $this->eavConfig->getAttribute('catalog_product', $code);
        if (!$attr || !$attr->getId()) {
            return $result->setData(['ok' => false, 'options' => []]);
        }

        $opts = $attr->getSource()->getAllOptions(false);
        $out  = [];
        foreach ($opts as $o) {
            if ($o['value'] === '' || $o['value'] === null) {
                continue;
            }
            $out[] = ['value' => (string)$o['value'], 'label' => (string)$o['label']];
        }

        return $result->setData(['ok' => true, 'options' => $out]);
    }
}
