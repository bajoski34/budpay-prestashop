<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Budpay extends PaymentModule
{
    const CONFIG_PO_BUDPAY_ENABLED = 'BUDPAY_OS_CHECKOUT';
    const GO_LIVE = 'BUDPAY_GO_LIVE';
    const SECRET_KEY_TEST = 'BUDPAY_SECRET_KEY_TEST';
    const PUBLIC_KEY_TEST = 'BUDPAY_PUBLIC_KEY_TEST';
    const SECRET_KEY_LIVE = 'BUDPAY_SECRET_KEY_LIVE';
    const PUBLIC_KEY_LIVE = 'BUDPAY_PUBLIC_KEY_LIVE';
    const MODULE_ADMIN_CONTROLLER = 'AdminConfigureBudpay';
    const HOOKS = [
        'actionObjectShopAddAfter',
        'paymentOptions',
        'displayAdminOrderLeft',
        'displayAdminOrderMainBottom',
        'displayCustomerAccount',
        'displayOrderConfirmation',
        'displayOrderDetail',
        'displayPaymentReturn',
        'displayPDFInvoice',
    ];

    public function __construct()
    {
        $this->name = 'budpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'BudpayDevelopers';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->controllers = [
            'account',
            'cancel',
            'external',
            'validation',
        ];

        parent::__construct();

        $this->displayName = $this->l('Budpay Payments');
        $this->description = $this->l('Accept payment on your Store with Budpay.');
    }

    /**
     * @return bool
     */
    public function install()
    {
        return (bool) parent::install()
            && (bool) $this->registerHook(static::HOOKS)
            && $this->installOrderState()
            && $this->installConfiguration()
            && $this->installTabs();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return (bool) parent::uninstall()
            && $this->deleteOrderState()
            && $this->uninstallConfiguration()
            && $this->uninstallTabs();
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        // Redirect to our ModuleAdminController when click on Configure button
        Tools::redirectAdmin($this->context->link->getAdminLink(static::MODULE_ADMIN_CONTROLLER));
    }

    /**
     * This hook called after a new Shop is created
     *
     * @param array $params
     */
    public function hookActionObjectShopAddAfter(array $params)
    {
        if (empty($params['object'])) {
            return;
        }

        /** @var Shop $shop */
        $shop = $params['object'];

        if (false === Validate::isLoadedObject($shop)) {
            return;
        }

        $this->addCheckboxCarrierRestrictionsForModule([(int) $shop->id]);
        $this->addCheckboxCountryRestrictionsForModule([(int) $shop->id]);

        if ($this->currencies_mode === 'checkbox') {
            $this->addCheckboxCurrencyRestrictionsForModule([(int) $shop->id]);
        } elseif ($this->currencies_mode === 'radio') {
            $this->addRadioCurrencyRestrictionsForModule([(int) $shop->id]);
        }
    }

    /**
     * @param array $params
     *
     * @return array Should always return an array
     */
    public function hookPaymentOptions(array $params)
    {
        if (!$this->active) {
            return;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];

        if (false === Validate::isLoadedObject($cart) || false === $this->checkCurrency($cart)) {
            return [];
        }

        $paymentOptions = [];

        $paymentOptions[] = $this->getEmbeddedPaymentOption();

        return $paymentOptions;
    }

    public function getConfigFieldsValues()
    {
        return array(
            'SECRET_KEY_TEST' => Tools::getValue(self::SECRET_KEY_TEST, Configuration::get(self::SECRET_KEY_TEST)),
            'PUBLIC_KEY_TEST' => Tools::getValue(self::PUBLIC_KEY_TEST, Configuration::get(self::PUBLIC_KEY_TEST)),
            'PUBLIC_KEY_LIVE' => Tools::getValue(self::PUBLIC_KEY_LIVE, Configuration::get(self::PUBLIC_KEY_LIVE)),
            'SECRET_KEY_LIVE' => Tools::getValue(self::SECRET_KEY_LIVE, Configuration::get(self::SECRET_KEY_LIVE)),
            'GO_LIVE' => Tools::getValue(self::GO_LIVE, Configuration::get(self::GO_LIVE))
        );
    }

    /**
     * This hook is used to display additional information on BO Order View, under Payment block
     *
     * @since PrestaShop 1.7.7 This hook is replaced by displayAdminOrderMainBottom on migrated BO Order View
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderLeft(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayAdminOrderLeft.tpl');
    }

    /**
     * This hook is used to display additional information on BO Order View, under Payment block
     *
     * @since PrestaShop 1.7.7 This hook replace displayAdminOrderLeft on migrated BO Order View
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderMainBottom(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayAdminOrderMainBottom.tpl');
    }

    /**
     * This hook is used to display information in customer account
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCustomerAccount(array $params)
    {
        $this->context->smarty->assign([
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
            'transactionsLink' => $this->context->link->getModuleLink(
                $this->name,
                'account'
            ),
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayCustomerAccount.tpl');
    }

    /**
     * This hook is used to display additional information on order confirmation page
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayOrderConfirmation.tpl');
    }

    /**
     * This hook is used to display additional information on FO (Guest Tracking and Account Orders)
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderDetail(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayOrderDetail.tpl');
    }

    /**
     * This hook is used to display additional information on bottom of order confirmation page
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPaymentReturn(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
            'transactionsLink' => $this->context->link->getModuleLink(
                $this->name,
                'account'
            ),
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayPaymentReturn.tpl');
    }

    /**
     * This hook is used to display additional information on Invoice PDF
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPDFInvoice(array $params)
    {
        if (empty($params['object'])) {
            return '';
        }

        /** @var OrderInvoice $orderInvoice */
        $orderInvoice = $params['object'];

        if (false === Validate::isLoadedObject($orderInvoice)) {
            return '';
        }

        $order = $orderInvoice->getOrder();

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/hook/displayPDFInvoice.tpl');
    }

    /**
     * Check if currency is allowed in Payment Preferences
     *
     * @param Cart $cart
     *
     * @return bool
     */
    private function checkCurrency(Cart $cart)
    {
        $currency_order = new Currency($cart->id_currency);
        /** @var array $currencies_module */
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (empty($currencies_module)) {
            return false;
        }

        foreach ($currencies_module as $currency_module) {
            if ($currency_order->id == $currency_module['id_currency']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Factory of PaymentOption for External Payment
     *
     * @return PaymentOption
     */
    private function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setModuleName($this->name);
        $externalOption->setCallToActionText($this->l('Pay external'));
        $externalOption->setAction($this->context->link->getModuleLink($this->name, 'external', [], true));
        $externalOption->setInputs([
            'token' => [
                'name' => 'token',
                'type' => 'hidden',
                'value' => '[5cbfniD+(gEV<59lYbG/,3VmHiE<U46;#G9*#NP#X.FA§]sb%ZG?5Q{xQ4#VM|7',
            ],
        ]);
        $externalOption->setAdditionalInformation($this->context->smarty->fetch('module:budpay/views/templates/front/paymentOptionExternal.tpl'));
        $externalOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/external.png'));

        return $externalOption;
    }

    /**
     * Factory of PaymentOption for Embedded Payment
     *
     * @return PaymentOption
     */
    private function getEmbeddedPaymentOption()
    {
        $checkoutOption = new PaymentOption();
        $checkoutOption->setModuleName($this->name);
        $checkoutOption->setCallToActionText($this->l('Proceed to BudPay'));
        $checkoutOption->setForm($this->generateEmbeddedForm());
        $checkoutOption->setAdditionalInformation($this->context->smarty->fetch('module:budpay/views/templates/front/paymentOptionEmbedded.tpl'));
        $checkoutOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/budpay.png'));
        
        return $checkoutOption;
    }

    /**
     * Generate a form for Embedded Payment.
     *
     * @return string
     */
    private function generateEmbeddedForm()
    {
        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', ['option' => 'embedded'], true),
        ]);

        return $this->context->smarty->fetch('module:budpay/views/templates/front/paymentOptionEmbeddedForm.tpl');
    }

    /**
     * @return bool
     */
    private function installOrderState()
    {
        return $this->createOrderState(
            static::CONFIG_PO_BUDPAY_ENABLED,
            [
                'en' => 'Awaiting Budpay payment',
            ],
            '#00ffff',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            true,
            'awaiting-budpay-payment'
        );
    }

    /**
     * Create custom OrderState used for payment
     *
     * @param string $configurationKey Configuration key used to store OrderState identifier
     * @param array $nameByLangIsoCode An array of name for all languages, default is en
     * @param string $color Color of the label
     * @param bool $isLogable consider the associated order as validated
     * @param bool $isPaid set the order as paid
     * @param bool $isInvoice allow a customer to download and view PDF versions of his/her invoices
     * @param bool $isShipped set the order as shipped
     * @param bool $isDelivery show delivery PDF
     * @param bool $isPdfDelivery attach delivery slip PDF to email
     * @param bool $isPdfInvoice attach invoice PDF to email
     * @param bool $isSendEmail send an email to the customer when his/her order status has changed
     * @param string $template Only letters, numbers and underscores are allowed. Email template for both .html and .txt
     * @param bool $isHidden hide this status in all customer orders
     * @param bool $isUnremovable Disallow delete action for this OrderState
     * @param bool $isDeleted Set OrderState deleted
     *
     * @return bool
     */
    private function createOrderState(
        $configurationKey,
        array $nameByLangIsoCode,
        $color,
        $isLogable = false,
        $isPaid = false,
        $isInvoice = false,
        $isShipped = false,
        $isDelivery = false,
        $isPdfDelivery = false,
        $isPdfInvoice = false,
        $isSendEmail = false,
        $template = '',
        $isHidden = false,
        $isUnremovable = true,
        $isDeleted = false
    ) {
        $tabNameByLangId = [];

        foreach ($nameByLangIsoCode as $langIsoCode => $name) {
            foreach (Language::getLanguages(false) as $language) {
                if (Tools::strtolower($language['iso_code']) === $langIsoCode) {
                    $tabNameByLangId[(int) $language['id_lang']] = $name;
                } elseif (isset($nameByLangIsoCode['en'])) {
                    $tabNameByLangId[(int) $language['id_lang']] = $nameByLangIsoCode['en'];
                }
            }
        }

        $orderState = new OrderState();
        $orderState->module_name = $this->name;
        $orderState->name = $tabNameByLangId;
        $orderState->color = $color;
        $orderState->logable = $isLogable;
        $orderState->paid = $isPaid;
        $orderState->invoice = $isInvoice;
        $orderState->shipped = $isShipped;
        $orderState->delivery = $isDelivery;
        $orderState->pdf_delivery = $isPdfDelivery;
        $orderState->pdf_invoice = $isPdfInvoice;
        $orderState->send_email = $isSendEmail;
        $orderState->hidden = $isHidden;
        $orderState->unremovable = $isUnremovable;
        $orderState->template = $template;
        $orderState->deleted = $isDeleted;
        $result = (bool) $orderState->add();

        if (false === $result) {
            $this->_errors[] = sprintf(
                'Failed to create OrderState %s',
                $configurationKey
            );

            return false;
        }

        $result = (bool) Configuration::updateGlobalValue($configurationKey, (int) $orderState->id);

        if (false === $result) {
            $this->_errors[] = sprintf(
                'Failed to save OrderState %s to Configuration',
                $configurationKey
            );

            return false;
        }

        $orderStateImgPath = $this->getLocalPath() . 'views/img/orderstate/' . $configurationKey . '.png';

        if (false === (bool) Tools::file_exists_cache($orderStateImgPath)) {
            $this->_errors[] = sprintf(
                'Failed to find icon file of OrderState %s',
                $configurationKey
            );

            return false;
        }

        if (false === (bool) Tools::copy($orderStateImgPath, _PS_ORDER_STATE_IMG_DIR_ . $orderState->id . '.gif')) {
            $this->_errors[] = sprintf(
                'Failed to copy icon of OrderState %s',
                $configurationKey
            );

            return false;
        }

        return true;
    }

    /**
     * Delete custom OrderState used for payment
     * We mark them as deleted to not break passed Orders
     *
     * @return bool
     */
    private function deleteOrderState()
    {
        $result = true;

        $orderStateCollection = new PrestaShopCollection('OrderState');
        $orderStateCollection->where('module_name', '=', $this->name);
        /** @var OrderState[] $orderStates */
        $orderStates = $orderStateCollection->getAll();

        foreach ($orderStates as $orderState) {
            $orderState->deleted = true;
            $result = $result && (bool) $orderState->save();
        }

        return $result;
    }

    /**
     * Install default module configuration
     *
     * @return bool
     */
    private function installConfiguration()
    {
        return (bool) Configuration::updateGlobalValue(static::GO_LIVE, '0')
            && (bool) Configuration::updateGlobalValue(static::SECRET_KEY_TEST, 'PBLK-TEST-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX-X')
            && (bool) Configuration::updateGlobalValue(static::PUBLIC_KEY_TEST, 'SECRK-TEST-XXXXXXXXXXXXXXXXXXXXXXXXXXXX')
            && (bool) Configuration::updateGlobalValue(static::SECRET_KEY_LIVE, 'SECR-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX-X')
            && (bool) Configuration::updateGlobalValue(static::PUBLIC_KEY_LIVE, 'PUBK-XXXXXXXXXXXXXXXXXXXXXXXX-X');
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    private function uninstallConfiguration()
    {
        return (bool) Configuration::deleteByName(static::GO_LIVE)
        && (bool) Configuration::deleteByName(static::SECRET_KEY_TEST)
        && (bool) Configuration::deleteByName(static::PUBLIC_KEY_TEST)
        && (bool) Configuration::deleteByName(static::SECRET_KEY_LIVE)
        && (bool) Configuration::deleteByName(static::PUBLIC_KEY_LIVE);
    }

    /**
     * Install Tabs
     *
     * @return bool
     */
    public function installTabs()
    {
        if (Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = static::MODULE_ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->displayName
        );

        return (bool) $tab->add();
    }

    /**
     * Uninstall Tabs
     *
     * @return bool
     */
    public function uninstallTabs()
    {
        $id_tab = (int) Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER);

        if ($id_tab) {
            $tab = new Tab($id_tab);

            return (bool) $tab->delete();
        }

        return true;
    }
}