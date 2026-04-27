<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/addEvent.php';

class Analytics extends Module
{
    public function __construct()
    {
        $this->name = 'analytics';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();
        $this->bootstrap = true;

        $this->displayName = $this->l('Analytics');
        $this->description = $this->l('Module for analytics integration.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('footer')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionAuthentication')
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionValidateOrder');
    }

    public function uninstall()
    {
        Configuration::deleteByName('ANALYTICS_GA4_ID');

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAnalyticsSettings')) {
            $ga4Id = trim(Tools::getValue('ANALYTICS_GA4_ID'));
            Configuration::updateValue('ANALYTICS_GA4_ID', $ga4Id);
            $output .= $this->displayConfirmation($this->l('Settings saved.'));
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $this->l('Google Analytics 4 Settings')],
                'input'  => [
                    [
                        'type'     => 'text',
                        'label'    => $this->l('GA4 Measurement ID'),
                        'name'     => 'ANALYTICS_GA4_ID',
                        'desc'     => $this->l('Example: G-XXXXXXXXXX'),
                        'required' => false,
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action   = 'submitAnalyticsSettings';
        $helper->fields_value['ANALYTICS_GA4_ID'] = Configuration::get('ANALYTICS_GA4_ID');

        return $helper->generateForm([$fieldsForm]);
    }

    private function data()
    {
        return [
            'ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], true)
        ];
    }

    private function buildPurchasePayload(Order $order)
    {
        $currency = new Currency((int) $order->id_currency);
        $items = [];
        $contentIds = [];

        foreach ($order->getProducts() as $product) {
            $contentIds[] = (string) $product['product_id'];
            $items[] = [
                'item_id'   => (string) $product['product_id'],
                'item_name' => $product['product_name'],
                'price'     => (float) $product['unit_price_tax_incl'],
                'quantity'  => (int) $product['product_quantity'],
            ];
        }

        return [
            'ga4' => [
                'transaction_id' => $order->reference,
                'value'          => (float) $order->total_paid_tax_incl,
                'currency'       => $currency->iso_code,
                'items'          => $items,
            ],
            'pixel' => [
                'content_ids'  => $contentIds,
                'content_type' => 'product',
                'value'        => (float) $order->total_paid_tax_incl,
                'currency'     => $currency->iso_code,
            ],
        ];
    }

    private function isOrderTracked($orderId)
    {
        return (bool) $this->context->cookie->{'analytics_tracked_' . (int) $orderId};
    }

    private function markOrderTracked($orderId)
    {
        $this->context->cookie->{'analytics_tracked_' . (int) $orderId} = 1;
    }

    public function hookActionAuthentication()
    {
        $this->context->cookie->analytics_login = 1;
    }

    public function hookActionCustomerAccountAdd()
    {
        $this->context->cookie->analytics_sign_up = 1;
    }

    public function hookDisplayHeader()
    {

        $this->context->controller->registerJavascript(
            'module-analytics-script',
            'modules/' . $this->name . '/views/js/script.js',
            [
                'position' => 'bottom',
                'priority' => 150,
            ]
        );

        $data = $this->data();

        if (!empty($this->context->cookie->analytics_login)) {
            $data['fire_login'] = true;
            unset($this->context->cookie->analytics_login);
        }

        if (!empty($this->context->cookie->analytics_sign_up)) {
            $data['fire_sign_up'] = true;
            unset($this->context->cookie->analytics_sign_up);
        }

        Media::addJsDef(['analyticsData' => $data]);
    }

    /**
     * Fires on the order-confirmation page (Sistecredito, MercadoPago).
     * Sends the purchase event directly as inline script.
     */
    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'];

        if ($this->isOrderTracked((int) $order->id)) {
            return '';
        }

        $payloads = $this->buildPurchasePayload($order);
        $this->markOrderTracked((int) $order->id);

        return '<script>
            if (typeof gtag === "function") {
                gtag("event", "purchase", ' . json_encode($payloads['ga4']) . ');
            }
            if (typeof fbq === "function") {
                fbq("track", "Purchase", ' . json_encode($payloads['pixel']) . ');
            }
        </script>';
    }

