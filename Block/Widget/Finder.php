<?php
declare(strict_types=1);

namespace Merlin\ProductFinder\Block\Widget;

use Magento\Widget\Block\BlockInterface;
use Merlin\ProductFinder\Block\Form as BaseForm;
use Merlin\ProductFinder\Helper\Data as Helper;

class Finder extends BaseForm implements BlockInterface
{
    protected $_template = 'Merlin_ProductFinder::form.phtml';

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

    public function getSections(): array
    {
        $override = (string)($this->getData('sections_override') ?? '');
        if ($override) {
            $arr = json_decode($override, true);
            if (is_array($arr) && $arr) {
                return $arr;
            }
        }
        return parent::getSections();
    }
}
