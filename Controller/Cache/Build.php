<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Adminhtml\Cache;

use Magento\Store\Model\StoreManagerInterface;
use Merlin\ProductFinder\Model\CacheBuilder;

class Build extends AbstractCache
{
    public function execute()
    {
        try {
            /** @var CacheBuilder $builder */
            $builder = $this->_objectManager->get(CacheBuilder::class);
            $storeId = (int)$this->_objectManager->get(StoreManagerInterface::class)->getStore()->getId();

            $count = $builder->buildForStore($storeId);
            $this->messageManager->addSuccessMessage(
                __('Product Finder cache built for store #%1 (%2 sets).', $storeId, $count)
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Failed to build cache: %1', $e->getMessage()));
        }
        return $this->_redirect('adminhtml/system_config/edit', ['section' => 'merlin_productfinder']);
    }
}
