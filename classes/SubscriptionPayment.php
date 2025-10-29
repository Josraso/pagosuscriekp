<?php
/**
 * Clase SubscriptionPayment
 * Gestiona los pagos individuales de una suscripción
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SubscriptionPayment extends ObjectModel
{
    public $id_payment;
    public $id_subscription;
    public $installment_number;
    public $amount;
    public $due_date;
    public $paid;
    public $date_paid;
    public $id_order_payment;
    public $date_add;

    public static $definition = array(
        'table' => 'pagosuscriekp_payment',
        'primary' => 'id_payment',
        'fields' => array(
            'id_subscription' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'installment_number' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'due_date' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'paid' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_paid' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => false),
            'id_order_payment' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => false),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Marcar el pago como pagado
     */
    public function markAsPaid($create_order_payment = true)
    {
        $this->paid = 1;
        $this->date_paid = date('Y-m-d H:i:s');

        if ($create_order_payment) {
            $this->createOrderPayment();
        }

        if ($this->update()) {
            // Verificar si la suscripción está completamente pagada
            $subscription = new Subscription($this->id_subscription);
            $subscription->updateOrderPaymentStatus();
            
            return true;
        }

        return false;
    }

    /**
     * Crear un pago en el pedido de PrestaShop
     */
    private function createOrderPayment()
    {
        $subscription = new Subscription($this->id_subscription);
        $order = new Order($subscription->id_order);

        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        // Crear OrderPayment
        $order_payment = new OrderPayment();
        $order_payment->order_reference = $order->reference;
        $order_payment->id_currency = $order->id_currency;
        $order_payment->amount = $this->amount;
        $order_payment->payment_method = 'Pago por suscripción';
        $order_payment->conversion_rate = 1;
        $order_payment->date_add = $this->date_paid;

        if ($order_payment->add()) {
            $this->id_order_payment = $order_payment->id;
            
            // Actualizar el total pagado del pedido
            $order->total_paid_real += $this->amount;
            $order->update();

            return true;
        }

        return false;
    }

    /**
     * Desmarcar como pagado
     */
    public function markAsUnpaid()
    {
        // Si existe un pago en el pedido, eliminarlo
        if ($this->id_order_payment) {
            $order_payment = new OrderPayment($this->id_order_payment);
            if (Validate::isLoadedObject($order_payment)) {
                $subscription = new Subscription($this->id_subscription);
                $order = new Order($subscription->id_order);
                
                // Restar del total pagado
                $order->total_paid_real -= $this->amount;
                $order->update();
                
                $order_payment->delete();
            }
        }

        $this->paid = 0;
        $this->date_paid = null;
        $this->id_order_payment = null;

        return $this->update();
    }

    /**
     * Verificar si el pago está vencido
     */
    public function isOverdue()
    {
        if ($this->paid) {
            return false;
        }

        $today = date('Y-m-d');
        return ($this->due_date < $today);
    }

    /**
     * Obtener días hasta el vencimiento
     */
    public function getDaysUntilDue()
    {
        $today = strtotime(date('Y-m-d'));
        $due = strtotime($this->due_date);
        
        $diff = $due - $today;
        return floor($diff / (60 * 60 * 24));
    }

    /**
     * Verificar si debe enviarse recordatorio
     */
    public function shouldSendReminder($reminder_days)
    {
        if ($this->paid) {
            return false;
        }

        $days_until = $this->getDaysUntilDue();
        
        return ($days_until == $reminder_days);
    }

    /**
     * Obtener pagos pendientes de una suscripción
     */
    public static function getPendingBySubscription($id_subscription)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` 
                WHERE id_subscription = ' . (int)$id_subscription . '
                AND paid = 0
                ORDER BY due_date ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener todos los pagos vencidos del sistema
     */
    public static function getAllOverdue()
    {
        $today = date('Y-m-d');
        
        $sql = 'SELECT p.*, s.id_customer, s.id_order, s.status as subscription_status
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` p
                INNER JOIN `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` s ON (p.id_subscription = s.id_subscription)
                WHERE p.paid = 0
                AND p.due_date < "' . pSQL($today) . '"
                AND s.status = "active"
                ORDER BY p.due_date ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener pagos que requieren recordatorio hoy
     */
    public static function getPaymentsNeedingReminder($reminder_days)
    {
        $target_date = date('Y-m-d', strtotime('+' . (int)$reminder_days . ' days'));
        
        $sql = 'SELECT p.*, s.id_customer, s.id_order
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment` p
                INNER JOIN `' . _DB_PREFIX_ . 'pagosuscriekp_subscription` s ON (p.id_subscription = s.id_subscription)
                WHERE p.paid = 0
                AND p.due_date = "' . pSQL($target_date) . '"
                AND s.status = "active"';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Obtener estadísticas de pagos
     */
    public static function getStats()
    {
        $sql = 'SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN paid = 1 THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN paid = 0 THEN 1 ELSE 0 END) as pending_count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN paid = 1 THEN amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN paid = 0 THEN amount ELSE 0 END) as pending_amount
                FROM `' . _DB_PREFIX_ . 'pagosuscriekp_payment`';
        
        return Db::getInstance()->getRow($sql);
    }
}