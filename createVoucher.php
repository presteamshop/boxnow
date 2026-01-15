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
require_once '../../config/config.inc.php';
require_once '../../init.php';
require_once 'boxnow.php';
$databaseConfig = require '../../app/config/parameters.php';

$servername = $databaseConfig['parameters']['database_host'];
$username = $databaseConfig['parameters']['database_user'];
$password = $databaseConfig['parameters']['database_password'];
$database = $databaseConfig['parameters']['database_name'];
$prefix = $databaseConfig['parameters']['database_prefix'];

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database",
        $username,
        $password,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Exception $e) {
    PrestaShopLogger::addLog("Exception caught:". $e->getMessage());
    echo 'Exception -> ';
    var_dump($e->getMessage());
}

if (!isset($_POST['order_id'], $_POST['voucher_number'], $_POST['Compartment_Size'], $_POST['warehouses'])) {
    PrestaShopLogger::addLog('Missing required parameters. Exiting createVoucher...');
    die('Missing required parameters.');
}


// get vars
$order_id = $_POST['order_id'];
$voucher = (int) $_POST['voucher_number'];
$Comp_S = (int) $_POST['Compartment_Size'];
// get configuration
$apisettings = $pdo->prepare("SELECT * FROM " . $prefix . "configuration WHERE name like 'BOXNOW_%'");
$apisettings->execute();
$apisettings = $apisettings->fetchAll();
$apisets = array();
foreach ($apisettings as $apis) {
    $apisets[$apis['name']] = $apis['value'];
}

$api_url = trim($apisets['BOXNOW_API_URL']);
$api_id = trim($apisets['BOXNOW_OAUTH_CLIENT_ID']);
$api_secret = trim($apisets['BOXNOW_OAUTH_CLIENT_SECRET']);
$api_warehouse = trim($apisets['BOXNOW_WAREHOUSE_NUMBER']);
$api_partner = trim($apisets['BOXNOW_PARTNER_ID']);
$api_email = trim($apisets['BOXNOW_CONTACT_EMAIL']);
$api_name = trim($apisets['BOXNOW_CONTACT_NAME']);
$api_contact = trim($apisets['BOXNOW_CONTACT_NUMBER']);
$api_allowReturn = $apisets['BOXNOW_ALLOW_RETURN'];
// get api session

$post_dt = '{
	"grant_type": "client_credentials",
	"client_id": "' . $api_id . '",
	"client_secret": "' . $api_secret . '"
  }';
