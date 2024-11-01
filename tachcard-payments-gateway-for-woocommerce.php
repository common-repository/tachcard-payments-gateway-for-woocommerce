<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: Tachcard payments gateway for Woocommerce
 * Plugin URI: https://wordpress.org/plugins/tachcard-payments-gateway-for-woocommerce/
 * Description: This plugin adds to e-shop with Woocommerce the Tachcard module to accept payments. Easy set-up. Easy usage.
 * Version: 1.1
 * Author: Tachcard 
 * Author URI: https://tachcard.com
 */

/** Copyright 2018 Tachcard (email: info@tachcard.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
 
/**
 * Notices for permalink
 */
add_action( 'admin_notices', 'action_function_name_11' );
function action_function_name_11(){
	if ( !get_option('permalink_structure') ) {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		else{
		?>
		<div class="notice notice-error is-dismissible">
			<p>Внимание! Тип Ваших постоянных ссылок не подходит для плагина "Tachcard payments gateway for Woocommerce". Для корректной работы плагина нужно выбрать любой тип, кроме первого.
				<a href="<?php echo get_site_url().'/wp-admin/options-permalink.php'; ?>">Изменить</a>
			</p>
		</div>
		<?php
		}
	}
}

/**
 * Add the gateway to WooCommerce.
 */
add_filter( 'woocommerce_payment_gateways', 'wc_tachcard_add_gateway' );

function wc_tachcard_add_gateway( $methods ) {
    if (!in_array('WC_Gateway_Tachcard', $methods)) {
            $methods[] = 'WC_Gateway_Tachcard';
        }
        return $methods;
}

/**
 * Tachcard Gateway
 * @class       WC_Tachcard
 * @extends     WC_Gateway_Tachcard
 */
