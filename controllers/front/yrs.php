<?php
/**
 * Main Youstice Resolution system frontend controller.
 *
 * @author    Youstice
 * @copyright (c) 2015, Youstice
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html  Apache License, Version 2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class YousticeResolutionSystemYrsModuleFrontController extends ModuleFrontController
{

    private $yapi = null;
    private $url_yrs;
    private $customer_id;

    public function __construct()
    {
        parent::__construct();

        $url = 'index.php?fc=module&module=yousticeresolutionsystem&controller=yrs';
        $this->url_yrs = '//' . Tools::getShopDomainSsl() . __PS_BASE_URI__ . $url;

        $yapi = $this->createSDK();
        //at logged out reporting
        $this->authenticateUser();
        $yapi->setUserId($this->customer_id);
        $yapi->run();
        $this->yapi = $yapi;

        $this->parseActions();
    }

    protected function parseActions()
    {
        define('YRS_TEMPLATE_PATH', _PS_MODULE_DIR_ . $this->module->name . '/views/templates/front/');

        $action = Tools::getValue('action');

        switch ($action) {
            case 'getReportClaimsPage':
                $this->getReportClaimsPage();
                break;
            case 'getReportClaimsPagePost':
                $this->getReportClaimsPagePost();
                break;
            case 'getOrdersPage':
                $this->getOrdersPage();
                break;
            case 'getShowButtonsHtml':
                $this->getShowButtonsHtml();
                break;
            case 'getLogoWidget':
                $this->getLogoWidget();
                break;
            case 'getOrdersButtons':
                $this->getOrdersButtons();
                break;
            case 'getProductsButtons':
                $this->getProductsButtons();
                break;
            case 'getWebReportButton':
                $this->getWebReportButton();
                break;
            case 'webReportPost':
                $this->webReportPost();
                break;
            case 'getOrderDetail':
                $this->getOrderDetail();
                break;
            case 'orderReportPost':
                $this->orderReportPost();
                break;
            case 'productReportPost':
                $this->productReportPost();
                break;
        }
    }

    public function getReportClaimsPage()
    {
        if (!Configuration::get('YRS_API_KEY') || !$this->yapi->checkIsProperlyInstalled()) {
            return;
        }

        if ($this->context->customer->id !== null) {
            $redirect_url = $this->getOrderHistoryUrl();

            if (Tools::getIsset('ordersPage')) {
                $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'ordersPage';
            }

            Tools::redirect($redirect_url);
        }

        $this->setTemplate('reportClaims.tpl');

        parent::init();
        parent::setMedia();
        $base_url = Tools::getCurrentUrlProtocolPrefix() . Tools::getShopDomainSsl() . __PS_BASE_URI__;
        $this->addJS($base_url . 'modules/yousticeresolutionsystem/views/js/yrs_report_claims.js');
        parent::initHeader();
        parent::initContent();
        parent::initFooter();

        $this->display();
        exit;
    }

    # ajax call

    public function getReportClaimsPagePost()
    {
        $order_number = $this->getOrderNumber();

        if (!ValidateCore::isEmail(Tools::getValue('email'))) {
            echo Tools::jsonEncode(array('error' => $this->module->l('Invalid email', 'yrs')));
            exit;
        }

        if (!$this->customer_id) {
            echo Tools::jsonEncode(array('error' => $this->module->l('Email or order number not found', 'yrs')));
            exit;
        }

        $order = $this->getOrderByReference($order_number);

        if ($order) {
            $shop_order = $this->createShopOrder($order);

            $html = $this->yapi->getOrderDetailHtml($shop_order);
            echo Tools::jsonEncode(array('orderDetail' => $html));
            exit;
        }

        //order number not found in customer's orders
        echo Tools::jsonEncode(array('error' => $this->module->l('Email or order number not found', 'yrs')));
        exit;
    }

    public function getOrdersPage()
    {
        $shop_orders = $this->createShopOrders();

        $url = $this->url_yrs . '&action=webReportPost';
        
        $html = $this->yapi->getOrdersPageWidgetHtml($url, Configuration::get('PS_SHOP_NAME'), $shop_orders);

        echo Tools::jsonEncode(array('ordersPage' => $html));
        exit;
    }

    protected function authenticateUser()
    {
        if ($this->context->customer->id !== null) {
            $this->customer_id = $this->context->customer->id;
            return;
        }

        $email = Tools::getValue('email');

        if (!Validate::isEmail($email)) {
            return;
        }

        //get customer
        $customer = new Customer();
        $result = $customer->getByEmail($email);

        if (!$result || !Validate::isLoadedObject($customer)) {
            return false;
        }

        $this->customer_id = $customer->id;
    }

    protected function getOrderNumber()
    {
        return preg_replace('/[^\w\d]/ui', '', Tools::getValue('orderNumber'));
    }

    public function getShowButtonsHtml()
    {
        echo $this->yapi->getShowButtonsWidgetHtml();
        $this->yapi->orderHistoryViewed();
        exit;
    }

    public function getLogoWidget()
    {
        if (!Configuration::get('YRS_SHOW_LOGO_WIDGET')) {
            exit;
        }

        echo $this->yapi->getLogoWidgetHtml($this->url_yrs . '&action=getReportClaimsPage', true);
        exit;
    }

    # AJAX

    public function getOrdersButtons()
    {
        $in = Tools::getValue('order_ids');
        $reports = array();

        if (!empty($in)) {
            foreach ($in as $order_id) {
                $order = $this->getOrder($order_id);

                if (empty($order) || $order['id_customer'] != $this->context->customer->id) {
                    continue;
                }

                $shop_order = $this->createShopOrder($order);
                $href = $this->createOrderDetailHref($order_id);

                $reports[$order_id] = $this->yapi->getOrderDetailButtonHtml($href, $shop_order);
            }
        }

        echo Tools::jsonEncode($reports);
        exit;
    }

    # AJAX

    public function getProductsButtons()
    {
        $in = Tools::getValue('products_ids');
        $reports = array();
        $order_id = Tools::getValue('order_id');
        $order = $this->getOrder($order_id);

        if (empty($order) || $order['id_customer'] != $this->context->customer->id) {
            exit;
        }

        if (!empty($in)) {
            foreach ($in as $product_order_id) {
                $href = $this->createProductReportHref($order_id, $product_order_id);

                $reports[$product_order_id] = $this->yapi->getProductReportButtonHtml($href, $product_order_id, $order_id);
            }
        }

        echo Tools::jsonEncode($reports);
        exit;
    }

    # AJAX

    public function getWebReportButton()
    {
        if (!$this->context->customer->id) {
            return;
        }

        echo $this->yapi->getWebReportButtonHtml($this->url_yrs . '&action=webReportPost');
        exit;
    }

    public function webReportPost()
    {
        if (!$this->context->customer->id) {
            return;
        }

        try {
            $redirect_url = $this->yapi->createWebReport();
        } catch (Exception $e) {
            exit('Connection to remote server failed, please <a href="#" onClick="history.go(0)">try again</a> later');
        }

        Tools::redirect($redirect_url);
    }

    #AJAX

    public function getOrderDetail()
    {
        $order = $this->getOrder(Tools::getValue('order_id'));

        if (!$order || $order['id_customer'] != $this->context->customer->id) {
            exit('Operation not allowed');
        }

        $shop_order = $this->createShopOrder($order);

        echo $this->yapi->getOrderDetailHtml($shop_order);
        exit;
    }

    public function orderReportPost()
    {
        //logged out reporting
        if ($this->context->customer->id !== $this->customer_id) {
            $order = $this->getOrderByReference($this->getOrderNumber());
        } else {
            $order = $this->getOrder((int) Tools::getValue('order_id'));

            if (!$order || $order['id_customer'] != $this->context->customer->id) {
                exit('Operation not allowed');
            }
        }

        $shop_order = $this->createShopOrder($order);

        try {
            $redirect_url = $this->yapi->createOrderReport($shop_order);
        } catch (Exception $e) {
            exit('Connection to remote server failed, please <a href="" onClick="history.go(0)">try again</a> later');
        }

        Tools::redirect($redirect_url);
    }

    public function productReportPost()
    {
        //logged out reporting
        if ($this->context->customer->id !== $this->customer_id) {
            $order = $this->getOrderByReference($this->getOrderNumber());
        } else {
            $order = $this->getOrder((int) Tools::getValue('order_id'));

            if (!$order || $order['id_customer'] != $this->context->customer->id) {
                exit('Operation not allowed');
            }
        }

        $shop_order = $this->createShopOrder($order);

        $shop_products = $shop_order->getProducts();

        foreach ($shop_products as $shop_product) {
            if ($shop_product->getId() == Tools::getValue('id_order_detail')) {
                try {
                    $redirect_url = $this->yapi->createProductReport($shop_product);
                } catch (Exception $e) {
                    exit('Connection to remote server failed, please <a href="" onClick="history.go(0)">try again</a> later');
                }

                Tools::redirect($redirect_url);
            }
        }

        exit('Product not found');
    }

    protected function getOrders()
    {
        return Order::getCustomerOrders($this->customer_id);
    }

    protected function getOrder($order_id)
    {
        if ($orders = $this->getOrders()) {
            foreach ($orders as $o) {
                if ($order_id == (int) $o['id_order']) {
                    return $o;
                }
            }
        }
        return null;
    }

    protected function getOrderByReference($order_reference)
    {
        if ($orders = Order::getCustomerOrders($this->customer_id)) {
            foreach ($orders as $o) {
                if ($o['reference'] == $order_reference) {
                    return $o;
                }
            }
        }
        return null;
    }

    protected function createShopOrders()
    {
        $orders = $this->getOrders();

        if (empty($orders)) {
            return array();
        }

        $shop_orders = array();

        foreach ($orders as $order) {
            $shop_orders[] = $this->createShopOrder($order);
        }

        return $shop_orders;
    }

    protected function createShopOrder($order)
    {
        if (!is_array($order)) {
            $order = $this->getOrder($order);
        }

        $order_id = $order['id_order'];

        $currency = Currency::getCurrencyInstance((int) $order['id_currency']);

        $shop_order = YousticeShopOrder::create();
        $shop_order->setDescription('');

        if (empty($order)) {
            exit('Operation not allowed');
        }

        $shop_order->setName('#' . $order['reference']);
        $shop_order->setCurrency($currency->iso_code);
        $shop_order->setPrice((float) $order['total_paid']);
        $shop_order->setId($order_id);
        $shop_order->setDeliveryDate($order['delivery_date']);
        $shop_order->setOrderDate($order['date_add']);
        $shop_order->setHref($this->createOrderReportHref($order_id));
        $shop_order->setOrderDetailHref($this->createOrderDetailHref($order_id));

        if ($order['total_paid_real'] >= $order['total_paid']) {
            $shop_order->setPaymentState(YousticeShopOrder::PAID);
        }

        if (strtotime($order['delivery_date']) > 0) {
            $shop_order->setDeliveryState(YousticeShopOrder::DELIVERED);
        }

        $shop_order->setOtherInfo('');

        $order_object = new Order((int) $order_id);
        $products = $order_object->getProducts();

        foreach ($products as $product) {
            $shop_product = $this->createShopProduct($product, $order_id);
            $shop_product->setCurrency($currency->iso_code);
            $shop_product->setDeliveryDate($order['delivery_date']);
            $shop_product->setOrderDate($order['date_add']);

            $shop_order->addProduct($shop_product);
        }

        return $shop_order;
    }

    protected function createShopProduct(array $product, $order_id)
    {
        $shop_product = YousticeShopProduct::create();
        $shop_product->setName($product['product_name']);
        $shop_product->setId($product['id_order_detail']);
        $shop_product->setPrice((float) $product['unit_price_tax_incl']);
        $shop_product->setDescription('');
        $shop_product->setOtherInfo('');

        //add image if exists
        if (count($product['image']) > 0) {
            //todo get image_format from $product['image'] ... $product['image']->image_format
            $image_path = _PS_PROD_IMG_DIR_ . $product['image']->getExistingImgPath() . '.jpg';
            $shop_product->setImagePath($image_path);
        }

        $shop_product->setOrderId($order_id);
        $shop_product->setHref($this->createProductReportHref($order_id, $product['id_order_detail']));

        return $shop_product;
    }

    protected function createOrderDetailHref($order_id)
    {
        return $this->url_yrs . '&action=getOrderDetail&order_id=' . $order_id;
    }

    protected function createOrderReportHref($order_id)
    {
        $href = $this->url_yrs . '&action=orderReportPost';
        //logged out reporting
        if ($this->customer_id !== $this->context->customer->id) {
            $href .= '&email=' . Tools::getValue('email');
            $href .= '&orderNumber=' . Tools::getValue('orderNumber');
        } else {
            $href .= '&order_id=' . $order_id;
        }

        return $href;
    }

    protected function createProductReportHref($order_id, $product_id)
    {
        $href = $this->url_yrs . '&action=productReportPost&id_order_detail=' . $product_id;
        //logged out reporting
        if ($this->customer_id !== $this->context->customer->id) {
            $href .= '&email=' . Tools::getValue('email');
            $href .= '&orderNumber=' . Tools::getValue('orderNumber');
        } else {
            $href .= '&order_id=' . $order_id;
        }

        return $href;
    }

    protected function createSDK()
    {
        require_once 'modules/yousticeresolutionsystem/SDK/Api.php';

        $db = array(
            'driver' => 'mysql',
            'host' => _DB_SERVER_,
            'user' => _DB_USER_,
            'pass' => _DB_PASSWD_,
            'name' => _DB_NAME_,
            'prefix' => _DB_PREFIX_
        );

        $yapi = YousticeApi::create();

        $yapi->setDbCredentials($db);
        $yapi->setLanguage(Context::getContext()->language->iso_code);
        $yapi->setShopSoftwareType('prestashop');
        $yapi->setApiKey(Configuration::get('YRS_API_KEY'), Configuration::get('YRS_SANDBOX'));
        $yapi->setSession(new YousticeProvidersSessionPrestashopProvider());

        return $yapi;
    }

    protected function getOrderHistoryUrl()
    {
        return $this->context->link->getPageLink('history');
    }
}
