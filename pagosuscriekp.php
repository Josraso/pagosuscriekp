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
        Configuration::deleteByName('PAGOSUSCRIEKP_ORDER_STATE');
        Configuration::deleteByName('PAGOSUSCRIEKP_COMPLETED_STATE');

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
        // Crear estado "Pago por suscripción"
        $id_order_state = (int)Configuration::get('PAGOSUSCRIEKP_ORDER_STATE');

        if (!$id_order_state || !Validate::isLoadedObject(new OrderState($id_order_state))) {
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
            } else {
                return false;
            }
        }

        // Crear estado "Pago completado"
        $id_completed_state = (int)Configuration::get('PAGOSUSCRIEKP_COMPLETED_STATE');

        if (!$id_completed_state || !Validate::isLoadedObject(new OrderState($id_completed_state))) {
            $completedState = new OrderState();
            $completedState->name = array();
            $completedState->module_name = $this->name;
            $completedState->send_email = true;
            $completedState->color = '#108510';
            $completedState->hidden = false;
            $completedState->delivery = false;
            $completedState->logable = true;
            $completedState->invoice = true;
            $completedState->paid = true;

            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                $completedState->name[$language['id_lang']] = 'Pago completado';
            }

            if ($completedState->add()) {
                Configuration::updateValue('PAGOSUSCRIEKP_COMPLETED_STATE', (int)$completedState->id);
            } else {
                return false;
            }
        }

        return true;
    }

    public function getContent()
    {
        $this->html = '';

        // Procesar formularios
        if (Tools::isSubmit('submitPagoSuscriekpConfig')) {
            $this->postProcessConfig();
        } elseif (Tools::isSubmit('submitPlan')) {
            $this->postProcessPlan();
        } elseif (Tools::isSubmit('deleteplan')) {
            $this->postProcessDeletePlan();
        } elseif (Tools::isSubmit('markPaid')) {
            $this->postProcessMarkPaid();
        } elseif (Tools::isSubmit('markUnpaid')) {
            $this->postProcessMarkUnpaid();
        } elseif (Tools::isSubmit('cancelSubscription')) {
            $this->postProcessCancelSubscription();
        } elseif (Tools::isSubmit('reactivateSubscription')) {
            $this->postProcessReactivateSubscription();
        } elseif (Tools::isSubmit('markAllPaid')) {
            $this->postProcessMarkAllPaid();
        }

        // Mostrar vistas según acción
        if (Tools::isSubmit('addplan') || Tools::isSubmit('editplan')) {
            return $this->renderPlanForm();
        } elseif (Tools::isSubmit('viewsubscription')) {
            return $this->renderSubscriptionView();
        }

        // Determinar pestaña activa
        $active_tab = Tools::getValue('tab', 'config');

        // Renderizar pestañas
        $this->html .= $this->renderTabs($active_tab);

        // Renderizar contenido según pestaña
        switch ($active_tab) {
            case 'planes':
                $this->html .= $this->renderPlanesTab();
                break;
            case 'suscripciones':
                $this->html .= $this->renderSuscripcionesTab();
                break;
            case 'config':
            default:
                $this->html .= $this->renderConfigTab();
                break;
        }

        return $this->html;
    }

    /**
     * Helper para formatear fechas en español DD/MM/YYYY
     */
    private function formatDateES($date, $full = false)
    {
        if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($date);
        if ($full) {
            // Formato completo: 25/01/2025 14:30
            return date('d/m/Y H:i', $timestamp);
        } else {
            // Solo fecha: 25/01/2025
            return date('d/m/Y', $timestamp);
        }
    }

    /**
     * Renderizar pestañas de navegación
     */
    private function renderTabs($active_tab)
    {
        $base_url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $tabs = array(
            'config' => array(
                'name' => $this->l('Configuración'),
                'icon' => 'icon-cogs',
                'url' => $base_url . '&tab=config'
            ),
            'planes' => array(
                'name' => $this->l('Planes de Suscripción'),
                'icon' => 'icon-list-alt',
                'url' => $base_url . '&tab=planes'
            ),
            'suscripciones' => array(
                'name' => $this->l('Suscripciones'),
                'icon' => 'icon-users',
                'url' => $base_url . '&tab=suscripciones'
            ),
        );

        $html = '<div class="panel"><ul class="nav nav-tabs" role="tablist">';

        foreach ($tabs as $key => $tab) {
            $active_class = ($active_tab == $key) ? ' active' : '';
            $html .= '<li class="' . $active_class . '" role="presentation">
                        <a href="' . $tab['url'] . '">
                            <i class="' . $tab['icon'] . '"></i> ' . $tab['name'] . '
                        </a>
                      </li>';
        }

        $html .= '</ul></div>';

        return $html;
    }

    /**
     * Procesar configuración
     */
    private function postProcessConfig()
    {
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_OWNER', Tools::getValue('PAGOSUSCRIEKP_BANK_OWNER'));
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_DETAILS', Tools::getValue('PAGOSUSCRIEKP_BANK_DETAILS'));
        Configuration::updateValue('PAGOSUSCRIEKP_BANK_ADDRESS', Tools::getValue('PAGOSUSCRIEKP_BANK_ADDRESS'));
        Configuration::updateValue('PAGOSUSCRIEKP_REMINDER_DAYS', (int)Tools::getValue('PAGOSUSCRIEKP_REMINDER_DAYS'));

        $this->html .= $this->displayConfirmation($this->l('Configuración actualizada correctamente'));
    }

    /**
     * Procesar creación/edición de plan
     */
    private function postProcessPlan()
    {
        $id_plan = (int)Tools::getValue('id_plan');
        $name = pSQL(Tools::getValue('plan_name'));
        $id_product = (int)Tools::getValue('id_product');
        $id_product_attribute = (int)Tools::getValue('id_product_attribute');
        $active = (int)Tools::getValue('active');

        $installments_amounts = Tools::getValue('installment_amount');
        $installments_days = Tools::getValue('installment_days');

        if (!$name) {
            $this->html .= $this->displayError($this->l('El nombre del plan es obligatorio'));
            return;
        }

        if (empty($installments_amounts) || empty($installments_days)) {
            $this->html .= $this->displayError($this->l('Debe añadir al menos una cuota'));
            return;
        }

        // Guardar o actualizar el plan
        if ($id_plan > 0) {
            // Actualizar
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'pagosuscriekp_plan` SET
                    name = "' . $name . '",
                    id_product = ' . ($id_product > 0 ? $id_product : 'NULL') . ',
                    id_product_attribute = ' . ($id_product_attribute > 0 ? $id_product_attribute : 'NULL') . ',
                    active = ' . $active . ',
                    date_upd = NOW()
                    WHERE id_plan = ' . $id_plan;
            Db::getInstance()->execute($sql);

            // Eliminar cuotas antiguas
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` WHERE id_plan = ' . $id_plan);
        } else {
            // Insertar nuevo
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'pagosuscriekp_plan` (name, id_product, id_product_attribute, active, date_add, date_upd)
                    VALUES ("' . $name . '", '
                    . ($id_product > 0 ? $id_product : 'NULL') . ', '
                    . ($id_product_attribute > 0 ? $id_product_attribute : 'NULL') . ', '
                    . $active . ', NOW(), NOW())';
            Db::getInstance()->execute($sql);
            $id_plan = Db::getInstance()->Insert_ID();
        }

        // Insertar cuotas
        foreach ($installments_amounts as $index => $amount) {
            $days = (int)$installments_days[$index];
            $amount = (float)$amount;

            if ($amount > 0 && $days >= 0) {
                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` (id_plan, installment_number, amount, days_after_purchase)
                        VALUES (' . $id_plan . ', ' . ($index + 1) . ', ' . $amount . ', ' . $days . ')';
                Db::getInstance()->execute($sql);
            }
        }

        $this->html .= $this->displayConfirmation($this->l('Plan guardado correctamente'));

        // Redirigir a la pestaña de planes
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&tab=planes');
    }

    /**
     * Procesar eliminación de plan
     */
    private function postProcessDeletePlan()
    {
        $id_plan = (int)Tools::getValue('id_plan');

        // Eliminar cuotas
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` WHERE id_plan = ' . $id_plan);

        // Eliminar plan
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` WHERE id_plan = ' . $id_plan);

        $this->html .= $this->displayConfirmation($this->l('Plan eliminado correctamente'));
    }

    /**
     * Procesar marcar pago como pagado
     */
    private function postProcessMarkPaid()
    {
        $id_payment = (int)Tools::getValue('id_payment');
        $payment = new SubscriptionPayment($id_payment);

        if ($payment->markAsPaid()) {
            $this->html .= $this->displayConfirmation($this->l('Pago marcado como pagado correctamente'));
        } else {
            $this->html .= $this->displayError($this->l('Error al marcar el pago'));
        }
    }

    /**
     * Procesar marcar pago como no pagado
     */
    private function postProcessMarkUnpaid()
    {
        $id_payment = (int)Tools::getValue('id_payment');
        $payment = new SubscriptionPayment($id_payment);

        if ($payment->markAsUnpaid()) {
            $this->html .= $this->displayConfirmation($this->l('Pago desmarcado correctamente'));
        } else {
            $this->html .= $this->displayError($this->l('Error al desmarcar el pago'));
        }
    }

    /**
     * Procesar cancelación de suscripción
     */
    private function postProcessCancelSubscription()
    {
        $id_subscription = (int)Tools::getValue('id_subscription');
        $subscription = new Subscription($id_subscription);

        if ($subscription->cancel()) {
            $this->html .= $this->displayConfirmation($this->l('Suscripción cancelada correctamente'));
        } else {
            $this->html .= $this->displayError($this->l('Error al cancelar la suscripción'));
        }
    }

    /**
     * Procesar reactivación de suscripción
     */
    private function postProcessReactivateSubscription()
    {
        $id_subscription = (int)Tools::getValue('id_subscription');
        $subscription = new Subscription($id_subscription);

        if ($subscription->reactivate()) {
            $this->html .= $this->displayConfirmation($this->l('Suscripción reactivada correctamente'));
        } else {
            $this->html .= $this->displayError($this->l('Error al reactivar la suscripción'));
        }
    }

    /**
     * Procesar marcar todos los pagos como pagados
     */
    private function postProcessMarkAllPaid()
    {
        $id_subscription = (int)Tools::getValue('id_subscription');
        $subscription = new Subscription($id_subscription);

        if ($subscription->markAsCompleted()) {
            $this->html .= $this->displayConfirmation($this->l('Todos los pagos han sido marcados como pagados'));
        } else {
            $this->html .= $this->displayError($this->l('Error al marcar los pagos'));
        }
    }

    /**
     * Renderizar pestaña de configuración
     */
    private function renderConfigTab()
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

    /**
     * Renderizar pestaña de planes
     */
    private function renderPlanesTab()
    {
        $plans = $this->getPlans();
        $base_url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-list-alt"></i> ' . $this->l('Planes de Suscripción') . '
                <span class="badge">' . count($plans) . '</span>
                <span class="panel-heading-action">
                    <a class="btn btn-primary" href="' . $base_url . '&tab=planes&addplan=1">
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
                            <th>' . $this->l('Total') . '</th>
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

                // Calcular total del plan
                $total = 0;
                foreach ($installments as $inst) {
                    $total += $inst['amount'];
                }

                $html .= '<tr>
                    <td>' . $plan['id_plan'] . '</td>
                    <td><strong>' . htmlentities($plan['name']) . '</strong></td>
                    <td>' . $product_name . '</td>
                    <td>' . count($installments) . ' cuotas</td>
                    <td><strong>' . Tools::displayPrice($total) . '</strong></td>
                    <td>' . ($plan['active'] ? '<span class="badge badge-success">' . $this->l('Activo') . '</span>' : '<span class="badge badge-danger">' . $this->l('Inactivo') . '</span>') . '</td>
                    <td>
                        <a class="btn btn-default btn-sm" href="' . $base_url . '&tab=planes&editplan=1&id_plan=' . $plan['id_plan'] . '">
                            <i class="icon-edit"></i> ' . $this->l('Editar') . '
                        </a>
                        <a class="btn btn-danger btn-sm" href="' . $base_url . '&tab=planes&deleteplan=1&id_plan=' . $plan['id_plan'] . '" onclick="return confirm(\'' . $this->l('¿Eliminar este plan?') . '\')">
                            <i class="icon-trash"></i>
                        </a>
                    </td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="7" class="text-center">' . $this->l('No hay planes configurados') . '</td></tr>';
        }

        $html .= '</tbody>
                </table>
            </div>
        </div>';

        return $html;
    }

    /**
     * Renderizar pestaña de suscripciones
     */
    private function renderSuscripcionesTab()
    {
        $subscriptions = Subscription::getAll();
        $base_url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-users"></i> ' . $this->l('Suscripciones') . '
                <span class="badge">' . count($subscriptions) . '</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>' . $this->l('Cliente') . '</th>
                            <th>' . $this->l('Pedido') . '</th>
                            <th>' . $this->l('Producto') . '</th>
                            <th>' . $this->l('Fecha') . '</th>
                            <th>' . $this->l('Estado') . '</th>
                            <th>' . $this->l('Progreso') . '</th>
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

                $percentage = count($payments) > 0 ? round(($paid_count / count($payments)) * 100) : 0;

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
                    <td>' . htmlentities($customer->firstname . ' ' . $customer->lastname) . '</td>
                    <td><a href="' . $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $subscription->id_order . '&vieworder" target="_blank">#' . $subscription->id_order . '</a></td>
                    <td>' . $product_name . '</td>
                    <td>' . $this->formatDateES($subscription->date_add, false) . '</td>
                    <td>' . $status_badge . '</td>
                    <td>
                        <span class="badge">' . $paid_count . '/' . count($payments) . '</span>
                        <div class="progress" style="margin-bottom:0; width: 100px; display: inline-block;">
                            <div class="progress-bar progress-bar-success" style="width: ' . $percentage . '%">' . $percentage . '%</div>
                        </div>
                    </td>
                    <td>
                        <a class="btn btn-default btn-sm" href="' . $base_url . '&tab=suscripciones&viewsubscription=1&id_subscription=' . $subscription->id . '">
                            <i class="icon-eye"></i> ' . $this->l('Ver') . '
                        </a>
                    </td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="8" class="text-center">' . $this->l('No hay suscripciones registradas') . '</td></tr>';
        }

        $html .= '</tbody>
                </table>
            </div>
        </div>';

        return $html;
    }

    /**
     * Renderizar formulario de creación/edición de plan
     */
    private function renderPlanForm()
    {
        $id_plan = (int)Tools::getValue('id_plan');
        $editing = ($id_plan > 0);
        $base_url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        $plan_data = array();
        $installments = array();

        if ($editing) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` WHERE id_plan = ' . $id_plan;
            $plan_data = Db::getInstance()->getRow($sql);

            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment`
                    WHERE id_plan = ' . $id_plan . ' ORDER BY installment_number ASC';
            $installments = Db::getInstance()->executeS($sql);
        }

        // Si no hay cuotas, crear una por defecto
        if (empty($installments)) {
            $installments = array(
                array('amount' => '', 'days_after_purchase' => 0)
            );
        }

        // Obtener productos para el selector
        $products = Product::getProducts($this->context->language->id, 0, 0, 'name', 'ASC');

        // Crear el HTML del formulario manualmente
        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> ' . ($editing ? $this->l('Editar plan de suscripción') : $this->l('Crear nuevo plan de suscripción')) . '
            </div>
            <form action="' . $base_url . '&tab=planes" method="post" class="form-horizontal" id="planForm">
                <input type="hidden" name="submitPlan" value="1">
                ' . ($editing ? '<input type="hidden" name="id_plan" value="' . $id_plan . '">' : '') . '

                <div class="panel-body">

                    <!-- Nombre del plan -->
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">' . $this->l('Nombre del plan') . '</label>
                        <div class="col-lg-9">
                            <input type="text" name="plan_name" class="form-control"
                                   value="' . ($editing ? htmlentities($plan_data['name']) : '') . '" required>
                            <p class="help-block">' . $this->l('Ejemplo: "Plan 3 cuotas sin intereses", "Suscripción mensual"') . '</p>
                        </div>
                    </div>

                    <!-- Producto asociado -->
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Producto específico') . '</label>
                        <div class="col-lg-9">
                            <select name="id_product" id="id_product" class="form-control">
                                <option value="0">' . $this->l('-- Plan genérico (todos los productos) --') . '</option>';

        foreach ($products as $product) {
            $selected = ($editing && $plan_data['id_product'] == $product['id_product']) ? 'selected' : '';
            $html .= '<option value="' . $product['id_product'] . '" ' . $selected . '>' . htmlentities($product['name']) . '</option>';
        }

        $html .= '                </select>
                            <p class="help-block">' . $this->l('Si seleccionas un producto, este plan solo estará disponible para ese producto específico.') . '</p>
                        </div>
                    </div>

                    <!-- Combinación/Variante -->
                    <div class="form-group" id="combination_group" style="display:none;">
                        <label class="control-label col-lg-3">' . $this->l('Combinación específica') . '</label>
                        <div class="col-lg-9">
                            <select name="id_product_attribute" id="id_product_attribute" class="form-control">
                                <option value="0">' . $this->l('-- Todas las combinaciones --') . '</option>
                            </select>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div class="form-group">
                        <label class="control-label col-lg-3">' . $this->l('Estado') . '</label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="active" id="active_on" value="1" ' . (!$editing || $plan_data['active'] == 1 ? 'checked' : '') . '>
                                <label for="active_on">' . $this->l('Sí') . '</label>
                                <input type="radio" name="active" id="active_off" value="0" ' . ($editing && $plan_data['active'] == 0 ? 'checked' : '') . '>
                                <label for="active_off">' . $this->l('No') . '</label>
                                <a class="slide-button btn"></a>
                            </span>
                        </div>
                    </div>

                    <hr>

                    <!-- Cuotas del plan -->
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">' . $this->l('Cuotas del plan') . '</label>
                        <div class="col-lg-9">
                            <table class="table table-bordered" id="installments_table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;" class="text-center">#</th>
                                        <th>' . $this->l('Importe (€)') . '</th>
                                        <th>' . $this->l('Días después de la compra') . '</th>
                                        <th style="width: 80px;" class="text-center">' . $this->l('Acción') . '</th>
                                    </tr>
                                </thead>
                                <tbody id="installments_body">';

        $num = 1;
        foreach ($installments as $inst) {
            $html .= '<tr class="installment-row">
                        <td class="text-center"><strong class="installment-number">' . $num . '</strong></td>
                        <td>
                            <div class="input-group">
                                <input type="number" name="installment_amount[]" class="form-control"
                                       step="0.01" min="0.01" value="' . ($inst['amount'] !== '' ? $inst['amount'] : '') . '" required>
                                <span class="input-group-addon">€</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <input type="number" name="installment_days[]" class="form-control"
                                       min="0" value="' . $inst['days_after_purchase'] . '" required>
                                <span class="input-group-addon">' . $this->l('días') . '</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remove-installment">
                                <i class="icon-trash"></i>
                            </button>
                        </td>
                    </tr>';
            $num++;
        }

        $html .= '                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <button type="button" class="btn btn-default btn-sm" id="add_installment">
                                                <i class="icon-plus"></i> ' . $this->l('Añadir cuota') . '
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-right"><strong>' . $this->l('Total del plan:') . '</strong></td>
                                        <td class="text-center"><strong id="plan_total">0.00 €</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="panel-footer">
                    <a href="' . $base_url . '&tab=planes" class="btn btn-default">
                        <i class="process-icon-cancel"></i> ' . $this->l('Cancelar') . '
                    </a>
                    <button type="submit" name="submitPlan" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> ' . $this->l('Guardar plan') . '
                    </button>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        $(document).ready(function() {

            function calculatePlanTotal() {
                var total = 0;
                $("#installments_body input[name=\'installment_amount[]\']").each(function() {
                    var amount = parseFloat($(this).val()) || 0;
                    total += amount;
                });
                $("#plan_total").text(total.toFixed(2) + " €");
            }

            function updateInstallmentNumbers() {
                $("#installments_body tr.installment-row").each(function(index) {
                    $(this).find(".installment-number").text(index + 1);
                });
            }

            $("#add_installment").on("click", function() {
                var newRow = `<tr class="installment-row">
                    <td class="text-center"><strong class="installment-number"></strong></td>
                    <td>
                        <div class="input-group">
                            <input type="number" name="installment_amount[]" class="form-control"
                                   step="0.01" min="0.01" required>
                            <span class="input-group-addon">€</span>
                        </div>
                    </td>
                    <td>
                        <div class="input-group">
                            <input type="number" name="installment_days[]" class="form-control"
                                   min="0" value="30" required>
                            <span class="input-group-addon">' . $this->l('días') . '</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-installment">
                            <i class="icon-trash"></i>
                        </button>
                    </td>
                </tr>`;
                $("#installments_body").append(newRow);
                updateInstallmentNumbers();
                calculatePlanTotal();
            });

            $(document).on("click", ".remove-installment", function() {
                if ($("#installments_body tr.installment-row").length > 1) {
                    $(this).closest("tr").remove();
                    updateInstallmentNumbers();
                    calculatePlanTotal();
                } else {
                    alert("' . $this->l('Debe haber al menos una cuota') . '");
                }
            });

            $(document).on("input", "input[name=\'installment_amount[]\']", function() {
                calculatePlanTotal();
            });

            $("#id_product").on("change", function() {
                var id_product = $(this).val();
                if (id_product > 0) {
                    $.ajax({
                        url: "' . $base_url . '&ajax=1&action=getCombinations",
                        data: { id_product: id_product },
                        dataType: "json",
                        success: function(data) {
                            var options = \'<option value="0">' . $this->l('-- Todas las combinaciones --') . '</option>\';
                            if (data && data.length > 0) {
                                $.each(data, function(index, combination) {
                                    options += \'<option value="\' + combination.id_product_attribute + \'">\' + combination.name + \'</option>\';
                                });
                                $("#combination_group").show();
                            } else {
                                $("#combination_group").hide();
                            }
                            $("#id_product_attribute").html(options);
                        }
                    });
                } else {
                    $("#combination_group").hide();
                }
            });

            updateInstallmentNumbers();
            calculatePlanTotal();

            if ($("#id_product").val() > 0) {
                $("#id_product").trigger("change");
            }
        });
        </script>';

        return $html;
    }

    /**
     * Renderizar vista de detalles de suscripción
     */
    private function renderSubscriptionView()
    {
        $id_subscription = (int)Tools::getValue('id_subscription');
        $subscription = new Subscription($id_subscription);
        $base_url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        if (!Validate::isLoadedObject($subscription)) {
            return $this->displayError($this->l('Suscripción no encontrada'));
        }

        $customer = new Customer($subscription->id_customer);
        $order = new Order($subscription->id_order);
        $payments = $subscription->getPayments();

        $total_amount = $subscription->getTotalAmount();
        $paid_amount = $subscription->getPaidAmount();
        $pending_amount = $subscription->getPendingAmount();
        $is_fully_paid = $subscription->isFullyPaid();

        $percentage = $total_amount > 0 ? round(($paid_amount / $total_amount) * 100) : 0;

        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-user"></i> ' . $this->l('Detalles de la suscripción') . ' #' . $subscription->id . '
                ' . ($subscription->status == 'active'
                    ? '<span class="badge badge-success">' . $this->l('Activa') . '</span>'
                    : '<span class="badge badge-warning">' . $this->l('Cancelada') . '</span>') . '
            </div>
            <div class="panel-body">

                <div class="row">
                    <div class="col-md-6">
                        <h4>' . $this->l('Información del cliente') . '</h4>
                        <dl class="well list-detail">
                            <dt>' . $this->l('Cliente:') . '</dt>
                            <dd><a href="' . $this->context->link->getAdminLink('AdminCustomers') . '&id_customer=' . $customer->id . '&viewcustomer" target="_blank">' . htmlentities($customer->firstname . ' ' . $customer->lastname) . '</a></dd>

                            <dt>' . $this->l('Email:') . '</dt>
                            <dd>' . htmlentities($customer->email) . '</dd>

                            <dt>' . $this->l('Pedido:') . '</dt>
                            <dd><a href="' . $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder" target="_blank">' . $order->reference . '</a></dd>

                            <dt>' . $this->l('Fecha de creación:') . '</dt>
                            <dd>' . $this->formatDateES($subscription->date_add, true) . '</dd>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <h4>' . $this->l('Resumen financiero') . '</h4>
                        <dl class="well list-detail">
                            <dt>' . $this->l('Total suscripción:') . '</dt>
                            <dd><strong style="font-size: 18px;">' . Tools::displayPrice($total_amount) . '</strong></dd>

                            <dt>' . $this->l('Total pagado:') . '</dt>
                            <dd><strong style="color: #27ae60; font-size: 16px;">' . Tools::displayPrice($paid_amount) . '</strong></dd>

                            <dt>' . $this->l('Pendiente:') . '</dt>
                            <dd><strong style="color: #e74c3c; font-size: 16px;">' . Tools::displayPrice($pending_amount) . '</strong></dd>

                            <dt>' . $this->l('Progreso:') . '</dt>
                            <dd>
                                <div class="progress">
                                    <div class="progress-bar progress-bar-success" style="width: ' . $percentage . '%">' . $percentage . '%</div>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-12">
                        <h4>' . $this->l('Acciones') . '</h4>
                        <div class="btn-group">';

        if ($subscription->status == 'active' && !$is_fully_paid) {
            $html .= '<a href="' . $base_url . '&tab=suscripciones&viewsubscription=1&id_subscription=' . $subscription->id . '&markAllPaid=1"
                           class="btn btn-success"
                           onclick="return confirm(\'' . $this->l('¿Marcar todos los pagos como pagados?') . '\');">
                            <i class="icon-check"></i> ' . $this->l('Marcar todo como pagado') . '
                        </a>';
        }

        if ($subscription->status == 'active') {
            $html .= '<a href="' . $base_url . '&tab=suscripciones&cancelSubscription=1&id_subscription=' . $subscription->id . '"
                           class="btn btn-warning"
                           onclick="return confirm(\'' . $this->l('¿Cancelar esta suscripción?') . '\');">
                            <i class="icon-times"></i> ' . $this->l('Cancelar suscripción') . '
                        </a>';
        } else {
            $html .= '<a href="' . $base_url . '&tab=suscripciones&reactivateSubscription=1&id_subscription=' . $subscription->id . '"
                           class="btn btn-success"
                           onclick="return confirm(\'' . $this->l('¿Reactivar esta suscripción?') . '\');">
                            <i class="icon-check"></i> ' . $this->l('Reactivar suscripción') . '
                        </a>';
        }

        $html .= '        </div>
                    </div>
                </div>

                <hr>

                <h4>' . $this->l('Pagos programados') . '</h4>';

        if (count($payments) > 0) {
            $html .= '<div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th>' . $this->l('Importe') . '</th>
                                <th>' . $this->l('Fecha vencimiento') . '</th>
                                <th class="text-center">' . $this->l('Estado') . '</th>
                                <th>' . $this->l('Fecha de pago') . '</th>
                                <th class="text-center" style="width: 150px;">' . $this->l('Acciones') . '</th>
                            </tr>
                        </thead>
                        <tbody>';

            $num = 1;
            $today = date('Y-m-d');
            foreach ($payments as $payment) {
                $is_overdue = ($payment['due_date'] < $today && !$payment['paid']);
                $row_class = $payment['paid'] ? 'success' : ($is_overdue ? 'danger' : 'warning');

                $html .= '<tr class="' . $row_class . '">
                    <td class="text-center"><strong>' . $num . '</strong></td>
                    <td><strong style="font-size: 16px;">' . Tools::displayPrice($payment['amount']) . '</strong></td>
                    <td>' . $this->formatDateES($payment['due_date'], false);

                if ($is_overdue) {
                    $html .= '<br><span class="badge badge-danger"><i class="icon-warning"></i> ' . $this->l('Vencido') . '</span>';
                }

                $html .= '</td>
                    <td class="text-center">';

                if ($payment['paid']) {
                    $html .= '<span class="badge badge-success"><i class="icon-check"></i> ' . $this->l('Pagado') . '</span>';
                } else {
                    $html .= '<span class="badge badge-warning"><i class="icon-time"></i> ' . $this->l('Pendiente') . '</span>';
                }

                $html .= '</td>
                    <td>' . ($payment['date_paid'] ? $this->formatDateES($payment['date_paid'], true) : '<span class="text-muted">-</span>') . '</td>
                    <td class="text-center">';

                if (!$payment['paid']) {
                    $html .= '<a href="' . $base_url . '&tab=suscripciones&viewsubscription=1&id_subscription=' . $subscription->id . '&markPaid=1&id_payment=' . $payment['id_payment'] . '"
                               class="btn btn-success btn-xs"
                               onclick="return confirm(\'' . $this->l('¿Marcar este pago como pagado?') . '\');">
                                <i class="icon-check"></i> ' . $this->l('Marcar pagado') . '
                            </a>';
                } else {
                    $html .= '<a href="' . $base_url . '&tab=suscripciones&viewsubscription=1&id_subscription=' . $subscription->id . '&markUnpaid=1&id_payment=' . $payment['id_payment'] . '"
                               class="btn btn-warning btn-xs"
                               onclick="return confirm(\'' . $this->l('¿Desmarcar este pago?') . '\');">
                                <i class="icon-undo"></i> ' . $this->l('Desmarcar') . '
                            </a>';
                }

                $html .= '</td>
                </tr>';
                $num++;
            }

            $html .= '</tbody>
                    </table>
                </div>';
        } else {
            $html .= '<div class="alert alert-warning"><i class="icon-warning"></i> ' . $this->l('No hay pagos registrados para esta suscripción.') . '</div>';
        }

        $html .= '    </div>
            <div class="panel-footer">
                <a href="' . $base_url . '&tab=suscripciones" class="btn btn-default">
                    <i class="icon-arrow-left"></i> ' . $this->l('Volver al listado') . '
                </a>
            </div>
        </div>

        <style>
        .list-detail dt {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        .list-detail dd {
            margin-bottom: 15px;
        }
        .table tbody tr.success {
            background-color: #dff0d8;
        }
        .table tbody tr.warning {
            background-color: #fcf8e3;
        }
        .table tbody tr.danger {
            background-color: #f2dede;
        }
        .progress {
            height: 30px;
            margin-bottom: 0;
        }
        .progress-bar {
            line-height: 30px;
            font-weight: bold;
        }
        </style>';

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
            '{due_date}' => $this->formatDateES($payment_data['due_date'], false),
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