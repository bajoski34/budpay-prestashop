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
class AdminConfigureBudpayController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->className = 'Configuration';
        $this->table = 'configuration';

        parent::__construct();

        $this->fields_options = [
            $this->module->name => [
                'fields' => [
                    Budpay::GO_LIVE => [
                        'type' => 'bool',
                        'title' => $this->l('Check this box to go Live'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
                    Budpay::SECRET_KEY_TEST => [
                        'type' => 'text',
                        'title' => $this->l('Enter your Budpay Test Secret Key'),
                        'validation' => 'isAnything',
                        'required' => true,
                    ],
                    Budpay::PUBLIC_KEY_TEST => [
                        'type' => 'text',
                        'title' => $this->l('Enter your Budpay Test Public Key'),
                        'validation' => 'isAnything',
                        'required' => true,
                    ],
                    Budpay::SECRET_KEY_LIVE => [
                        'type' => 'text',
                        'title' => $this->l('Enter your Budpay Live Secret Key'),
                        'validation' => 'isAnything',
                        'required' => true,
                    ],
                    Budpay::PUBLIC_KEY_LIVE => [
                        'type' => 'text',
                        'title' => $this->l('Enter your Budpay Live Public Key'),
                        'validation' => 'isAnything',
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }
}
