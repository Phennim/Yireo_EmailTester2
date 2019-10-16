<?php
/**
 * Yireo EmailTester for Magento
 *
 * @package     Yireo_EmailTester
 * @author      Yireo (https://www.yireo.com/)
 * @copyright   Copyright 2017 Yireo (https://www.yireo.com/)
 * @license     Open Source License (OSL v3)
 */

declare(strict_types=1);

namespace Yireo\EmailTester2\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\FactoryInterface as TemplateFactoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Email\Model\BackendTemplateFactory;
use Magento\Framework\Mail\TemplateInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Email\Model\Template\Config as TemplateConfig;
use Magento\Store\Model\StoreManagerInterface;
use Exception;
use Yireo\EmailTester2\Behaviour\Errorable;
use Yireo\EmailTester2\Model\Mailer\Addressee;
use Yireo\EmailTester2\Model\Mailer\Recipient;

/**
 * EmailTester Core model
 */
class Mailer extends DataObject
{
    /**
     * Include the behaviour of handling errors
     */
    use Errorable;

    /**
     * @var Mailer\AddresseeFactory
     */
    private $addresseeFactory;

    /**
     * @var Mailer\RecipientFactory
     */
    private $recipientFactory;

    /**
     * @var Mailer\VariableBuilder
     */
    private $variableBuilder;

    /**
     * @var  TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var TemplateFactoryInterface
     */
    private $templateFactory;

    /**
     * @var TemplateConfig
     */
    private $templateConfig;

    /**
     * @var BackendTemplateFactory
     */
    private $backendTemplateFactory;

    /**
     * Mailer constructor.
     *
     * @param Mailer\AddresseeFactory $addresseeFactory
     * @param Mailer\RecipientFactory $recipientFactory
     * @param Mailer\VariableBuilder $variableBuilder
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param StateInterface $inlineTranslation
     * @param ManagerInterface $eventManager
     * @param TemplateFactoryInterface $templateFactory
     * @param BackendTemplateFactory $backendTemplateFactory
     * @param TemplateConfig $templateConfig
     * @param array $data
     */
    public function __construct(
        Mailer\AddresseeFactory $addresseeFactory,
        Mailer\RecipientFactory $recipientFactory,
        Mailer\VariableBuilder $variableBuilder,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        StateInterface $inlineTranslation,
        ManagerInterface $eventManager,
        TemplateFactoryInterface $templateFactory,
        BackendTemplateFactory $backendTemplateFactory,
        TemplateConfig $templateConfig,
        array $data = []
    ) {
        parent::__construct($data);
        $this->addresseeFactory = $addresseeFactory;
        $this->recipientFactory = $recipientFactory;
        $this->variableBuilder = $variableBuilder;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->inlineTranslation = $inlineTranslation;
        $this->eventManager = $eventManager;
        $this->templateFactory = $templateFactory;
        $this->templateConfig = $templateConfig;
        $this->backendTemplateFactory = $backendTemplateFactory;
    }

    /**
     * Output the email
     *
     * @return string
     * @throws Exception
     */
    public function getHtml(): string
    {
        $this->prepare();

        return $this->getRawContentFromTransportBuilder();
    }

    /**
     * Send the email
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function send(): bool
    {
        $this->prepare();
        $transport = $this->transportBuilder->getTransport();

        try {
            $transport->sendMessage();
            $sent = true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            $sent = false;
        }

        if ($sent === false) {
            $this->processMailerErrors();
            return false;
        }

        return true;
    }

    /**
     *
     * @return string
     */
    protected function getRawContentFromTransportBuilder(): string
    {
        /** @var \Zend\Mime\Message $body */
        $message = $this->transportBuilder->getMessage();
        $body = $message->getBody();

        if (is_string($body)) {
            return $body;
        }

        if (method_exists($body, 'getRawContent')) {
            return $body->getRawContent();
        }

        $content = '';
        $parts = $body->getParts();
        foreach ($parts as $part) {
            $part->setEncoding('');
            $content .= $part->getContent();
        }

        return $this->cleanContent($content);
    }

    /**
     * @param string $content
     * @return string
     */
    private function cleanContent(string $content): string
    {
        return quoted_printable_decode($content);
    }

    /**
     *
     */
    private function processMailerErrors()
    {
        if ($this->scopeConfig->getValue('system/smtp/disable')) {
            $this->addError('SMTP is disabled');
        }

        if (!$this->hasErrors()) {
            $this->addError('Check your logs for unknown error');
        }
    }