add_action( 'plugins_loaded', 'tachcard_gateway_load', 0 );
function tachcard_gateway_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_Tachcard extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			global $woocommerce;

			//standard
			$this->id           = 'wc_tachcard_gateway';
			$this->has_fields   = false;
			$this->method_title = __( 'Tachcard', 'woocommerce' );
			$this->ipn_url   = add_query_arg( 'wc-api', 'WC_Gateway_Tachcard', home_url( '/' ) );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title            = $this->get_option( 'title' );
			$this->description      = $this->get_option( 'description' );
			$this->base_url          = $this->get_option( 'base_url' );
			$this->secret_key   = $this->get_option( 'secret_key' );
			$this->wc_lang    = $this->get_option( 'wc_lang' );
			$this->callback_url = $this->get_option('callback_url');
			$this->status = $this->get_option('status');
			$this->icon = $this->get_option('icon');

			// Logs
			$this->log = new WC_Logger();

			add_action('woocommerce_receipt_tachcard', array( $this, 'receipt_page' ) );
			// Actions
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_receipt_tachcard', array($this, 'add_my_script'));

			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_tachcard', array( $this, 'check_ipn_response' ) );

			if ( !$this->is_valid_for_use() ) $this->enabled = false;

		}
		
		public function add_my_script() {
			wp_register_style( 'tachcard-style', plugins_url('/inner_assets/css/tachcard.css', __FILE__) );
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 *
		 * @access public
		 * @return bool
		 */
		function is_valid_for_use() {
			 if (!in_array(get_option('woocommerce_currency'), array('UAH'))) {
					return false;
				}
				return true;
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {

			?>
			<h3><?php _e( 'Провайдер платежей и переводов Tachcard', 'woocommerce' ); ?></h3>

			<?php if ( $this->is_valid_for_use() ) : ?>

				<table class="form-table">
				<?php
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
				?>
				</table><!--/.form-table-->

			<?php else : ?>
			   <div class="inline error">
						<p>
							<strong><?php _e('Плагин отключен', 'woocommerce'); ?></strong>: <?php _e('Tachcard не поддерживает валюты Вашего магазина.', 'woocommerce'); ?>
						</p>
					</div>
			<?php
				endif;
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Включить/Выключить', 'woocommerce' ),
								'type' => 'checkbox',
								'label' => __( 'Включить Tachcard', 'woocommerce' ),
								'default' => 'yes'
							),
				'title' => array(
								'title' => __( 'Заголовок', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Заголовок, который отображается на странице оформления заказа.', 'woocommerce' ),
								'default' => __( 'Оплата картой Visa/Mastercard (Tachcard)', 'woocommerce' ),
								'desc_tip'      => true,
							),
				'description' => array(
								'title' => __( 'Описание', 'woocommerce' ),
								'type' => 'textarea',
								'description' => __( 'Описание, которое отображается на странице оформления заказа.', 'woocommerce' ),
								'default' => __( 'Оплатить с помощью провайдера платежей Tachcard', 'woocommerce' ),
								'desc_tip'      => true,
							),
				'base_url' => array(
								'title' => __( 'Базовая ссылка', 'woocommerce' ),
								'type'          => 'text',
								'placeholder' => 'https://user.tachcard.com/requisites/your-shop-name',
								'description' => __( 'Базовая ссылка вашего магазина в системе Tachcard. Получите от менеджера Tachcard.', 'woocommerce' ),
								'desc_tip'      => true,
							),
				'secret_key' => array(
								'title' => __( 'Секретный ключ', 'woocommerce' ),
								'type'          => 'text',
								'placeholder' => '71c9ad8275b08eab851933cacb8d686d',
								'description' => __( 'Уникальный секретный ключ вашего магазина в системе Tachcard. Получите от менеджера Tachcard.', 'woocommerce' ),
								'desc_tip'      => true,
							),
				'wc_lang' => array(
								'title'       => __('Язык', 'woocommerce'),
								'type'        => 'select',
								'default'     => 'auto',
								'options'     => array('auto'=> __('Auto', 'woocommerce'), 'ru'=> __('RU', 'woocommerce'), 'uk'=> __('UA', 'woocommerce'), 'en'=> __('EN', 'woocommerce')),
								'description' => __('Это выпадающий список значений , обозначающих язык интерфейса платежных страниц.', 'woocommerce'),
								'desc_tip'    => true,
							),
				'status'     => array(
								'title'       => __('Статус заказа', 'woocommerce'),
								'type'        => 'select',
								'default'     => 'processing',
								'options'     => array('processing'=> __('Обработка', 'woocommerce'), 'completed'=> __('Выполнен', 'woocommerce')), 
								'description' => __('Статус заказа после успешной оплаты', 'woocommerce'),
								'desc_tip'    => true, 
                ),
				'callback_url'     => array(
								'title'       => __('CallBack URL', 'woocommerce'),
								'type'        => 'text',
								'custom_attributes' => array('readonly' => 'readonly'),
								'default'     => ''.get_site_url( null, '/wc-api/wc_tachcard/').'',
								'description' => __('Это ссылка для получения вашей системой статуса после оплаты заказа. Передайте менеджеру Tachcard.', 'woocommerce'),
								'desc_tip'    => true,
							),
				'icon'     => array(
								'title'       => __('Лого', 'woocommerce'),
								'type'        => 'text',
								'default'     => ''.apply_filters( 'tachcard_coinpayments_icon', plugins_url('/inner_assets/images/icons/tachcard-icon.svg', __FILE__) ),
								'description' => __('Это лого Tachcard, которое отображается на странице заказа. Файл должен лежать на хостинге. Файлообменники не подходят.', 'woocommerce'),
								'desc_tip'    => true,
							),
			);

		}
		
		/**
		 * Get sign
		 *
		 * @access public
		 * @param mixed $secret_key, $args
		 * @return array
		 */
		function make_url_sign($secret_key, $args) {
			ksort($args);
			$array = substr( md5(join (';',$args) . ';' . $secret_key), 0, 8);
			return $array;
		}

		
		/**
		 * Get Tachcard Args
		 *
		 * @access public
		 * @param mixed $order
		 * @return array
		 */
		function get_tachcard_args( $order ) {
			global $woocommerce;
			
			$secret_key = $this->get_option( 'secret_key' );
			$amount = number_format( $order->get_total(), 0, '.', '' );
			$order_number = $order->get_order_number();
			
			$sign_args = array(
					'a' => $amount,
					'o' => $order_number,
			);

			$tachcard_args = array(
					'a' => $amount,
					'o' => $order_number,
					's' => $this->make_url_sign($secret_key, $sign_args),
			);

			$tachcard_args = apply_filters( 'woocommerce_tachcard_args', $tachcard_args );

			return $tachcard_args;
		}


		/**
		 * Generate the tachcard button link
		 *
		 * @access public
		 * @param mixed $order_id
		 * @return string
		 */
		function generate_tachcard_url($order) {
			global $woocommerce;

			if ( $order->status != 'completed') {
				$order->update_status('pending');
			}

			$tachcard_address = $this->get_option('base_url').'?';
			$language = $this->get_option('wc_lang');
			if($language != "auto"){
				$tachcard_address = str_replace("https://user.tachcard.com", "", $tachcard_address);
				switch($language){
					case "ru":
					$tachcard_address = "https://user.tachcard.com/ru".$tachcard_address;
					break;
					case "uk":
					$tachcard_address = "https://user.tachcard.com/uk".$tachcard_address;
					break;
					case "en":
					$tachcard_address = "https://user.tachcard.com/en".$tachcard_address;
					break;
				}
			}
			$tachcard_args = $this->get_tachcard_args( $order );
			$tachcard_address .= http_build_query( $tachcard_args, '', '&' );
			return $tachcard_address;
		}
		
		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 * @param int $order_id
		 * @return array
		 */
		function process_payment( $order_id ) {

			$order          = wc_get_order( $order_id );
			
			WC()->cart->empty_cart();

			return array(
					'result' 	=> 'success',
					'redirect'	=> $this->generate_tachcard_url($order),
			);

		}
		
		
		/**
		 * Output for the order received page.
		 *
		 * @access public
		 * @return void
		 */
		function receipt_page( $order ) {
			echo '<p>'.__( 'Спасибо за ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы оплатить с помощью Tachcard.', 'woocommerce' ).'</p>';

			echo $this->generate_form( $order );
		}
		
		/**
		 * Check for Tachcard IPN Response
		 *
		 * @access public
		 * @return void
		 */
		function check_ipn_response() {
			global $woocommerce,$wpdb;

			$result_ipn = array();
			$data = json_decode( file_get_contents( 'php://input' ));
			if ( $data->id != 0 ) {
				$this->log->add( 'tachcard', 'Order ID: '. $data->order_id .'; Send date: '. $data->send_date .'; Created at: '. $data->created_at .'; Amount: '. $data->amount.'; Sign: '. $data->sign );
				
				$sign_args = array(
					'order_id' => $data->order_id,
					'send_date' => $data->send_date,
					'created_at' => $data->created_at,
					'amount' => $data->amount,
				);
				
				$order_id = $data->order_id;
				$ipn_sign = $data->sign;
				$secret_key = $this->get_option( 'secret_key' );
				
				$cur_sign = $this->make_url_sign($secret_key, $sign_args);
				
				$order = new WC_Order( $order_id );
				if($ipn_sign == $cur_sign){
					$result_ipn['status'] = true;
					$order->update_status($this->status, __('Заказ оплачен (оплата получена)', 'woocommerce'));
					$order->add_order_note(__('Клиент оплатил свой заказ', 'woocommerce'));
				}
				else {
					 // Set order status to payment failed
					$result_ipn['status'] = false;
					$order->update_status('failed', __('Оплата не была получена', 'woocommerce'));
				}
			} 
			else $result_ipn['status'] = false;
			
			$jsonResponse = json_encode($result_ipn);
			if (!$jsonResponse) {
				$this->log->add( 'tachcard-error', "couldn't encode" );
				die('JSON ENCODE ERROR');
			}
			
			header('Content-Type: application/json');
			echo $jsonResponse;
			die();

		}

		
    }


class WC_Tachcard extends WC_Gateway_Tachcard {
    public function __construct() {
        _deprecated_function( 'WC_Tachcard', '1.0', 'WC_Gateway_Tachcard' );
        parent::__construct();
    }
}

}

register_uninstall_hook( __FILE__, 'tachcard_uninstall' );

function tachcard_uninstall(){
        delete_option('woocommerce_wc_tachcard_gateway_settings');
}

?>