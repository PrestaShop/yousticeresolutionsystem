<?php
/**
 * Youstice Resolution Module
 *
 * @author    Youstice
 * @copyright (c) 2014, Youstice
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require('../../config/config.inc.php');
require('controllers/front/yrscontroller.php');
require('yousticeresolutionsystem.php');
require('Youstice/Api.php');
error_reporting(E_ALL ^ E_NOTICE);
define('YRS_MODULE_PATH', '/modules/yousticeresolutionsystem/');
define('YRS_MODULE_ROOT_JS', '/modules/yousticeresolutionsystem/');

$db = array(
	'driver' => 'mysql',
	'host' => _DB_SERVER_,
	'user' => _DB_USER_,
	'pass' => _DB_PASSWD_,
	'name' => _DB_NAME_,
	'prefix' => _DB_PREFIX_
);

$y_api = \Youstice\Api::create();

$y_api->setDbCredentials($db);
$y_api->setLanguage(Context::getContext()->language->iso_code);
$y_api->setShopSoftwareType('prestashop');
$y_api->setThisShopSells(Configuration::get('YRS_ITEM_TYPE'));
$y_api->setApiKey(Configuration::get('YRS_API_KEY'), Configuration::get('YRS_SANDBOX'));

if (Tools::getIsset('section'))
{
	$c = new YrsController($y_api);
	$c->module = new YousticeResolutionSystem();
	if (method_exists('YrsController', $_GET['section']))
		call_user_func_array(array($c, $_GET['section']), array($_GET));
	else
		exit('Route not found');
}