    /**
     * @return Recipient
     * @throws LocalizedException
     */
    private function getRecipient(): Recipient
    {
        $data = [
            'customer_id' => $this->getData('customer_id'),
            'email' => $this->getData('email'),
        ];

        return $this->recipientFactory->create($data);
    }

    /**
     * Prepare for the main action
     *
     * @throws NoSuchEntityException
     */
    private function prepare()
    {
        $this->setDefaultStoreId();

        $this->inlineTranslation->suspend();
        $this->prepareTransportBuilder();
        $this->inlineTranslation->resume();
    }

    /**
     * Prepare the transport builder
     */
    private function prepareTransportBuilder()
    {
        /** @var Addressee $sender */
        $sender = $this->addresseeFactory->create();

        $recipient = $this->getRecipient();
        $templateId = $this->getData('template');
        $storeId = $this->getStoreId();
        $variables = $this->buildVariables();

        if (preg_match('/^([^\/]+)\/(.*)$/', $templateId, $match)) {
            $templateId = $match[1];
        }

        $template = $this->loadTemplate($templateId);

        $variables['subject'] = $template->getSubject();

        if ($this->matchTemplate($template, 'checkout_payment_failed_template')) {
            $variables['customer'] = $variables['customerName'];
        }

        $this->dispatchEventEmailOrderSetTemplateVarsBefore($variables, $sender);
        $this->dispatchEventEmailtesterVariables($variables);
        $this->dispatchEventEmailShipmentSetTemplateVarsBefore($variables);

        $area = Area::AREA_FRONTEND;
        if (!preg_match('/^([0-9]+)$/', $templateId)) {
            $area = $this->templateConfig->getTemplateArea($templateId);
        }

        $this->transportBuilder->setTemplateIdentifier($template->getId())
            ->setTemplateOptions(['area' => $area, 'store' => $storeId])
            ->setTemplateVars($variables)
            ->setFrom($sender->getAsArray())
            ->addTo($recipient->getEmail(), $recipient->getName());
    }

    /**
     * @param array $variables
     * @param $sender
     */
    private function dispatchEventEmailOrderSetTemplateVarsBefore(array &$variables, $sender)
    {
        $eventTransport = new DataObject($variables);
        $this->eventManager->dispatch(
            'email_order_set_template_vars_before',
            [
                'sender' => $sender,
                'transport' => $eventTransport,
                'transportObject' => $eventTransport,
            ]
        );
    }

    /**
     * @param array $variables
     */
    private function dispatchEventEmailtesterVariables(array &$variables)
    {
        $this->eventManager->dispatch(
            'emailtester_variables',
            ['variables' => &$variables]
        );
    }

    /**
     * @param array $variables
     */
    private function dispatchEventEmailShipmentSetTemplateVarsBefore(array &$variables)
    {
        $transport = new DataObject($variables);
        $this->eventManager->dispatch(
            'email_shipment_set_template_vars_before',
            ['sender' => $this, 'transport' => $variables, 'transportObject' => $transport]
        );

        $variables = $transport->getData();
    }

    /**
     * @param mixed $templateId
     *
     * @return TemplateInterface
     */
    private function loadTemplate($templateId): TemplateInterface
    {
        if (preg_match('/^([0-9]+)$/', $templateId)) {
            $template = $this->backendTemplateFactory->create();
            $template->load($templateId);
            return $template;
        }

        $template = $this->templateFactory->get($templateId);
        return $template;
    }

    /**
     * @param TemplateInterface $template
     * @param string $name
     *
     * @return bool
     */
    private function matchTemplate(TemplateInterface $template, string $name): bool
    {
        if ($template->getId() === $name) {
            return true;
        }

        if ($template->getOrigTemplateCode() === $name) {
            return true;
        }

        return false;
    }

    /**
     * Make sure a valid store ID is set
     *
     * @throws NoSuchEntityException
     */
    private function setDefaultStoreId()
    {
        $storeId = $this->getStoreId();

        if (empty($storeId)) {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $this->setStoreId($storeId);
        }
    }

    /**
     * Collect all variables to insert into the email template
     *
     * @return array
     */
    private function buildVariables(): array
    {
        $variableBuilder = $this->variableBuilder;
        $data = $this->getData();
        $variableBuilder->setData($data);
        $variables = $variableBuilder->getVariables();

        return $variables;
    }

    /**
     * @return int
     */
    private function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    /**
     * @param int $storeId
     */
    private function setStoreId(int $storeId)
    {
        $this->setData('store_id', $storeId);
    }
}
