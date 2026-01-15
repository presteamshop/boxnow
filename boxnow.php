<?php

/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2024 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    print_r("PS Version exit");
    exit;
}
include_once _PS_MODULE_DIR_ . 'boxnow/classes/BoxnowEntry.php';

class boxnow extends CarrierModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'boxnow';
        $this->tab = 'shipping_logistics';
        $this->version = '2.4.3';
        $this->author = 'BOX NOW';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.5.0',
            'max' => '9.0.1',
        ];

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BOX NOW');
        $this->description = $this->l('The Future of Parcel Delivery! 24/7, Faster, Greener');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the BOX NOW Delivery module?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        if (!Configuration::hasKey('BOXNOW_MAP_MODE')) {
            Configuration::updateValue('BOXNOW_MAP_MODE', 'popup');
        }

        if (!Configuration::hasKey('BOXNOW_BUTTON_COLOR')) {
            Configuration::updateValue('BOXNOW_BUTTON_COLOR', '#84C33F');
        }

        if (!Configuration::hasKey('BOXNOW_BUTTON_TEXT')) {
            Configuration::updateValue('BOXNOW_BUTTON_TEXT', 'Pick a locker');
        }

        if (!Configuration::hasKey('BOXNOW_ALLOW_RETURN')) {
            Configuration::updateValue('BOXNOW_ALLOW_RETURN', 'true'); // Stored config value must also be a string, not boolean: 'true' | 'false'
        }

        //Add the new carrier during installation
        $carrier = $this->addCarrier();
        Configuration::updateValue('BOXNOW_CARRIER_ID', (int) $carrier->id); // <-- This is storing the new carrier ID

        // Add zones, groups, ranges for the new carrier
        //$this->addZones($carrier);
        $this->addGroups($carrier);
        //$this->addRanges($carrier);

        
        // Execute the SQL installation script (if any)
        include dirname(__FILE__) . '/sql/install.php';
        $boxnowCarrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
$carrierDimensions = Db::getInstance()->execute(
        'UPDATE ' . _DB_PREFIX_ . 'carrier
SET max_width = 45,
    max_height = 36,
    max_depth = 60,
    max_weight = 20
WHERE id_carrier = '.$boxnowCarrierId.''
    );

        if (!$carrierDimensions){
            PrestaShopLogger::addLog("BOXNOW:Encountered error trying to add dimensions to Carrier", 3);
        }

        // Register hooks and complete the installation
        return parent::install() &&
            $this->registerHook('header') &&  // PHP message: PHP Deprecated:  The hook "Header" is deprecated, please use "displayHeader"
            $this->registerHook('displayCarrierExtraContent') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('displayAdminOrderSide') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayOrderDetail') &&
            $this->registerHook('updateCarrier');