$auth = curl_init('https://' . $api_url . '/api/v1/auth-sessions'); // Initialise cURL
curl_setopt($auth, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); // Inject the token into the header
curl_setopt($auth, CURLOPT_RETURNTRANSFER, true);
curl_setopt($auth, CURLOPT_POST, 1); // Specify the request method as POST
curl_setopt($auth, CURLOPT_POSTFIELDS, $post_dt); // Set the posted fields
curl_setopt($auth, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
$auth_response = curl_exec($auth);
$auth_http_status = curl_getinfo($auth, CURLINFO_HTTP_CODE);
$auth_json = json_decode($auth_response, true);
curl_close($auth);

if ($auth_http_status != 200) {
    $error_message = 'Unknown error';
    if (is_array($auth_json)) {
        if (!empty($auth_json['message'])) {
            $error_message = $auth_json['message'];
        } elseif (!empty($auth_json['error_description'])) {
            $error_message = $auth_json['error_description'];
        } elseif (!empty($auth_json['error'])) {
            $error_message = $auth_json['error'];
        }
    } elseif (is_string($auth_response) && $auth_response !== '') {
        $error_message = $auth_response;
    }
    die('Authentication failed: ' . $error_message);
}


// get orderinfo
$objOrder = new Order($order_id);
$customer = new Customer($objOrder->id_customer);
$address = new Address($objOrder->id_address_delivery);
$state = new State($address->id_state);
$country = new Country($address->id_country);
$products = $objOrder->getProducts();
if ($objOrder->module == 'ps_cashondelivery') {
    $paymentMode = 'cod';
    $payment_type = 2;
    $amountcollect = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
} else {
    $paymentMode = 'prepaid';
    $payment_type = 1;
    $amountcollect = 0.00;
}

// get order boxnow info
$boxnoworder = $pdo->prepare("SELECT * FROM " . $prefix . "boxnow_entries WHERE id_order='$order_id' LIMIT 1");
$boxnoworder->execute();
$boxnoworder = $boxnoworder->fetch();

// create vouchers in boxnow

// Code below was refactored to count quantities in conjunction with the count of products.
$totalQuantity = 0;

foreach ($products as $product) {
    $totalQuantity += (int)$product['product_quantity'];
}

$voucher_number = (int)$_POST['voucher_number'];
if ($voucher_number > $totalQuantity) {
    $voucher_number = $totalQuantity;
}

for ($i = 1; $i <= $voucher_number; $i++) {
    // get
    if ($i > 0) {
        $orderid = $order_id . uniqid() . '_' . $i;
    } else {
        $orderid = $order_id . uniqid();
    }
    $apicontact = $api_contact;
    $addressphone = Boxnow::formatPhone(
    (!empty($address->phone) && preg_match('/^\+?(?:\d ?){9,14}\d$/', $address->phone)) 
        ? $address->phone 
        : (preg_match('/^\+?(?:\d ?){9,14}\d$/', $address->phone_mobile) ? $address->phone_mobile : null)
    );

    $invoiceValue = number_format(floor($objOrder->total_paid * 100) / 100, 2, '.', '');
    $warehouse = $_POST['warehouses'];

    $items_prod = '[';
    // Loop until the voucher number iterations are met
    $total_products = count($products);
for ($i = 0; $i < $voucher_number; $i++) {
    // This sets product_index to the first index in products array.
    $product_index = key($products);
    $v = array();
    $v = $products[$product_index];
//var_dump($products); // Check what the products array contains

    // Calculate the value as per original logic
    $value = number_format(floor(($v['unit_price_tax_incl'] * $v['product_quantity']) * 100) / 100, 2, '.', '');

    // Create the product string
    $new_prod = '{
        "id": "' . $v['id_product'] . '",
        "name": "' . $v['product_name'] . '",
        "value": "' . $value . '",
        "weight": ' . $v['product_weight'] * $v['product_quantity'] . ',
        "compartmentSize": ' . (int)$Comp_S . '
    },';

    // Append the product to the result string
    $items_prod .= $new_prod;
}
    $items_prod = rtrim($items_prod, ',');
    $items_prod .= ']';

    $postData = '{
		"orderNumber": "' . $orderid . '",
		"invoiceValue": "' . $invoiceValue . '",
		"paymentMode": "' . $paymentMode . '",
		"amountToBeCollected" : "' . $amountcollect . '",
		"allowReturn":  ' . $api_allowReturn . ',
		"origin": {
			"contactNumber": "' . $apicontact . '",
			"contactEmail": "' . $api_email . '",
			"contactName": "' . $api_name . '",
			"locationId": "' . $_POST['warehouses'] . '"
		},
		"destination": {
			"contactNumber": "' . $addressphone . '",
			"contactEmail": "' . $customer->email . '",
			"contactName": "' . $address->firstname . ' ' . $address->lastname . '",
			"name": "' . $boxnoworder['locker_name'] . '",
			"addressLine1": "' . $address->address1 . '",
			"locationId": "' . $boxnoworder['locker_id'] . '"
		}, "items": ' . $items_prod . '}';

    // "country": "'.$country->iso_code.'",
    // "postalCode": "'.$address->postcode.'",
    // "note": "'.$address->other.'",

    $authorization = "Authorization: Bearer " . $auth_json['access_token'];
    $delivery_request = curl_init('https://' . $api_url . '/api/v1/delivery-requests');
    curl_setopt($delivery_request, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/json')); // Inject the token into the header
    curl_setopt($delivery_request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($delivery_request, CURLOPT_POST, 1); // Specify the request method as POST
    curl_setopt($delivery_request, CURLOPT_POSTFIELDS, $postData); // Set the posted fields
    curl_setopt($delivery_request, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
    $delivery_response = curl_exec($delivery_request);
    $delivery_http_status = curl_getinfo($delivery_request, CURLINFO_HTTP_CODE);
    $delivery = json_decode($delivery_response, true);
    curl_close($delivery_request);

    if (!empty($delivery['parcels'][0]['id'])) {
        // get old vouchers
        $oldvoucher = $pdo->prepare("SELECT parcel_ids FROM " . $prefix . "boxnow_entries WHERE id_order='$order_id' LIMIT 1");
        $oldvoucher->execute();
        $oldvoucher = $oldvoucher->fetch();
        $trackid = '';
        foreach ($delivery['parcels'] as $parcel){
        $trackid = $trackid . $parcel['id'] . ',';
        }
        $trackid = rtrim($trackid, ',');
        $old_parcels = trim((string) $oldvoucher['parcel_ids']);
        if ($old_parcels !== '') {
            $trackid = $old_parcels . ',' . $trackid;
        }
        $totalvoucher = $voucher_number;
        $sql = "UPDATE " . $prefix . "boxnow_entries SET parcel_ids='$trackid',warehouse_id='$warehouse',vouchers='$totalvoucher',payment_type='$payment_type' WHERE id_order=" . $order_id;
        $insert = $pdo->exec($sql);
    } else {
        $errors_arr = array(
            "P400" => "Invalid request data Make sure you are sending the request according to the documentation.",
            "P401" => "Invalid request origin location reference Make sure you are referencing a valid location ID from Origins endpoint or valid address.",
            "P402" => "Invalid request destination location reference Make sure you are referencing a valid location ID from Destinations endpoint or valid address",
            "P403" => "You are not allowed to use AnyAPM SameAPM delivery Contact support if you believe this is a mistake",
            "P404" => "Invalid import CSV See error contents for additional info.",
            "P405" => "Invalid phone number Make sure you are sending the phone number in full international format, e.g. +30 xx x xxx xxxx",
            "P406" => "Invalid compartment/parcel size Make sure you are sending one of required sizes 1, 2 or 3 ( Medium or Large) Size is required when sen ding from AnyAPM directly",
            "P407" => "Invalid country code Make sure you are sending country code in ISO 3166 1 alpha 2 format, e.g. GR.",
            "P410" => "Order number conflict You are trying to create a delivery request for order ID that has already been created. Choose another order ID",
            "P411" => "You are not eligible to use Cash on delivery payment type Use another payment type or contact our support.",
            "P420" => "Parcel not ready for cancel You can cancel only new, undelivered, or parcels that are not returned or lost. Make sure parcel is in transit and try again.",
            "P430" => "Parcel not ready for AnyAPM confirmation Parcel is probably already confirmed or being delivered. Contact support if you believe this is a mistake.",
        );
        echo 'Error' . ': ' . $delivery['code'] . ' - ' . $errors_arr[$delivery['code']];
    }
}
