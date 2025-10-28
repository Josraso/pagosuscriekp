<?php
/**
 * Controlador administrativo para gestión de suscripciones
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/Subscription.php';
require_once dirname(__FILE__) . '/../../classes/SubscriptionPayment.php';

class AdminPagoSuscriekpController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'pagosuscriekp_subscription';
        $this->className = 'Subscription';
        $this->identifier = 'id_subscription';
        
        parent::__construct();

        $this->addRowAction('view');
        $this->addRowAction('cancel');
        $this->addRowAction('reactivate');

        $this->bulk_actions = array(
            'cancel' => array(
                'text' => $this->l('Cancelar seleccionados'),
                'icon' => 'icon-times',
                'confirm' => $this->l('¿Cancelar las suscripciones seleccionadas?')
            )
        );

        $this->fields_list = array(
            'id_subscription' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'id_order' => array(
                'title' => $this->l('Pedido'),
                'align' => 'center',
                'callback' => 'displayOrderLink'
            ),
            'customer_name' => array(
                'title' => $this->l('Cliente'),
                'filter_key' => 'customer',
                'callback' => 'displayCustomerName'
            ),
            'product_name' => array(
                'title' => $this->l('Producto'),
                'callback' => 'displayProductName'
            ),
            'total_amount' => array(
                'title' => $this->l('Total'),
                'callback' => 'displayTotalAmount',
                'align' => 'right'
            ),
            'paid_amount' => array(
                'title' => $this->l('Pagado'),
                'callback' => 'displayPaidAmount',
                'align' => 'right'
            ),
            'status' => array(
                'title' => $this->l('Estado'),
                'align' => 'center',
                'callback' => 'displayStatus'
            ),
            'date_add' => array(
                'title' => $this->l('Fecha'),
                'type' => 'date',
                'align' => 'right'
            )
        );
    }

    public function renderList()
    {
        // Añadir botón para crear plan
        $this->toolbar_btn['new'] = array(
            'href' => self::$currentIndex . '&addplan&token=' . $this->token,
            'desc' => $this->l('Añadir plan de suscripción')
        );

        $this->addRowActionSkipList('cancel', array_keys($this->getCancelledSubscriptions()));
        $this->addRowActionSkipList('reactivate', array_keys($this->getActiveSubscriptions()));

        return parent::renderList();
    }

    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

        // Añadir información adicional a cada fila
        foreach ($this->_list as &$row) {
            $subscription = new Subscription($row['id_subscription']);
            $row['total_amount'] = $subscription->getTotalAmount();
            $row['paid_amount'] = $subscription->getPaidAmount();
            
            $customer = new Customer($row['id_customer']);
            $row['customer_name'] = $customer->firstname . ' ' . $customer->lastname;
            
            if ($row['id_product']) {
                $product = new Product($row['id_product'], false, $this->context->language->id);
                $row['product_name'] = $product->name;
            } else {
                $row['product_name'] = $this->l('Genérico');
            }
        }
    }

    private function getActiveSubscriptions()
    {
        $sql = 'SELECT id_subscription FROM `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` WHERE status = "active"';
        $results = Db::getInstance()->executeS($sql);
        $list = array();
        foreach ($results as $result) {
            $list[$result['id_subscription']] = true;
        }
        return $list;
    }

    private function getCancelledSubscriptions()
    {
        $sql = 'SELECT id_subscription FROM `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` WHERE status = "cancelled"';
        $results = Db::getInstance()->executeS($sql);
        $list = array();
        foreach ($results as $result) {
            $list[$result['id_subscription']] = true;
        }
        return $list;
    }

    public function displayOrderLink($id_order)
    {
        return '<a href="' . $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . (int)$id_order . '&vieworder" target="_blank">#' . (int)$id_order . '</a>';
    }

    public function displayCustomerName($customer_name, $row)
    {
        $customer = new Customer($row['id_customer']);
        return '<a href="' . $this->context->link->getAdminLink('AdminCustomers') . '&id_customer=' . (int)$customer->id . '&viewcustomer" target="_blank">' . $customer_name . '</a>';
    }

    public function displayProductName($product_name, $row)
    {
        if (!$row['id_product']) {
            return '<span class="badge badge-info">' . $this->l('Genérico') . '</span>';
        }
        return $product_name;
    }

    public function displayTotalAmount($total, $row)
    {
        return Tools::displayPrice($total);
    }

    public function displayPaidAmount($paid, $row)
    {
        $subscription = new Subscription($row['id_subscription']);
        $total = $subscription->getTotalAmount();
        $percentage = $total > 0 ? ($paid / $total) * 100 : 0;
        
        return Tools::displayPrice($paid) . ' <span class="badge">' . number_format($percentage, 0) . '%</span>';
    }

    public function displayStatus($status)
    {
        if ($status == 'active') {
            return '<span class="badge badge-success">' . $this->l('Activa') . '</span>';
        } else {
            return '<span class="badge badge-warning">' . $this->l('Cancelada') . '</span>';
        }
    }

    public function renderView()
    {
        $id_subscription = (int)Tools::getValue('id_subscription');
        $subscription = new Subscription($id_subscription);

        if (!Validate::isLoadedObject($subscription)) {
            $this->errors[] = $this->l('Suscripción no encontrada');
            return parent::renderList();
        }

        $customer = new Customer($subscription->id_customer);
        $order = new Order($subscription->id_order);
        $payments = $subscription->getPayments();

        // Acciones sobre pagos
        if (Tools::isSubmit('markPaid')) {
            $id_payment = (int)Tools::getValue('id_payment');
            $payment = new SubscriptionPayment($id_payment);
            if ($payment->markAsPaid()) {
                $this->confirmations[] = $this->l('Pago marcado como pagado correctamente');
                Tools::redirectAdmin(self::$currentIndex . '&id_subscription=' . $id_subscription . '&viewpagosuscriekp_subscription&token=' . $this->token);
            }
        }

        if (Tools::isSubmit('markUnpaid')) {
            $id_payment = (int)Tools::getValue('id_payment');
            $payment = new SubscriptionPayment($id_payment);
            if ($payment->markAsUnpaid()) {
                $this->confirmations[] = $this->l('Pago desmarcado correctamente');
                Tools::redirectAdmin(self::$currentIndex . '&id_subscription=' . $id_subscription . '&viewpagosuscriekp_subscription&token=' . $this->token);
            }
        }

        if (Tools::isSubmit('markAllPaid')) {
            if ($subscription->markAsCompleted()) {
                $this->confirmations[] = $this->l('Todos los pagos han sido marcados como pagados');
                Tools::redirectAdmin(self::$currentIndex . '&id_subscription=' . $id_subscription . '&viewpagosuscriekp_subscription&token=' . $this->token);
            }
        }

        // Preparar datos para la vista
        $this->context->smarty->assign(array(
            'subscription' => $subscription,
            'customer' => $customer,
            'order' => $order,
            'payments' => $payments,
            'total_amount' => $subscription->getTotalAmount(),
            'paid_amount' => $subscription->getPaidAmount(),
            'pending_amount' => $subscription->getPendingAmount(),
            'is_fully_paid' => $subscription->isFullyPaid(),
            'current_index' => self::$currentIndex,
            'token' => $this->token,
            'link' => $this->context->link
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'pagosuscriekp/views/templates/admin/subscription_view.tpl');
    }

    public function processCancel()
    {
        $subscription = new Subscription((int)Tools::getValue('id_subscription'));
        
        if (!Validate::isLoadedObject($subscription)) {
            $this->errors[] = $this->l('Suscripción no encontrada');
        } else {
            if ($subscription->cancel()) {
                $this->confirmations[] = $this->l('Suscripción cancelada correctamente');
            } else {
                $this->errors[] = $this->l('Error al cancelar la suscripción');
            }
        }

        return parent::processBulkCancel();
    }

    public function processBulkCancel()
    {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            foreach ($this->boxes as $id_subscription) {
                $subscription = new Subscription((int)$id_subscription);
                if (Validate::isLoadedObject($subscription)) {
                    $subscription->cancel();
                }
            }
            $this->confirmations[] = $this->l('Suscripciones canceladas correctamente');
        }
    }

    public function processReactivate()
    {
        $subscription = new Subscription((int)Tools::getValue('id_subscription'));
        
        if (!Validate::isLoadedObject($subscription)) {
            $this->errors[] = $this->l('Suscripción no encontrada');
        } else {
            if ($subscription->reactivate()) {
                $this->confirmations[] = $this->l('Suscripción reactivada correctamente');
            } else {
                $this->errors[] = $this->l('Error al reactivar la suscripción');
            }
        }
    }

    public function initContent()
    {
        // Gestión de planes
        if (Tools::isSubmit('addplan') || Tools::isSubmit('editplan')) {
            return $this->renderPlanForm();
        }

        if (Tools::isSubmit('submitPlan')) {
            $this->processSavePlan();
        }

        if (Tools::isSubmit('deleteplan')) {
            $this->processDeletePlan();
        }

        parent::initContent();
    }

    private function renderPlanForm()
    {
        $id_plan = (int)Tools::getValue('id_plan');
        $editing = ($id_plan > 0);

        $plan_data = array();
        $installments = array();

        if ($editing) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` WHERE id_plan = ' . $id_plan;
            $plan_data = Db::getInstance()->getRow($sql);

            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` 
                    WHERE id_plan = ' . $id_plan . ' ORDER BY installment_number ASC';
            $installments = Db::getInstance()->executeS($sql);
        }

        // Obtener productos para el selector
        $products = Product::getProducts($this->context->language->id, 0, 0, 'name', 'ASC');

        $this->context->smarty->assign(array(
            'editing' => $editing,
            'id_plan' => $id_plan,
            'plan' => $plan_data,
            'installments' => $installments,
            'products' => $products,
            'current_index' => self::$currentIndex,
            'token' => $this->token
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'pagosuscriekp/views/templates/admin/plan_form.tpl');
    }

    private function processSavePlan()
    {
        $id_plan = (int)Tools::getValue('id_plan');
        $name = pSQL(Tools::getValue('plan_name'));
        $id_product = (int)Tools::getValue('id_product');
        $id_product_attribute = (int)Tools::getValue('id_product_attribute');
        $active = (int)Tools::getValue('active');

        $installments_amounts = Tools::getValue('installment_amount');
        $installments_days = Tools::getValue('installment_days');

        if (!$name) {
            $this->errors[] = $this->l('El nombre del plan es obligatorio');
            return;
        }

        if (empty($installments_amounts) || empty($installments_days)) {
            $this->errors[] = $this->l('Debe añadir al menos una cuota');
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

        $this->confirmations[] = $this->l('Plan guardado correctamente');
        Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token);
    }

    private function processDeletePlan()
    {
        $id_plan = (int)Tools::getValue('id_plan');

        // Eliminar cuotas
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` WHERE id_plan = ' . $id_plan);

        // Eliminar plan
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan` WHERE id_plan = ' . $id_plan);

        $this->confirmations[] = $this->l('Plan eliminado correctamente');
        Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token);
    }
}