;
    }

    /**
     * Handle the old carrier if the module was previously uninstalled.
     */
    protected function handleOldCarrier()
{
    $boxnowCarrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
    if (!$boxnowCarrierId) {
        PrestaShopLogger::addLog("BOXNOW: No Boxnow Carrier ID found in configuration.", 3);
        return;
    }

    // Step 1: Find carts using Boxnow carrier and without corresponding orders
    $cartIds = Db::getInstance()->executeS(
        'SELECT c.id_cart 
         FROM ' . _DB_PREFIX_ . 'cart AS c 
         WHERE c.id_carrier = ' . $boxnowCarrierId . ' 
         AND NOT EXISTS (
             SELECT 1 FROM ' . _DB_PREFIX_ . 'orders o 
             WHERE o.id_cart = c.id_cart
         )'
    );

    // Step 2: Delete carts that have no corresponding orders
    if (!empty($cartIds)) {
        foreach ($cartIds as $row) {
            $cartId = (int) $row['id_cart'];
            $cart = new Cart($cartId);
            if (Validate::isLoadedObject($cart)) {
                $cart->delete();
            } else {
                PrestaShopLogger::addLog("BOXNOW: Failed to load cart ID $cartId during uninstall.", 3);
            }
        }
    }

    // Step 3: Remove Boxnow entries from the `boxnow_entries` table
    $result = Db::getInstance()->execute(
        'DELETE FROM ' . _DB_PREFIX_ . 'boxnow_entries 
         WHERE id_cart IN (
             SELECT c.id_cart 
             FROM ' . _DB_PREFIX_ . 'cart c 
             WHERE c.id_carrier = ' . $boxnowCarrierId . ' 
             AND NOT EXISTS (
                 SELECT 1 FROM ' . _DB_PREFIX_ . 'orders o 
                 WHERE o.id_cart = c.id_cart
             )
         )'
    );

    if ($result === false) {
        PrestaShopLogger::addLog("BOXNOW: Failed to delete entries from boxnow_entries for carrier ID $boxnowCarrierId.", 3);
    }
}


    public function uninstall()
    {
        $this->handleOldCarrier();
        Configuration::deleteByName('BOXNOW_API_URL');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_ID');
        Configuration::deleteByName('BOXNOW_OAUTH_CLIENT_SECRET');
        Configuration::deleteByName('BOXNOW_WAREHOUSE_NUMBER');
        Configuration::deleteByName('BOXNOW_PARTNER_ID');
        Configuration::deleteByName('BOXNOW_CONTACT_EMAIL');
        Configuration::deleteByName('BOXNOW_CONTACT_NAME');
        Configuration::deleteByName('BOXNOW_CONTACT_NUMBER');
        Configuration::deleteByName('BOXNOW_ALLOW_RETURN');
        Configuration::deleteByName('BOXNOW_MAP_MODE');
        Configuration::deleteByName('BOXNOW_BUTTON_COLOR');
        Configuration::deleteByName('BOXNOW_BUTTON_TEXT');
        $this->removeCarrier();
    
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitBoxnowModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . '<div class="boxnow-settings-grid">' . $this->renderForm() . '</div>';
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBoxnowModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());

    }

    /**
     * Create the structure of your form.
     */
 protected function getConfigForm()
{
    return [
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('API Details'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Select API URL'),
                        'name' => 'BOXNOW_API_URL',
                        'required' => true,
                        'options' => [
                            'query' => [
                                ['id' => 'api-stage.boxnow.gr', 'name' => 'GR Staging Environment (api-stage.boxnow.gr)'],
                                ['id' => 'api-production.boxnow.gr', 'name' => 'GR Production Environment (api-production.boxnow.gr)'],
                                ['id' => 'api-stage.boxnow.cy', 'name' => 'CY Staging Environment (api-stage.boxnow.cy)'],
                                ['id' => 'api-production.boxnow.cy', 'name' => 'CY Production Environment (api-production.boxnow.cy)'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Select the API environment you want to use'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Client ID'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_ID',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Client Secret'),
                        'name' => 'BOXNOW_OAUTH_CLIENT_SECRET',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Warehouse IDs (Multiple IDs separated by commas ",")'),
                        'name' => 'BOXNOW_WAREHOUSE_NUMBER',
                        'required' => true,
                        'desc' => $this->l('Enter your warehouse number(s). If you have more than one warehouse separate numbers by commas (example: 1234 [Main Warehouse], 1235 [Supplier])'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Partner ID'),
                        'name' => 'BOXNOW_PARTNER_ID',
                        'required' => true,
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Contact Details'),
                    'icon' => 'icon-user',
                    'description' => $this->l('Contact details are also used for Parcel Returns'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Name'),
                        'name' => 'BOXNOW_CONTACT_NAME',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Email'),
                        'name' => 'BOXNOW_CONTACT_EMAIL',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your Orders Contact Mobile Phone'),
                        'name' => 'BOXNOW_CONTACT_NUMBER',
                        'required' => true,
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Voucher Options'),
                    'icon' => 'icon-gear',
                    'description' => $this->l('Contact details are also used for Parcel Returns'),
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Allow Returns'),
                        'name' => 'BOXNOW_ALLOW_RETURN',
                        'desc' => $this->l('This option enables or disables the ability for customers to return items using the same voucher number.'),
                        'values' => [
                            [
                                'id' => 'true',
                                'value' => 'true',
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'false',
                                'value' => 'false',
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Widget Options'),
                    'icon' => 'icon-map-marker',
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Widget Display Mode'),
                        'name' => 'BOXNOW_MAP_MODE',
                        'values' => [
                            [
                                'id' => 'popup',
                                'value' => 'popup',
                                'label' => $this->l('Popup Window'),
                            ],
                            [
                                'id' => 'iframe',
                                'value' => 'iframe',
                                'label' => $this->l('Embedded iFrame'),
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'form' => [
                'legend' => [
                    'title' => $this->l('Button & Customization'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Change Button Text'),
                        'name' => 'BOXNOW_BUTTON_TEXT',
                        'required' => false,
                        'desc' => $this->l('Edit the text of the BOX NOW pick location selection button in popup mode'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Change Button Background Color'),
                        'name' => 'BOXNOW_BUTTON_COLOR',
                        'required' => false,
                        'desc' => $this->l('Edit the color of the BOX NOW pick location selection button in popup mode'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ],
    ];
}



    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'BOXNOW_API_URL' => Configuration::get('BOXNOW_API_URL', null),
            'BOXNOW_OAUTH_CLIENT_ID' => trim(Configuration::get('BOXNOW_OAUTH_CLIENT_ID', null)),
            'BOXNOW_OAUTH_CLIENT_SECRET' => trim(Configuration::get('BOXNOW_OAUTH_CLIENT_SECRET', null)),
            'BOXNOW_WAREHOUSE_NUMBER' => trim(Configuration::get('BOXNOW_WAREHOUSE_NUMBER', null)),
            'BOXNOW_PARTNER_ID' => trim(Configuration::get('BOXNOW_PARTNER_ID', null)),
            'BOXNOW_CONTACT_EMAIL' => Configuration::get('BOXNOW_CONTACT_EMAIL', null),
            'BOXNOW_CONTACT_NAME' => Configuration::get('BOXNOW_CONTACT_NAME', null),
            'BOXNOW_CONTACT_NUMBER' => Configuration::get('BOXNOW_CONTACT_NUMBER', null),
            'BOXNOW_ALLOW_RETURN' => Configuration::get('BOXNOW_ALLOW_RETURN', 'true'), // Stored config value must also be a string, not boolean: 'true' | 'false'
            'BOXNOW_MAP_MODE' => Configuration::get('BOXNOW_MAP_MODE', 'popup'), //popup | iframe
            'BOXNOW_BUTTON_COLOR' => Configuration::get('BOXNOW_BUTTON_COLOR', '#84C33F'),
            'BOXNOW_BUTTON_TEXT' => Configuration::get('BOXNOW_BUTTON_TEXT', 'Pick a locker'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return false;
    }

    protected function addCarrier()
    {
        $carrier = new Carrier();

        $carrier->name = $this->l('BOX NOW');
        $carrier->is_module = true;
        $carrier->active = 1;
        $carrier->need_range = 1;
        $carrier->shipping_external = true;
        $carrier->range_behavior = 0;
        $carrier->url = 'https://track.boxnow.gr/?track=@';
        $carrier->external_module_name = $this->name;
        $carrier->shipping_method = 2;

        foreach (Language::getLanguages() as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->l('Pick up your order in BOX NOW lockers');
        }
        
        if ($carrier->add() == true) {
            @copy(dirname(__FILE__) . '/views/img/carrier_image.png', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
            Configuration::updateValue('BOXNOW_CARRIER_ID', (int) $carrier->id);
            return $carrier;
        }

        return false;
    }

    protected function removeCarrier()
    {
        $carrierId = (int) Configuration::get('BOXNOW_CARRIER_ID');
        if (!$carrierId) {
            PrestaShopLogger::addLog('Invalid carrier ID provided or fetched from Configuration. Exiting removeCarrier()');
            return;
        }

        // Check if there is a valid carrier in DB with the supplied carrierId from Configuration
        $carrier = new Carrier($carrierId);
        if (Validate::isLoadedObject($carrier)) {
            // If so, mark the carrier as deleted and update the DB and delete from Configuration
            $carrier->deleted = 1;
            $carrier->save();
            $carrierDeleted = Configuration::deleteByName('BOXNOW_CARRIER_ID');
            PrestaShopLogger::addLog('Carrier with the supplied carrierId deleted:' . ($carrierDeleted ? ' Success' : ' Failed'));
        } else {
            PrestaShopLogger::addLog('Carrier with the supplied carrierId not found in DB. Exiting removeCarrier()');
        }

        return;
    }




    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        $carrier->setGroups($groups_ids);
    }

    /**
     * Hook implemented in order to sync carrier IDs due to PrestaShop's carrier id increment upon updating a carrier.
     * @param mixed $params
     * @return void
     */
    public function hookUpdateCarrier($params)
    {
        $id_carrier_old = (int) $params['id_carrier'];
        $id_carrier_new = (int) $params['carrier']->id;
        if ($id_carrier_old === (int) Configuration::get('BOXNOW_CARRIER_ID')) {
            Configuration::updateValue('BOXNOW_CARRIER_ID', $id_carrier_new);
        }
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '20';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone($zone['id_zone']);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     * Warning: PHP message: PHP Deprecated:  The hook "Header" is deprecated, please use "displayHeader"
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/displayheader/
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookDisplayCarrierExtraContent(array $params)
    {
        $id_cart = $params['cart']->id;
        $entry = BoxnowEntry::fromCartId($id_cart);

        $selected_country = 'GR';

        $id_address = (int)$params['cart']->id_address_delivery;

        $address = new Address($id_address);
        if (Validate::isLoadedObject($address)) {
            $country = new Country($address->id_country);
            if (Validate::isLoadedObject($country)) {
                $selected_country = $country->iso_code;
            }
        }

        $this->context->smarty->assign(array(
            'boxnow_partner_id' => Configuration::get('BOXNOW_PARTNER_ID'),
            'boxnow_map_mode' => Configuration::get('BOXNOW_MAP_MODE'),
            'boxnow_button_color' => Configuration::get('BOXNOW_BUTTON_COLOR'),
            'boxnow_button_text' => Configuration::get('BOXNOW_BUTTON_TEXT'),
            'boxnow_select_endpoint' => $this->context->link->getModuleLink('boxnow', 'selection'),
            'boxnow_selected_entry' => $entry,
            'boxnow_id_cart' => $params['cart']->id,
            'boxnow_cart' => $params['cart']->id_address_invoice,
            'selected_country' => $selected_country,
        ));

        switch ($selected_country) {
            case 'CY':
                $template_file = 'views/templates/hooks/boxnowcy.tpl';
                break;
            case 'BG':
                $template_file = 'views/templates/hooks/boxnowbg.tpl';
                break;
            case 'HR':
                $template_file = 'views/templates/hooks/boxnowhr.tpl';
                break;
            default:
                $template_file = 'views/templates/hooks/boxnow.tpl';
                break;
        }

        return $this->context->smarty->fetch($this->local_path . $template_file);
    }



//TODO: Add functionality in displayAdminOrderSide that allowes for locker selection from the map widget exactly as it happens in the checkout. Keep in mind to use shipping address and fallback to billing adresss to display the correct country map through the appropriate template files.
    public function hookDisplayAdminOrderSide($param)
{
    // Fetch the boxnow entry for the given order
    $result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'boxnow_entries` WHERE id_order = "' . 
        (int)($param['id_order']) . '"');
    
    // Initialize the button HTML
    $boxnow_button = '';
    
    // Check if a valid entry exists
    if (!empty($result['id_order'])) {
        // Parse parcel_ids (voucher numbers) from the database
        $voucher_numbers = [];
        if (!empty($result['parcel_ids'])) {
            $voucher_numbers = explode(',', $result['parcel_ids']);  // Array of parcel ids
        }

        // Fetch warehouse numbers (if any are configured)
        $warehouses = [];
        if (!empty(Configuration::get('BOXNOW_WAREHOUSE_NUMBER'))) {
            $warehouses = explode(',', Configuration::get('BOXNOW_WAREHOUSE_NUMBER'));
        }

        // Fetch the order and its products
        $order = new Order($result['id_order']);
        $products = $order->getProducts();
        
        // Calculate the total quantity of products in the order
        $totalQuantity = 0;
        foreach ($products as $product) {
            $totalQuantity += (int)$product['product_quantity'];
        }

        // Assign all the necessary variables to Smarty
        $this->context->smarty->assign(
            array(
                'boxnow_id_order' => $result['id_order'],
                'boxnow_vouchers' => $result['vouchers'],  // Display the number of vouchers created
                'boxnow_locker_id' => $result['locker_id'],
                'boxnow_locker_name' => $result['locker_name'],
                'boxnow_locker_address' => $result['locker_address'],
                'boxnow_locker_post_code' => $result['locker_post_code'],
                'boxnow_parcel_ids' => $voucher_numbers,  // Display the actual voucher IDs
                'boxnow_warehouses' => $warehouses,
                'total_order_products' => $totalQuantity,  // Total products in the order
            )
        );

        // Return the rendered template
        return $this->context->smarty->fetch($this->local_path . 'views/templates/hooks/boxnow_voucher.tpl');
    } else {
        PrestaShopLogger::addLog("hookDisplayAdminOrderSide(): no valid entry found, returning an empty string and exiting...");
        // If no valid entry found, return an empty string
        return '';
    }
}

    public function hookActionValidateOrder($params)
    {
        $id = $params['order']->id;
        $order = new Order((int) $id);

        // Check if the order is using the BoxNow Carrier
        if ($order->id_carrier != Configuration::get('BOXNOW_CARRIER_ID')) {
            PrestaShopLogger::addLog('Exiting hookActionValidateOrder(). Order using BoxNow carrier id ' . $order->id_carrier . ', which differs from the BOXNOW_CARRIER_ID in Configuration');
            return;
        }

        // Check if cart ID is valid
        $id_cart = (int) $order->id_cart;

        if ($id_cart <= 0) {
            // Invalid cart ID, skip the update operation
            PrestaShopLogger::addLog('Exiting hookActionValidateOrder(). Invalid cart ID.');
            return;
        }

        // Update boxnow_entries table with order ID
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'boxnow_entries SET id_order=' . $id . ' WHERE id_cart=' . $id_cart;
        if ($a = !Db::getInstance()->execute($sql)) {
            // Error handling
            // Consider logging the error or throwing an exception
            PrestaShopLogger::addLog('Exiting hookActionValidateOrder(). Error updating boxnow_entries table.');
            PrestaShopLogger::addLog('$a: ' . $a);
            die('Error updating boxnow_entries table');
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $id = $_REQUEST['id_order'];

        $boxdata = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'boxnow_entries WHERE id_order=' . $id);
        if ($boxdata['id_order']) {
            $lockerdata = $boxdata['locker_name'] . ', ' . $this->l('Address') . ': ' . $boxdata['locker_address'] . ', ' . $boxdata['locker_post_code'];
        } else {
            $lockerdata = '';
        }
        $this->context->smarty->assign(array(
            'lockermsg' => $lockerdata,
        ));
        return $this->fetch(
            'module:boxnow/views/templates/hooks/locker_msg.tpl'
        );
    }
    
    public function hookDisplayOrderDetail($params)
    {
        $id = $params['order']->id;

        $boxdata = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'boxnow_entries WHERE id_order=' . $id);
        if ($boxdata['id_order']) {
            $lockerdata = $boxdata['locker_name'] . ', ' . $this->l('Address') . ': ' . $boxdata['locker_address'] . ', ' . $boxdata['locker_post_code'];
        } else {
            $lockerdata = '';
        }
        $this->context->smarty->assign(array(
            'lockermsg' => $lockerdata,
        ));
        return $this->fetch(
            'module:boxnow/views/templates/hooks/locker_msg.tpl'
        );
    }

    public static function formatPhone($raw): ?string
    {

        // 1. Normalize to digits only
        $digits = preg_replace('/\D+/', '', $raw);

        // 2. Convert 00 prefix to international
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        /*
        * ===== Strict country rules =====
        */

        // Greece
        if (preg_match('/^(30)?69\d{8}$/', $digits)) {
            return '+30' . substr($digits, -10);
        }

        // Cyprus
        if (preg_match('/^(357)?(94|95|96|97|99)\d{6}$/', $digits)) {
            return '+357' . substr($digits, -8);
        }

        // Bulgaria
        if (preg_match('/^(359)?8[7-9]\d{7}$/', $digits)) {
            return '+359' . substr($digits, -9);
        }

        // Croatia
        if (preg_match('/^(385)?9[1-9]\d{7}$/', $digits)) {
            return '+385' . substr($digits, -9);
        }

        /*
        * ===== Worldwide fallback (E.164) =====
        *  - Country code 1–3 digits (no leading 0)
        *  - Total length 8–15 digits
        */
        if (preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return '+' . $digits;
        }

        return null; // invalid
    }
}
