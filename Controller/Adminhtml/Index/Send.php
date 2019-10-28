<?php
/**
 * EmailTester2 plugin for Magento
 *
 * @package     Yireo_EmailTester2
 * @author      Yireo (https://www.yireo.com/)
 * @copyright   Copyright 2017 Yireo (https://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

declare(strict_types=1);

namespace Yireo\EmailTester2\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use Yireo\EmailTester2\Model\Mailer;

/**
 * Class Index
 *
 * @package Yireo\EmailTester2\Controller\Index
 */
class Send extends Action
{
    /**
     * ACL resource
     */
    const ADMIN_RESOURCE = 'Yireo_EmailTester2::index';

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param Context $context
     * @param RedirectFactory $redirectFactory
     * @param MessageManager $messageManager
     * @param Mailer $mailer
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Context $context,
        RedirectFactory $redirectFactory,
        MessageManager $messageManager,
        Mailer $mailer,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($context);
        $this->redirectFactory = $redirectFactory;
        $this->mailer = $mailer;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
    }

    /**
     * Index action
     *
     * @return Redirect
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $data = $this->getRequestData();
        $this->saveToSession($data);

        $this->mailer->setData($data);
        $this->mailer->send();

        $this->messageManager->addNoticeMessage('Message sent to ' . $data['email']);
        $redirect = $this->redirectFactory->create();
        $redirect->setPath('*/*/index', ['form_id' => 0]);

        return $redirect;
    }

    /**
     * @return array
     */
    private function getRequestData(): array
    {
        $data = [];
        $data['store_id'] = (int)$this->_request->getParam('store_id');
        $data['customer_id'] = (int)$this->_request->getParam('customer_id');
        $data['product_id'] = (int)$this->_request->getParam('product_id');
        $data['order_id'] = (int)$this->_request->getParam('order_id');
        $data['template'] = (string)$this->_request->getParam('template');
        $data['email'] = (string)$this->_request->getParam('email');
        $data['sender'] = (string)$this->_request->getParam('sender');

        return $data;
    }

    /**
     * @param array $data
     */
    private function saveToSession(array $data)
    {
        $this->_session->setEmailtesterValues($data);
    }
}
