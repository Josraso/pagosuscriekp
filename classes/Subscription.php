<?php
/**
 * Clase Subscription
 * Gestiona las suscripciones
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Subscription extends ObjectModel
{
    public $id_subscription;
    public $id_order;
    public $id_customer;
    public $id_product;
    public $id_product_attribute;
    public $status;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'pagosuscriekp_subscription',
        'primary' => 'id_subscription',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_customer' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false),
            'id_product_attribute' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Obtener suscripción por ID de pedido
     */
    public static function getByOrderId($id_order)
    {
        $sql = 'SELECT id_subscription 
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` 
                WHERE id_order = ' . (int)$id_order;
        
        $id_subscription = Db::getInstance()->getValue($sql);
        
        if ($id_subscription) {
            return new Subscription($id_subscription);
        }
        
        return false;
    }

    /**
     * Obtener todas las suscripciones
     */
    public static function getAll($status = null)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_subscription`';
        
        if ($status) {
            $sql .= ' WHERE status = "' . pSQL($status) . '"';
        }
        
        $sql .= ' ORDER BY date_add DESC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener suscripciones por cliente
     */
    public static function getByCustomer($id_customer)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` 
                WHERE id_customer = ' . (int)$id_customer . '
                ORDER BY date_add DESC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener pagos de la suscripción
     */
    public function getPayments()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id . '
                ORDER BY due_date ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Crear pagos basados en un plan
     */
    public function createPaymentsFromPlan($id_plan, $order_date)
    {
        // Obtener las cuotas del plan
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_plan_installment` 
                WHERE id_plan = ' . (int)$id_plan . '
                ORDER BY installment_number ASC';
        
        $installments = Db::getInstance()->executeS($sql);
        
        if (!$installments) {
            return false;
        }

        // Para calcular fechas de forma acumulativa
        $previous_due_date = $order_date;

        foreach ($installments as $installment) {
            $payment = new SubscriptionPayment();
            $payment->id_subscription = $this->id;
            $payment->installment_number = $installment['installment_number'];
            $payment->amount = $installment['amount'];

            // Calcular fecha de vencimiento según el tipo
            $date_type = isset($installment['date_type']) ? $installment['date_type'] : 'days';

            if ($date_type == 'fixed' && $installment['fixed_date_day']) {
                // Día fijo del mes: calcular siguiente ocurrencia del día especificado
                $fixed_day = (int)$installment['fixed_date_day'];
                $current_date = strtotime($previous_due_date);
                $current_day = (int)date('d', $current_date);

                // Si el día fijo ya pasó en el mes actual, ir al mes siguiente
                if ($current_day >= $fixed_day) {
                    // Próximo mes
                    $next_month = date('Y-m-01', strtotime($previous_due_date . ' +1 month'));
                    $due_date = date('Y-m-' . str_pad($fixed_day, 2, '0', STR_PAD_LEFT), strtotime($next_month));
                } else {
                    // Mismo mes
                    $due_date = date('Y-m-' . str_pad($fixed_day, 2, '0', STR_PAD_LEFT), $current_date);
                }
            } else {
                // Días después: calcular acumulativamente
                // La primera cuota es inmediata (0 días)
                // Las siguientes son X días después de la cuota anterior
                $days_after = (int)$installment['days_after_purchase'];
                $due_date = date('Y-m-d', strtotime($previous_due_date . ' +' . $days_after . ' days'));
            }

            $payment->due_date = $due_date;
            $payment->paid = 0;

            // Actualizar la fecha anterior para la próxima iteración
            $previous_due_date = $due_date;

            if (!$payment->add()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Cancelar suscripción
     */
    public function cancel()
    {
        $this->status = 'cancelled';
        return $this->update();
    }

    /**
     * Reactivar suscripción
     */
    public function reactivate()
    {
        $this->status = 'active';
        return $this->update();
    }

    /**
     * Verificar si todos los pagos están completados
     */
    public function isFullyPaid()
    {
        $sql = 'SELECT COUNT(*) as total, 
                SUM(CASE WHEN paid = 1 THEN 1 ELSE 0 END) as paid_count
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id;
        
        $result = Db::getInstance()->getRow($sql);
        
        return ($result['total'] > 0 && $result['total'] == $result['paid_count']);
    }

    /**
     * Obtener total de la suscripción
     */
    public function getTotalAmount()
    {
        $sql = 'SELECT SUM(amount) FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id;
        
        return (float)Db::getInstance()->getValue($sql);
    }

    /**
     * Obtener total pagado
     */
    public function getPaidAmount()
    {
        $sql = 'SELECT SUM(amount) FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id . '
                AND paid = 1';
        
        return (float)Db::getInstance()->getValue($sql);
    }

    /**
     * Obtener total pendiente
     */
    public function getPendingAmount()
    {
        return $this->getTotalAmount() - $this->getPaidAmount();
    }

    /**
     * Marcar como completamente pagada
     */
    public function markAsCompleted()
    {
        // Marcar todos los pagos como pagados
        $payments = $this->getPayments();
        $now = date('Y-m-d H:i:s');
        
        foreach ($payments as $payment_data) {
            if (!$payment_data['paid']) {
                $payment = new SubscriptionPayment($payment_data['id_payment']);
                $payment->paid = 1;
                $payment->date_paid = $now;
                $payment->update();
            }
        }

        // Actualizar el estado del pedido
        $this->updateOrderPaymentStatus();
        
        return true;
    }

    /**
     * Actualizar estado de pago del pedido
     */
    public function updateOrderPaymentStatus()
    {
        $order = new Order($this->id_order);

        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        // Si está completamente pagado, solo cambiamos el estado
        // PrestaShop se encargará automáticamente de crear el pago y la factura
        if ($this->isFullyPaid()) {
            $id_completed_state = (int)Configuration::get('PAGOSUSCRIEKP_COMPLETED_STATE');

            // Si no existe el estado personalizado, usar el estado por defecto de PrestaShop
            if (!$id_completed_state) {
                $id_completed_state = (int)Configuration::get('PS_OS_PAYMENT');
            }

            // Solo cambiar si el estado actual no es ya "Pago completado"
            if ($order->getCurrentState() != $id_completed_state) {
                // PrestaShop creará automáticamente el ps_order_payment al cambiar el estado
                $order->setCurrentState($id_completed_state);

                // Enviar email de suscripción completada
                $this->sendCompletionEmail();
            }
        }

        return true;
    }

    /**
     * Obtener próximo pago pendiente
     */
    public function getNextPendingPayment()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id . '
                AND paid = 0
                ORDER BY due_date ASC
                LIMIT 1';
        
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Obtener pagos vencidos
     */
    public function getOverduePayments()
    {
        $today = date('Y-m-d');
        
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id . '
                AND paid = 0
                AND due_date < "' . pSQL($today) . '"
                ORDER BY due_date ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Contar pagos pendientes
     */
    public function getPendingPaymentsCount()
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$this->id . '
                AND paid = 0';
        
        return (int)Db::getInstance()->getValue($sql);
    }

    /**
     * Contar pagos completados
     */
    public function getPaidPaymentsCount()
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment`
                WHERE id_subscription = ' . (int)$this->id . '
                AND paid = 1';

        return (int)Db::getInstance()->getValue($sql);
    }

    /**
     * Enviar email de suscripción completada
     */
    private function sendCompletionEmail()
    {
        $customer = new Customer($this->id_customer);
        $order = new Order($this->id_order);

        if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($order)) {
            return false;
        }

        // Obtener datos de la suscripción
        $payments = $this->getPayments();
        $total_count = count($payments);
        $total_amount = $this->getTotalAmount();

        // Preparar variables para el correo
        $templateVars = array(
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{order_reference}' => $order->reference,
            '{total_count}' => $total_count,
            '{total_amount}' => Tools::displayPrice($total_amount),
            '{subscription_start_date}' => date('d/m/Y', strtotime($this->date_add)),
            '{completion_date}' => date('d/m/Y H:i'),
        );

        // Enviar correo
        $result = Mail::Send(
            (int)$order->id_lang,
            'subscription_completed',
            'Suscripción completada - Todos los pagos realizados',
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/../mails/',
            false,
            (int)$order->id_shop
        );

        return $result;
    }
}