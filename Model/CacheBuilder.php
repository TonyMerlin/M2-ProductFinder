<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\StoreManagerInterface;
use Merlin\ProductFinder\Block\Form;

class CacheBuilder
{
    public const TAG = 'MERLIN_PF';

    private CacheInterface $cache;
    private StoreManagerInterface $storeManager;
    private Form $formBlock;

    public function __construct(
        CacheInterface $cache,
        StoreManagerInterface $storeManager,
        Form $formBlock
    ) {
        $this->cache        = $cache;
        $this->storeManager = $storeManager;
        $this->formBlock    = $formBlock;
    }

    public static function keyForStore(int $storeId): string
    {
        return 'merlin_pf:options:set:store:' . $storeId;
    }

    /**
     * Build and store cache for a store. Returns number of sets processed.
     */
    public function buildForStore(int $storeId): int
    {
        $currentStoreId = (int)$this->storeManager->getStore()->getId();
        if ($currentStoreId !== $storeId) {
            // If needed, you can switch store here using AppState/Store emulation;
            // for simplicity we stick to the current store.
        }

        $sets     = $this->formBlock->getAllowedAttributeSets();       // [id => name]
        $profiles = $this->formBlock->getAttributeSetProfiles();       // per set profiles

        $map = $this->formBlock->getInStockOptionsBySet(array_keys($sets), $profiles);

        $key = self::keyForStore($storeId);
        $this->cache->save(
            json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $key,
            [self::TAG]
        );

        return count($sets);
    }

    /**
     * Read cache (returns array or []).
     */
    public function readForStore(int $storeId): array
    {
        $raw = $this->cache->load(self::keyForStore($storeId));
        if (!$raw) return [];
        try {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function clearForStore(int $storeId): void
    {
        $this->cache->remove(self::keyForStore($storeId));
    }
}
