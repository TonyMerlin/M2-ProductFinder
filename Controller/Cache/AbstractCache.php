<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Controller\Adminhtml\Cache;

use Magento\Backend\App\Action;

abstract class AbstractCache extends Action
{
    protected function _isAllowed()
    {
        // Reuse your config permission
        return $this->_authorization->isAllowed('Merlin_ProductFinder::config');
    }
}
