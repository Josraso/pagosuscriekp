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

            // Calcular fecha de vencimiento ACUMULATIVA
            // Si la primera cuota es en 0 días, se paga inmediatamente
            // Si la segunda cuota es en 30 días, es 30 días DESPUÉS de la primera
            // Si la tercera cuota es en 30 días, es 30 días DESPUÉS de la segunda
            $due_date = date('Y-m-d', strtotime($previous_due_date . ' +' . $installment['days_after_purchase'] . ' days'));
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

        // Si está completamente pagado
        if ($this->isFullyPaid()) {
            // 1. Crear ps_order_payment con el total completo (solo si no existe ya)
            $existing_payment = Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_payment`
                WHERE order_reference = \'' . pSQL($order->reference) . '\'
            ');

            if (!$existing_payment) {
                $order_payment = new OrderPayment();
                $order_payment->order_reference = $order->reference;
                $order_payment->id_currency = $order->id_currency;
                $order_payment->amount = $this->getTotalAmount();
                $order_payment->payment_method = 'Pago por suscripción completado';
                $order_payment->conversion_rate = 1;
                $order_payment->date_add = date('Y-m-d H:i:s');
                $order_payment->add();

                // Actualizar total pagado del pedido
                $order->total_paid_real = $this->getTotalAmount();
                $order->update();
            }

            // 2. Cambiar estado a "Pago completado" (esto generará la factura)
            $id_completed_state = (int)Configuration::get('PAGOSUSCRIEKP_COMPLETED_STATE');

            // Si no existe el estado personalizado, usar el estado por defecto de PrestaShop
            if (!$id_completed_state) {
                $id_completed_state = (int)Configuration::get('PS_OS_PAYMENT');
            }

            // Solo cambiar si el estado actual no es ya "Pago completado"
            if ($order->getCurrentState() != $id_completed_state) {
                $order->setCurrentState($id_completed_state);
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
}