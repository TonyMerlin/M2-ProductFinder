<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Observer;

use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class ClearFinderCache implements ObserverInterface
{
    private CacheFrontendPool $pool;

    public function __construct(CacheFrontendPool $pool)
    {
        $this->pool = $pool;
    }

    public function execute(Observer $observer): void
    {
        // Clean all cache frontends by our tag
        foreach ($this->pool as $frontend) {
            try {
                $frontend->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['MERLIN_PF_STOCK_OPTS']);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
