<?php
/**
 * EmailTester2 plugin for Magento
 *
 * @package     Yireo_EmailTester2
 * @author      Yireo (https://www.yireo.com/)
 * @copyright   Copyright 2017 Yireo (https://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

declare(strict_types = 1);

namespace Yireo\EmailTester2\Controller\Adminhtml\Ajax;

use \Magento\Backend\App\Action;

/**
 * Class Index
 *
 * @package Yireo\EmailTester2\Controller\Ajax
 */
class Customer extends Action
{
    const ADMIN_RESOURCE = 'Yireo_EmailTester2::index';

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    private $filterBuilder;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\App\Request\Http\Proxy $request
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\App\Request\Http\Proxy $request,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);

        $this->customerRepository = $customerRepository;
        $this->request = $request;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $customerData = [];
        $searchResults = $this->customerRepository->getList($this->loadSearchCriteria());

        foreach ($searchResults->getItems() as $customer) {
            /** @var $customer \Magento\Customer\Model\Customer */
            $customerData[] = [
                'value' => $customer->getId(),
                'label' => $this->getCustomerLabel($customer),
            ];
        }

        return $this->resultJsonFactory->create()->setData($customerData);
    }

    /**
     * @return string
     */
    private function getSearchQuery(): string
    {
        $search = $this->request->getParam('term');
        return $search;
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     *
     * @return string
     */
    private function getCustomerLabel(\Magento\Customer\Api\Data\CustomerInterface $customer): string
    {
        return $customer->getFirstname() . ' ' . $customer->getLastname() . ' [' . $customer->getEmail() . ']';
    }

    /**
     * @return \Magento\Framework\Api\SearchCriteria
     */
    private function loadSearchCriteria(): \Magento\Framework\Api\SearchCriteria
    {
        $this->searchCriteriaBuilder->setCurrentPage(0);
        $this->searchCriteriaBuilder->setPageSize(10);

        $searchFields = ['firstname', 'lastname', 'email'];
        $filters = [];
        foreach ($searchFields as $field) {
            $filters[] = $this->filterBuilder
                ->setField($field)
                ->setConditionType('like')
                ->setValue($this->getSearchQuery() . '%')
                ->create();
        }
        $this->searchCriteriaBuilder->addFilters($filters);

        return $this->searchCriteriaBuilder->create();
    }
}