    private function add_customer($cookie, $id, $reference, $session_id, $session_number)
    {
        $search = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'order_status_analytics` WHERE id_order = ' . (int)$id);
        if (!$search) {
            $sql = Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'order_status_analytics` (id_order,id_customer,id_session,session_number,state,reference,status_update) VALUES
            (
                ' . (int)$id . ', "' . $cookie . '" , ' . $session_id . ' , ' . $session_number . ' , ' . 0 . ', "' . pSQL($reference) . '",' . 0 . '
            )'
            );
        }


        return $sql;
    }

    public function hookActionValidateOrder($params)
    {
        $cookie = $_COOKIE['ga_client_id'] ?? null;
        $session_id = (int)($_COOKIE['ga_session_id'] ?? 0);
        $session_number = (int)($_COOKIE['ga_session_number'] ?? 0);
        $id_order = (int)$params['order']->id;
        $reference = $params['order']->reference;

        $this->add_customer($cookie, $id_order, $reference, $session_id, $session_number);
    }

    private function searchClientAnalytics($id)
    {
        $search = 'SELECT id_customer, id_session, session_number FROM `' . _DB_PREFIX_ . 'order_status_analytics` WHERE id_order = ' .  (int)$id;
        $sql = Db::getInstance()->executeS($search);

        return $sql;
    }

    private function searchProduct($cart)
    {
        $search = 'SELECT p.id_product, pl.name, p.reference, p.price, cp.quantity FROM `' . _DB_PREFIX_ . 'cart_product` as cp 
        INNER JOIN  `' . _DB_PREFIX_ . 'product` as p on (cp.id_product = p.id_product)
        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl on (p.id_product = pl.id_product)
        where id_cart = ' . $cart->id;

        return Db::getInstance()->executeS($search);
    }


    private function updateAnalytics($id)
    {
        $cart = new Cart((int) $id->id_cart);
        $reference = $id->reference;
        $paid = $id->total_paid;

        $search = $this->searchProduct($cart);
        $searchClient = $this->searchClientAnalytics($id->id);
        $data = [];
        foreach ($search as $product) {
            $data[] = [
                'item_id' => $product['id_product'],
                'item_name' => $product['name'],
                'price' => (int)round($product['price']),
                'reference' => $product['reference'],
                'quantity' => (int)$product['quantity']
            ];
        }

        $eventData = [
            'client_id' => $searchClient[0]['id_customer'],
            'events' => [
                [
                    'name' => 'purchase',
                    'params' => [
                        'ga_session_id' => $searchClient[0]['id_session'],
                        'ga_session_number' => (int)$searchClient[0]['session_number'],
                        "engagement_time_msec" => "500",
                        'transaction_id' => $reference,
                        'value' => (int)$paid,
                        'currency' => "COP",
                        'payment_status' => 'approved',
                        'items' => $data

                    ]
                ]
            ]
        ];
        $event = new AddEvent();
        $event->purchase($eventData);
    }

    /**
     * Fires when order status changes to Payment Accepted.
     * Used as fallback when the customer is not redirected to order-confirmation
     * (e.g. PSE or Efecty that start as pending and later get approved while
     * the customer is still browsing). Stores the event in cookie for hookFooter.
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $paymentAcceptedStateId = (int) Configuration::get('PS_OS_PAYMENT');

        if ((int) $params['newOrderStatus']->id !== $paymentAcceptedStateId) {
            return;
        }

        $order = new Order((int) $params['id_order']);
        $this->updateAnalytics($order);
    }

    /**
     * Outputs any purchase event stored by hookActionOrderStatusPostUpdate.
     */
    public function hookFooter()
    {
        if (empty($this->context->cookie->analytics_pending_purchase)) {
            return '';
        }

        $payloads = json_decode($this->context->cookie->analytics_pending_purchase, true);
        unset($this->context->cookie->analytics_pending_purchase);

        return '<script>
            if (typeof gtag === "function") {
                gtag("event", "purchase", ' . json_encode($payloads['ga4']) . ');
            }
            if (typeof fbq === "function") {
                fbq("track", "Purchase", ' . json_encode($payloads['pixel']) . ');
            }
        </script>';
    }
}
