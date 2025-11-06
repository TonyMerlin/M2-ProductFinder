<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Adminhtml\Cache;

use Magento\Framework\App\CacheInterface;

class Clear extends AbstractCache
{
    public const TAG = 'MERLIN_PF';

    public function execute()
    {
        try {
            $this->_objectManager->get(CacheInterface::class)->clean([self::TAG]);
            $this->messageManager->addSuccessMessage(__('Product Finder cache cleared.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Failed to clear cache: %1', $e->getMessage()));
        }
        return $this->_redirect('adminhtml/system_config/edit', ['section' => 'merlin_productfinder']);
    }
}
