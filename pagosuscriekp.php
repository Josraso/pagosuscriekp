<?php
/**
 * Módulo de Pago por Suscripción
 *
 * @author    Tu Nombre
 * @copyright 2025
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Subscription.php';
require_once dirname(__FILE__) . '/classes/SubscriptionPayment.php';

class PagoSuscriekp extends PaymentModule
{
    private $html = '';
    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'pagosuscriekp';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Tu Nombre';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->module_key = '';

        parent::__construct();

        $this->displayName = $this->l('Pago por Suscripción');
        $this->description = $this->l('Permite a los clientes pagar mediante suscripción/fraccionamiento de pagos');
        $this->confirmUninstall = $this->l('¿Estás seguro de desinstalar este módulo?');
    }

    public function install()
    {
        if (!parent::install()
            || !$this->installDb()
            || !$this->createOrderState()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayAdminOrder')
            || !$this->registerHook('actionCronJob')
        ) {
            return false;
        }

        // Crear valores de configuración por defecto
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_OWNER', '');
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_DETAILS', '');
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_ADDRESS', '');
        Configuration::updateValue('PAGOSUSCRIEKP_REMINDER_DAYS', 3);

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()
            || !$this->uninstallDb()
        ) {
            return false;
        }

        // Eliminar configuraciones
        Configuration::deleteByName('PAGOSUSCRIEKP_BANK_OWNER');
        Configuration::deleteByName('PAGOSUSCRIEKP_BANK_DETAILS');
        Configuration::deleteByName('PAGOSUSCRIEKP_BANK_ADDRESS');
        Configuration::deleteByName('PAGOSUSCRIEKP_REMINDER_DAYS');

        return true;
    }

    private function installDb()
    {
        $sql = array();

        // Tabla de suscripciones
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` (
            `id_subscription` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` int(11) NOT NULL,
            `id_customer` int(11) NOT NULL,
            `id_product` int(11) DEFAULT NULL,
            `id_product_attribute` int(11) DEFAULT NULL,
            `status` varchar(50) NOT NULL DEFAULT "active",
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_subscription`),
            KEY `id_order` (`id_order`),
            KEY `id_customer` (`id_customer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla de pagos de suscripción
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_payment` (
            `id_payment` int(11) NOT NULL AUTO_INCREMENT,
            `id_subscription` int(11) NOT NULL,
            `amount` decimal(20,6) NOT NULL,
            `due_date` date NOT NULL,
            `paid` tinyint(1) NOT NULL DEFAULT 0,
            `date_paid` datetime DEFAULT NULL,
            `id_order_payment` int(11) DEFAULT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_payment`),
            KEY `id_subscription` (`id_subscription`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla de planes de suscripción
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_plan` (
            `id_plan` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `id_product` int(11) DEFAULT NULL,
            `id_product_attribute` int(11) DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_plan`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Tabla de cuotas del plan
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` (
            `id_installment` int(11) NOT NULL AUTO_INCREMENT,
            `id_plan` int(11) NOT NULL,
            `installment_number` int(11) NOT NULL,
            `amount` decimal(20,6) NOT NULL,
            `days_after_purchase` int(11) NOT NULL,
            PRIMARY KEY (`id_installment`),
            KEY `id_plan` (`id_plan`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb()
    {
        $sql = array(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_payment`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_subscription`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'pagosuscriekp_plan`',
        );

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function createOrderState()
    {
        // Verificar si ya existe
        $id_order_state = (int)Configuration::get('PAGOSUSCRIEKP_ORDER_STATE');
        
        if ($id_order_state && Validate::isLoadedObject(new OrderState($id_order_state))) {
            return true;
        }

        $orderState = new OrderState();
        $orderState->name = array();
        $orderState->module_name = $this->name;
        $orderState->send_email = false;
        $orderState->color = '#34209E';
        $orderState->hidden = false;
        $orderState->delivery = false;
        $orderState->logable = false;
        $orderState->invoice = true;
        $orderState->paid = false;

        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $orderState->name[$language['id_lang']] = 'Pago por suscripción';
        }

        if ($orderState->add()) {
            Configuration::updateValue('PAGOSUSCRIEKP_ORDER_STATE', (int)$orderState->id);
            return true;
        }

        return false;
    }

    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('submitPagoSuscriekpConfig')) {
            $this->postProcess();
        }

        $this->html .= $this->renderConfigForm();
        $this->html .= $this->renderPlansList();
        
        return $this->html;
    }

    private function postProcess()
    {
        if (Tools::isSubmit('submitPagoSuscriekpConfig')) {
            Configuration::updateValue('PAGOSUSCRIEKP_BANK_OWNER', Tools::getValue('PAGOSUSCRIEKP_BANK_OWNER'));
            Configuration::updateValue('PAGOSUSCRIEKP_BANK_DETAILS', Tools::getValue('PAGOSUSCRIEKP_BANK_DETAILS'));
            Configuration::updateValue('PAGOSUSCRIEKP_BANK_ADDRESS', Tools::getValue('PAGOSUSCRIEKP_BANK_ADDRESS'));
            Configuration::updateValue('PAGOSUSCRIEKP_REMINDER_DAYS', (int)Tools::getValue('PAGOSUSCRIEKP_REMINDER_DAYS'));

            $this->html .= $this->displayConfirmation($this->l('Configuración actualizada correctamente'));
        }
    }

    private function renderConfigForm()
    {
        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuración General'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Titular de la cuenta'),
                        'name' => 'PAGOSUSCRIEKP_BANK_OWNER',
                        'required' => true,
                        'desc' => $this->l('Nombre del titular de la cuenta bancaria')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Datos bancarios'),
                        'name' => 'PAGOSUSCRIEKP_BANK_DETAILS',
                        'required' => true,
                        'desc' => $this->l('IBAN, BIC/SWIFT y otros datos necesarios'),
                        'rows' => 5
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Dirección del banco'),
                        'name' => 'PAGOSUSCRIEKP_BANK_ADDRESS',
                        'rows' => 3
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Días de aviso antes del vencimiento'),
                        'name' => 'PAGOSUSCRIEKP_REMINDER_DAYS',
                        'required' => true,
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Número de días antes del vencimiento para enviar el recordatorio')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar configuración'),
                    'class' => 'btn btn-default pull-right'
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPagoSuscriekpConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => array(
                'PAGOSUSCRIEKP_BANK_OWNER' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
                'PAGOSUSCRIEKP_BANK_DETAILS' => Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS'),
                'PAGOSUSCRIEKP_BANK_ADDRESS' => Configuration::get('PAGOSUSCRIEKP_BANK_ADDRESS'),
                'PAGOSUSCRIEKP_REMINDER_DAYS' => Configuration::get('PAGOSUSCRIEKP_REMINDER_DAYS'),
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fieldsForm));
    }

    private function renderPlansList()
    {
        $plans = $this->getPlans();

        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-list"></i> ' . $this->l('Planes de suscripción') . '
                <span class="badge">' . count($plans) . '</span>
                <span class="panel-heading-action">
                    <a class="btn btn-primary" href="' . $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&addplan=1">
                        <i class="icon-plus"></i> ' . $this->l('Añadir plan') . '
                    </a>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>' . $this->l('Nombre') . '</th>
                            <th>' . $this->l('Producto') . '</th>
                            <th>' . $this->l('Cuotas') . '</th>
                            <th>' . $this->l('Estado') . '</th>
                            <th>' . $this->l('Acciones') . '</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (count($plans) > 0) {
            foreach ($plans as $plan) {
                $product_name = $this->l('Todos los productos');
                if ($plan['id_product']) {
                    $product = new Product($plan['id_product'], false, $this->context->language->id);
                    $product_name = $product->name;
                    
                    if ($plan['id_product_attribute']) {
                        $combination = new Combination($plan['id_product_attribute']);
                        $attributes = $combination->getAttributesName($this->context->language->id);
                        $attr_names = array();
                        foreach ($attributes as $attr) {
                            $attr_names[] = $attr['name'];
                        }
                        $product_name .= ' - ' . implode(', ', $attr_names);
                    }
                }

                $installments = $this->getPlanInstallments($plan['id_plan']);
                
                $html .= '<tr>
                    <td>' . $plan['id_plan'] . '</td>
                    <td><strong>' . $plan['name'] . '</strong></td>
                    <td>' . $product_name . '</td>
                    <td>' . count($installments) . ' cuotas</td>
                    <td>' . ($plan['active'] ? '<span class="badge badge-success">' . $this->l('Activo') . '</span>' : '<span class="badge badge-danger">' . $this->l('Inactivo') . '</span>') . '</td>
                    <td>
                        <a class="btn btn-default btn-sm" href="' . $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&editplan=' . $plan['id_plan'] . '">
                            <i class="icon-edit"></i> ' . $this->l('Editar') . '
                        </a>
                        <a class="btn btn-default btn-sm" href="' . $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&deleteplan=' . $plan['id_plan'] . '" onclick="return confirm(\'' . $this->l('¿Eliminar este plan?') . '\')">
                            <i class="icon-trash"></i> ' . $this->l('Eliminar') . '
                        </a>
                    </td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">' . $this->l('No hay planes configurados') . '</td></tr>';
        }

        $html .= '</tbody>
                </table>
            </div>
        </div>';

        // Gestión de suscripciones activas
        $html .= $this->renderSubscriptionsList();

        return $html;
    }

    private function renderSubscriptionsList()
    {
        $subscriptions = Subscription::getAll();

        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-users"></i> ' . $this->l('Suscripciones activas') . '
                <span class="badge">' . count($subscriptions) . '</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>' . $this->l('Cliente') . '</th>
                            <th>' . $this->l('Pedido') . '</th>
                            <th>' . $this->l('Producto') . '</th>
                            <th>' . $this->l('Estado') . '</th>
                            <th>' . $this->l('Pagos') . '</th>
                            <th>' . $this->l('Acciones') . '</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (count($subscriptions) > 0) {
            foreach ($subscriptions as $sub) {
                $subscription = new Subscription($sub['id_subscription']);
                $customer = new Customer($subscription->id_customer);
                $payments = $subscription->getPayments();
                
                $paid_count = 0;
                foreach ($payments as $payment) {
                    if ($payment['paid']) {
                        $paid_count++;
                    }
                }

                $product_name = $this->l('Genérico');
                if ($subscription->id_product) {
                    $product = new Product($subscription->id_product, false, $this->context->language->id);
                    $product_name = $product->name;
                }

                $status_badge = $subscription->status == 'active' 
                    ? '<span class="badge badge-success">' . $this->l('Activa') . '</span>' 
                    : '<span class="badge badge-warning">' . $this->l('Cancelada') . '</span>';

                $html .= '<tr>
                    <td>' . $subscription->id . '</td>
                    <td>' . $customer->firstname . ' ' . $customer->lastname . '</td>
                    <td><a href="' . $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $subscription->id_order . '&vieworder" target="_blank">#' . $subscription->id_order . '</a></td>
                    <td>' . $product_name . '</td>
                    <td>' . $status_badge . '</td>
                    <td>' . $paid_count . '/' . count($payments) . ' pagados</td>
                    <td>
                        <a class="btn btn-default btn-sm" href="' . $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&viewsubscription=' . $subscription->id . '">
                            <i class="icon-eye"></i> ' . $this->l('Ver detalles') . '
                        </a>
                    </td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="7" class="text-center">' . $this->l('No hay suscripciones') . '</td></tr>';
        }

        $html .= '</tbody>
                </table>
            </div>
        </div>';

        return $html;
    }

    private function getPlans()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` ORDER BY id_plan DESC';
        return Db::getInstance()->executeS($sql);
    }

    private function getPlanInstallments($id_plan)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` 
                WHERE id_plan = ' . (int)$id_plan . ' 
                ORDER BY installment_number ASC';
        return Db::getInstance()->executeS($sql);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $cart = $params['cart'];
        
        // Verificar si existe un plan aplicable para los productos del carrito
        $availablePlans = $this->getAvailablePlansForCart($cart);
        
        if (empty($availablePlans)) {
            return;
        }

        $payment_options = array();

        foreach ($availablePlans as $plan) {
            $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $newOption->setCallToActionText($this->l('Pago por suscripción') . ' - ' . $plan['name'])
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array('id_plan' => $plan['id_plan']), true))
                ->setAdditionalInformation($this->generatePaymentInfo($plan))
                ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment.png'));

            $payment_options[] = $newOption;
        }

        return $payment_options;
    }

    private function getAvailablePlansForCart($cart)
    {
        $products = $cart->getProducts();
        $plans = array();

        // Primero buscar planes genéricos (sin producto específico)
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` 
                WHERE active = 1 AND id_product IS NULL';
        $generic_plans = Db::getInstance()->executeS($sql);
        
        if ($generic_plans) {
            $plans = array_merge($plans, $generic_plans);
        }

        // Luego buscar planes específicos para los productos del carrito
        foreach ($products as $product) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` 
                    WHERE active = 1 
                    AND id_product = ' . (int)$product['id_product'];
            
            if (isset($product['id_product_attribute']) && $product['id_product_attribute']) {
                $sql .= ' AND (id_product_attribute = ' . (int)$product['id_product_attribute'] . ' OR id_product_attribute IS NULL)';
            } else {
                $sql .= ' AND id_product_attribute IS NULL';
            }

            $product_plans = Db::getInstance()->executeS($sql);
            if ($product_plans) {
                $plans = array_merge($plans, $product_plans);
            }
        }

        // Eliminar duplicados
        $unique_plans = array();
        foreach ($plans as $plan) {
            $unique_plans[$plan['id_plan']] = $plan;
        }

        return array_values($unique_plans);
    }

    private function generatePaymentInfo($plan)
    {
        $installments = $this->getPlanInstallments($plan['id_plan']);
        
        $this->context->smarty->assign(array(
            'plan' => $plan,
            'installments' => $installments,
            'bank_owner' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
            'bank_details' => nl2br(Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS')),
            'bank_address' => nl2br(Configuration::get('PAGOSUSCRIEKP_BANK_ADDRESS')),
        ));

        return $this->context->smarty->fetch('module:pagosuscriekp/views/templates/hook/payment_infos.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['order'];

        if ($order->getCurrentState() != Configuration::get('PAGOSUSCRIEKP_ORDER_STATE')) {
            return;
        }

        $subscription = Subscription::getByOrderId($order->id);
        
        if (!$subscription) {
            return;
        }

        $payments = $subscription->getPayments();

        $this->context->smarty->assign(array(
            'subscription' => $subscription,
            'payments' => $payments,
            'bank_owner' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
            'bank_details' => nl2br(Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS')),
            'bank_address' => nl2br(Configuration::get('PAGOSUSCRIEKP_BANK_ADDRESS')),
            'shop_name' => $this->context->shop->name,
        ));

        return $this->fetch('module:pagosuscriekp/views/templates/hook/payment_return.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        $id_order = $params['id_order'];
        $subscription = Subscription::getByOrderId($id_order);

        if (!$subscription) {
            return;
        }

        $payments = $subscription->getPayments();

        $this->context->smarty->assign(array(
            'subscription' => $subscription,
            'payments' => $payments,
            'module_link' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name,
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }

    public function hookActionCronJob()
    {
        $this->sendPaymentReminders();
    }

    private function sendPaymentReminders()
    {
        $reminder_days = (int)Configuration::get('PAGOSUSCRIEKP_REMINDER_DAYS');
        $target_date = date('Y-m-d', strtotime('+' . $reminder_days . ' days'));

        // Obtener pagos pendientes que vencen en la fecha objetivo
        $sql = 'SELECT p.*, s.id_customer, s.id_order 
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` p
                INNER JOIN `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` s ON (p.id_subscription = s.id_subscription)
                WHERE p.paid = 0 
                AND p.due_date = "' . pSQL($target_date) . '"
                AND s.status = "active"';

        $payments = Db::getInstance()->executeS($sql);

        foreach ($payments as $payment_data) {
            $this->sendReminderEmail($payment_data);
        }

        return true;
    }

    private function sendReminderEmail($payment_data)
    {
        $customer = new Customer($payment_data['id_customer']);
        $order = new Order($payment_data['id_order']);
        
        $templateVars = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{order_reference}' => $order->reference,
            '{amount}' => Tools::displayPrice($payment_data['amount']),
            '{due_date}' => Tools::displayDate($payment_data['due_date']),
            '{bank_owner}' => Configuration::get('PAGOSUSCRIEKP_BANK_OWNER'),
            '{bank_details}' => Configuration::get('PAGOSUSCRIEKP_BANK_DETAILS'),
        );

        Mail::Send(
            (int)$order->id_lang,
            'payment_reminder',
            $this->l('Recordatorio de pago pendiente'),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/mails/',
            false,
            (int)$order->id_shop
        );
    }
	/**
     * Procesar peticiones AJAX
     */
    public function hookDisplayBackOfficeHeader()
    {
        // Manejar peticiones AJAX
        if (Tools::getValue('ajax') && Tools::getValue('action') == 'getCombinations') {
            $this->ajaxGetCombinations();
        }
    }

    /**
     * Obtener combinaciones de un producto via AJAX
     */
    private function ajaxGetCombinations()
    {
        $id_product = (int)Tools::getValue('id_product');
        
        if (!$id_product) {
            die(json_encode(array('error' => 'Invalid product ID')));
        }

        $product = new Product($id_product);
        
        if (!Validate::isLoadedObject($product)) {
            die(json_encode(array('error' => 'Product not found')));
        }

        $combinations = $product->getAttributeCombinations($this->context->language->id);
        
        if (!$combinations) {
            die(json_encode(array()));
        }

        // Agrupar por id_product_attribute
        $grouped = array();
        foreach ($combinations as $combination) {
            $id_attr = $combination['id_product_attribute'];
            if (!isset($grouped[$id_attr])) {
                $grouped[$id_attr] = array(
                    'id_product_attribute' => $id_attr,
                    'name' => array()
                );
            }
            $grouped[$id_attr]['name'][] = $combination['attribute_name'];
        }

        // Formatear resultado
        $result = array();
        foreach ($grouped as $id_attr => $data) {
            $result[] = array(
                'id_product_attribute' => $id_attr,
                'name' => implode(', ', $data['name'])
            );
        }

        die(json_encode($result));
    }
}