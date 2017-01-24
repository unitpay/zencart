<?php

class unitpay {
	//var $code, $title, $description, $enabled;

	// class constructor
	function unitpay() {

		$this->code = 'unitpay';
		$this->title = MODULE_PAYMENT_UNITPAY_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_UNITPAY_TEXT_DESCRIPTION;
		$this->sort_order = MODULE_PAYMENT_UNITPAY_SORT_ORDER;
		$this->enabled =	((MODULE_PAYMENT_UNITPAY_ENABLE == 'True') ? true : false);
	}

	// class methods
	function update_status() {
		return false;
	}

	function javascript_validation() {
		return false;
	}

	function selection() {
		return array('id' => $this->code, 'module' => $this->title);
	}

	function pre_confirmation_check() {
		return false;
	}

	function confirmation() {
		return false;
	}

	function process_button() {
		return false;
	}

	function before_process() {
		return false;
	}

	function after_process() {

		global $insert_id, $cart, $order, $currencies;
		$public_key = MODULE_PAYMENT_UNITPAY_PUBLIC_KEY;
		$currency_value = $order->info['currency_value'];
		$rate = (zen_not_null($currency_value)) ? $currency_value : 1;

		$sum = zen_round($order->info['total'] * $rate, $currencies->get_decimal_places());
		$account = $insert_id;
		$desc = 'Заказ №' . $insert_id;
		$currency = $order->info['currency'];
		$payment_url = 'https://unitpay.ru/pay/' . $public_key . '?' . 'sum=' . $sum . '&account=' . $account . '&desc=' . $desc . '&currency=' . $currency;
		$_SESSION['cart']->reset(true);

		unset($_SESSION['sendto']);
		unset($_SESSION['billto']);
		unset($_SESSION['shipping']);
		unset($_SESSION['payment']);
		unset($_SESSION['comments']);

		zen_redirect($payment_url);

	}

	function output_error() {
		return false;
	}

	function check() {
		global $db;

		if (!isset($this->_check)) {
			$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_UNITPAY_ENABLE'");
			$this->_check = $check_query->RecordCount();
		}
		return $this->_check;
	}

	function install() {

		global $db;

		$langDir = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'];
		include_once($langDir."/modules/payment/unitpay.php");

		$pay_status_id = $this->createOrderStatus("Paid[Unitpay]");
		$error_status_id = $this->createOrderStatus("Error[Unitpay]");
		//config params

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values (
		'" . MODULE_PAYMENT_UNITPAY_ENABLE_TITLE . "', 
		'MODULE_PAYMENT_UNITPAY_ENABLE', 
		'True', 
		'', 
		'6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_PUBLIC_KEY_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_PUBLIC_KEY', 
            '', 
            '', 
            '6', '0', now())"
		);
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_SECRET_KEY_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_SECRET_KEY', 
            '', 
            '', 
            '6', '0', now())"
		);
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_SORT_ORDER_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_SORT_ORDER', 
            '0', 
            '', 
            '6', '0', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID', 
            '".$pay_status_id."',  
            '', 
            '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID', 
            '".$error_status_id."',  
            '', 
            '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

	}

	function remove()
	{
		global $db;
		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function keys()
	{
		return array(
			'MODULE_PAYMENT_UNITPAY_ENABLE',
			'MODULE_PAYMENT_UNITPAY_PUBLIC_KEY',
			'MODULE_PAYMENT_UNITPAY_SECRET_KEY',
			'MODULE_PAYMENT_UNITPAY_SORT_ORDER',
			'MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID',
			'MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID',
		);
	}

	function createOrderStatus( $title ){
		global $db;

		$q = $db->Execute("select orders_status_id from ".TABLE_ORDERS_STATUS." where orders_status_name = '".$title."' limit 1");
		if ($q->RecordCount() < 1) {
			$q = $db->Execute("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
			$row = $q->current();
			$status_id = $row['status_id'] + 1;
			$languages = zen_get_languages();

			foreach ($languages as $lang) {
				$db->Execute("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', " . "'" . $title . "')");
			}
		}else{
			$status = $q->current();
			$status_id = $status['orders_status_id'];
		}
		return $status_id;
	}
}
?>