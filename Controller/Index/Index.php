<?php
namespace Merlin\ProductFinder\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Merlin\ProductFinder\Helper\Data as Helper;

class Index extends Action
{
    private PageFactory $pageFactory;
    private Helper $helper;

    public function __construct(Context $context, PageFactory $pageFactory, Helper $helper)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->helper = $helper;
    }

    public function execute()
    {
        if (!$this->helper->isEnabled()) {
            return $this->_redirect('/');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Product Finder'));
        return $page;
    }
}
