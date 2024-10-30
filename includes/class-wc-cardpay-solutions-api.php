<?php
/**
 * Class WC_Cardpay_Solutions_API file.
 *
 * @package High Risk Payment Gateway for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Cardpay_Solutions_API
 */
class WC_Cardpay_Solutions_API {

	/**
	 * Stores the gateway username.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * Stores the gateway password.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Determines if the WC version is less than 3.0.0.
	 *
	 * @var bool
	 */
	public $wc_pre_30;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * Authorize function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 * @param array                        $card Credit card array.
	 *
	 * @return mixed
	 */
	public function authorize( $gateway, $order, $amount, $card ) {
		$payload  = $this->get_payload( $gateway, $order, $amount, 'auth', $card );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Purchase function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 * @param array                        $card Credit card array.
	 *
	 * @return mixed
	 */
	public function purchase( $gateway, $order, $amount, $card ) {
		$payload  = $this->get_payload( $gateway, $order, $amount, 'sale', $card );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Capture function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 *
	 * @return mixed
	 */
	public function capture( $gateway, $order, $amount ) {
		$payload  = $this->get_payload( $gateway, $order, $amount, 'capture' );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Refund function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 *
	 * @return mixed
	 */
	public function refund( $gateway, $order, $amount ) {
		$payload  = $this->get_payload( $gateway, $order, $amount, 'refund' );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Void function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 *
	 * @return mixed
	 */
	public function void( $gateway, $order, $amount ) {
		$payload  = $this->get_payload( $gateway, $order, $amount, 'void' );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Verify function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 *
	 * @return mixed
	 */
	public function verify( $gateway ) {
		$payload  = $this->get_token_payload( $gateway );
		$response = $this->post_transaction( $payload );
		return $response;
	}

	/**
	 * Get_payload function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 * @param WC_Order                     $order Order object.
	 * @param float                        $amount Order amount.
	 * @param string                       $transaction_type Transaction type.
	 * @param array                        $card Credit card array.
	 *
	 * @return string
	 */
	public function get_payload( $gateway, $order, $amount, $transaction_type, $card = '' ) {
		$order_number       = $this->wc_pre_30 ? $order->id : $order->get_id();
		$billing_first_name = $this->wc_pre_30 ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = $this->wc_pre_30 ? $order->billing_last_name : $order->get_billing_last_name();
		$billing_address    = $this->wc_pre_30 ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_postcode   = $this->wc_pre_30 ? $order->billing_postcode : $order->get_billing_postcode();
		$tax_amount         = $this->wc_pre_30 ? $order->order_tax : $order->get_total_tax();
		$shipping_amount    = $this->wc_pre_30 ? $order->get_total_shipping() : $order->get_shipping_total();
		$cardholder_name    = $billing_first_name . ' ' . $billing_last_name;

		if ( 'yes' === $gateway->sandbox ) {
			$this->username = 'demo';
			$this->password = 'password';
		} else {
			$this->username = $gateway->username;
			$this->password = $gateway->password;
		}

		if ( 'auth' === $transaction_type || 'sale' === $transaction_type ) {
			if ( ! empty( $card ) ) {
				$data = array(
					'username'          => wc_clean( $this->username ),
					'password'          => wc_clean( $this->password ),
					'type'              => wc_clean( $transaction_type ),
					'customer_vault_id' => wc_clean( $card->get_token() ),
					'amount'            => number_format( $amount, 2, '.', '' ),
					'currency'          => wc_clean( strtoupper( get_woocommerce_currency() ) ),
					'orderid'           => wc_clean( $order_number ),
					'firstname'         => wc_clean( $billing_first_name ),
					'lastname'          => wc_clean( $billing_last_name ),
					'address1'          => wc_clean( substr( $billing_address, 0, 30 ) ),
					'zip'               => wc_clean( substr( $billing_postcode, 0, 10 ) ),
					'tax'               => number_format( $tax_amount, '2', '.', '' ),
					'shipping'          => number_format( $shipping_amount, '2', '.', '' ),
					'ponumber'          => wc_clean( $order_number ),
				);
			} else {
				$card_raw       = isset( $_POST['cardpay-card-number'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-number'] ) ) : '';
				$card_number    = str_replace( ' ', '', $card_raw );
				$exp_raw        = isset( $_POST['cardpay-card-expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-expiry'] ) ) : '';
				$exp_date_array = explode( '/', $exp_raw );
				$exp_month      = trim( $exp_date_array[0] );
				$exp_year       = trim( $exp_date_array[1] );
				$exp_date       = $exp_month . substr( $exp_year, -2 );
				$cvc            = isset( $_POST['cardpay-card-cvc'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-cvc'] ) ) : '';
				$data           = array(
					'username'  => wc_clean( $this->username ),
					'password'  => wc_clean( $this->password ),
					'type'      => wc_clean( $transaction_type ),
					'ccnumber'  => wc_clean( $card_number ),
					'ccexp'     => wc_clean( $exp_date ),
					'amount'    => number_format( $amount, 2, '.', '' ),
					'currency'  => wc_clean( strtoupper( get_woocommerce_currency() ) ),
					'cvv'       => wc_clean( $cvc ),
					'orderid'   => wc_clean( $order_number ),
					'firstname' => wc_clean( $billing_first_name ),
					'lastname'  => wc_clean( $billing_last_name ),
					'address1'  => wc_clean( substr( $billing_address, 0, 30 ) ),
					'zip'       => wc_clean( substr( $billing_postcode, 0, 10 ) ),
					'tax'       => number_format( $tax_amount, '2', '.', '' ),
					'shipping'  => number_format( $shipping_amount, '2', '.', '' ),
					'ponumber'  => wc_clean( $order_number ),
				);
				if ( isset( $_POST['wc-cardpay-new-payment-method'] ) ) {
					$data['customer_vault'] = 'add_customer';
				}
			}
		} else {
			$tran_meta = $order->get_meta( '_cardpay_transaction', true );
			$data      = array(
				'username'      => wc_clean( $this->username ),
				'password'      => wc_clean( $this->password ),
				'transactionid' => wc_clean( $tran_meta['transaction_id'] ),
				'amount'        => number_format( $amount, 2, '.', '' ),
				'currency'      => wc_clean( strtoupper( get_woocommerce_currency() ) ),
				'type'          => wc_clean( $transaction_type ),
			);
		}
		$query = '';
		foreach ( $data as $key => $value ) {
			$query .= $key . '=' . rawurlencode( $value ) . '&';
		}
		$query = trim( $query, '&' );
		return $query;
	}

	/**
	 * Get_token_payload function
	 *
	 * @param WC_Cardpay_Solutions_Gateway $gateway Gateway object.
	 *
	 * @return string
	 */
	public function get_token_payload( $gateway ) {
		if ( 'yes' === $gateway->sandbox ) {
			$this->username = 'demo';
			$this->password = 'password';
		} else {
			$this->username = $gateway->username;
			$this->password = $gateway->password;
		}
		$customer_id    = get_current_user_id();
		$card_raw       = isset( $_POST['cardpay-card-number'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-number'] ) ) : '';
		$card_number    = str_replace( ' ', '', $card_raw );
		$exp_raw        = isset( $_POST['cardpay-card-expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-expiry'] ) ) : '';
		$exp_date_array = explode( '/', $exp_raw );
		$exp_month      = trim( $exp_date_array[0] );
		$exp_year       = trim( $exp_date_array[1] );
		$exp_date       = $exp_month . substr( $exp_year, -2 );
		$cvc            = isset( $_POST['cardpay-card-cvc'] ) ? sanitize_text_field( wp_unslash( $_POST['cardpay-card-cvc'] ) ) : '';
		$data           = array(
			'username'       => wc_clean( $this->username ),
			'password'       => wc_clean( $this->password ),
			'type'           => 'validate',
			'ccnumber'       => wc_clean( $card_number ),
			'ccexp'          => wc_clean( $exp_date ),
			'cvv'            => wc_clean( $cvc ),
			'firstname'      => wc_clean( get_user_meta( $customer_id, 'billing_first_name', true ) ),
			'lastname'       => wc_clean( get_user_meta( $customer_id, 'billing_last_name', true ) ),
			'amount'         => '0.00',
			'customer_vault' => 'add_customer',
		);
		$query          = '';
		foreach ( $data as $key => $value ) {
			$query .= $key . '=' . rawurlencode( $value ) . '&';
		}
		$query = trim( $query, '&' );
		return $query;
	}

	/**
	 * Post_transaction function
	 *
	 * @param string $payload Payload json.
	 *
	 * @return string|WP_Error
	 */
	public function post_transaction( $payload ) {
		$url      = 'https://cardpaysolutions.transactiongateway.com/api/transact.php';
		$args     = array(
			'body'    => $payload,
			'method'  => 'POST',
			'timeout' => 70,
		);
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			return new WP_Error( 'cardpay_error', __( 'There was a problem connecting to the payment gateway.', 'woocommerce-cardpay-solutions' ) );
		}

		$data            = explode( '&', $response['body'] );
		$count           = count( $data );
		$parsed_response = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$rdata                        = explode( '=', $data[ $i ] );
			$parsed_response[ $rdata[0] ] = $rdata[1];
		}

		if ( empty( $parsed_response['response'] ) ) {
			$error_msg = __( 'There was a problem connecting to the payment gateway.', 'woocommerce-cardpay-solutions' );
			return new WP_Error( 'cardpay_error', $error_msg );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Get_card_type function
	 *
	 * @param string $number Credit card number.
	 *
	 * @return string
	 */
	public function get_card_type( $number ) {
		if ( preg_match( '/^4\d{12}(\d{3})?(\d{3})?$/', $number ) ) {
			return 'Visa';
		} elseif ( preg_match( '/^3[47]\d{13}$/', $number ) ) {
			return 'American Express';
		} elseif ( preg_match( '/^(5[1-5]\d{4}|677189|222[1-9]\d{2}|22[3-9]\d{3}|2[3-6]\d{4}|27[01]\d{3}|2720\d{2})\d{10}$/', $number ) ) {
			return 'MasterCard';
		} elseif ( preg_match( '/^(6011|65\d{2}|64[4-9]\d)\d{12}|(62\d{14})$/', $number ) ) {
			return 'Discover';
		} elseif ( preg_match( '/^35(28|29|[3-8]\d)\d{12}$/', $number ) ) {
			return 'JCB';
		} elseif ( preg_match( '/^3(0[0-5]|[68]\d)\d{11}$/', $number ) ) {
			return 'Diners Club';
		}
	}
}
