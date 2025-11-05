<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Widget;

use Magento\Widget\Block\BlockInterface;
use Merlin\ProductFinder\Block\Form as BaseForm;

class Finder extends BaseForm implements BlockInterface
{
    protected $_template = 'Merlin_ProductFinder::form.phtml';

    protected function _construct()
    {
        parent::_construct();
        // Disable caching so profiles/options reflect current config/scope
        $this->setData('cache_lifetime', null);
    }

    public function getTitle(): string
    {
        return (string)($this->getData('title') ?? '');
    }

    public function showPrePost(): bool
    {
        $v = $this->getData('show_pre_post');
        if ($v === null || $v === '') {
            return true;
        }
        return (bool)$v;
    }

    public function getPreHtml(): string
    {
        return $this->showPrePost() ? parent::getPreHtml() : '';
    }

    public function getPostHtml(): string
    {
        return $this->showPrePost() ? parent::getPostHtml() : '';
    }
}
