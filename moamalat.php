<?php

/******************************************************************************************************************************
 *
 *     Plugin Name: Moamalat Gateway - PayForm
 *     Plugin URI: https://moamalat.net/
 *     Description: Moamalat Payment gateway for woocommerce. This plugin supports woocommerce version 3.0.0 or greater version.
 *     Version: 1.0.0
 *     Author: Moamalat
 *     Author URL: https://moamalat.net/
 *
 ********************************************************************************************************************************/
@session_start();
//load plugin finction when woocommerce loaded
add_action('plugins_loaded', 'woocommerce_paysky_creditcard_wc_init', 0);
//paytab plugin function
function woocommerce_paysky_creditcard_wc_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    //extend wc_payment_gateway class and create ad class
    class WC_Gateway_paysky_creditcard_wc extends WC_Payment_Gateway
    {


        public function __construct()
        {

            $this->id = 'paysky';
            $this->icon = apply_filters('woocommerce_paysky_icon',  plugins_url('icons/moamalat.png', __FILE__));
            $this->medthod_title = 'Moamalat Payment Gateway - PayForm';
            $this->method_description = 'Take payments using credit cards, debit cards powered by Moamalat Payment gateway';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();

            //fetch data from admin setting
            $this->title = "Pay by Card (Moamalat Gateway)";
            $this->description = "Take payments using credit cards, debit cards powered by Moamalat Payment gateway";
            $this->merchant_id = $this->settings['merchant_id'];
            $this->live = $this->settings['live'];
            $this->complete_paid_order = $this->settings['complete_paid_order'];
            $this->secret_key = $this->settings['secret_key'];
            $this->terminal_id = $this->settings['terminal_id'];
            //live payment url
            $this->form_submission_method = $this->get_option('form_submission_method') == 'yes' ? true : false;
            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('wp_head', 'wpb_load_crypto_hmac_sha256_javascript');

            add_action('wp_head', 'wpb_hook_javascript');

            if ($this->live == "yes") {
                $this->liveurl = "https://npg.moamalat.net/Cube/PayLink.svc/api/FilterTransactions";
                add_action('wp_head', 'wpb_load_live_server_javascript');
            } else {
                $this->liveurl = "https://tnpg.moamalat.net/Cube/PayLink.svc/api/FilterTransactions";
                add_action('wp_head', 'wpb_load_test_server_javascript');
            }

            if (isset($_GET['lightbox'])) {
                excuse_hook_javascript($_SESSION['paysky_amount'], $this->terminal_id, $this->merchant_id, $_SESSION['paysky_ref_number'], $this->hexToStr($this->secret_key));
            }

            if (isset($_GET['ordercomplete'])) {
                $this->complete_transaction();
            }

            if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }

        //admin form fields or setting on wocommerce
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'paysky'),
                    'type' => 'checkbox',
                    'label' => __('Enable Moamalat PayForm Gateway', 'paysky'),
                    'default' => 'no'
                ),
                'live' => array(
                    'title' => __('Live/Test', 'paysky'),
                    'type' => 'checkbox',
                    'label' => __('Enable Live Moamalat PayForm Gateway', 'paysky'),
                    'default' => 'no'
                ),
                'merchant_id' => array(
                    'title' => __('Merchant id', 'paysky'),
                    'type' => 'text',
                    'value' => '',
                    'description' => __('Please enter the merchant ID ', 'woocommerce'),
                    'default' => '',
                    'required' => true
                ),

                'terminal_id' => array(
                    'title' => __('Terminal id', 'paysky'),
                    'type' => 'text',
                    'value' => '',
                    'description' => __('Please enter Terminal ID of your Moamalat merchant', 'woocommerce'),
                    'default' => '',
                    'size' => '15',
                    'required' => true
                ),
                'secret_key' => array(
                    'title' => __('Secret key', 'paysky'),
                    'type' => 'text',
                    'value' => '',
                    'description' => __('Please enter  Secret key', 'woocommerce'),
                    'default' => '',
                    'size' => '50',
                    'required' => true
                ),
                'complete_paid_order' => array(
                    'title' => __('Complete order after payment', 'paysky'),
                    'type' => 'checkbox',
                    'label' => __('set order status completed after payment instead of processing', 'paysky'),
                    'default' => 'no'
                ),

            );
        }

        //admin option on woocommerce setting
        public function admin_options()
        {

            echo '<h3>' . __('paysky', 'paysky') . '</h3>';
            echo ' <script type="text/javascript">
                jQuery("#mainform").submit(function(){
                  var marchantid=jQuery("#woocommerce_paysky_merchant_id").val();
                  var marchantpass=jQuery("#woocommerce_paysky_terminal_id").val();
                  var secretkey=jQuery("#woocommerce_paysky_secret_key").val();
                  var err_flag=0;
                  var errormsg="Required fields \t\n";          
                  if(marchantid==""){
                            errormsg+="\tPlease enter merchant id";
                  err_flag=1;
                          }
                          if(marchantpass==""){
                            errormsg+="\t Please enter  terminal id";
                            err_flag=1;
                          }    
                          
                          if(secretkey==""){
                            errormsg+="\t Please enter secret key";
                            err_flag=1;
                          } 
                          
                          
                          
                          
                  if(err_flag==1){                  
                      alert(errormsg) ;
                    return false;
                  }
                  else{
                  return true;
                  } 
                }); 
             </script>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }


        /**
         *  There are no payment fields for paysky, but we want to show the description if set.
         **/
        function payment_fields()
        {

            if ($this->description) echo wpautop(wptexturize($this->description));
        }

        /**
         * Get paysky Args for passing to PP
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_paysky_args($order)
        {

            $txnid = $order->get_id() . '_' . date("ymds");
            $redirect = $order->get_checkout_payment_url(true);
            $lang_ = "English";
            if ($locale) {
                $lang_ = "Arabic";
            }

            // paysky Args
            $paysky_args = array(
                'txnid' => $txnid,
                'merchant_email' => $this->merchant_id,
                'secret_key' => $this->secret_key,
                'productinfo' => $productinfo,
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'zipcode' => $order->get_billing_postcode(),
                'cc_phone_number' => $this->getccPhone($order->get_billing_country()),
                'phone' => $order->get_billing_phone(),
                "cc_first_name" => $order->get_billing_first_name(),
                "cc_last_name" => $order->get_billing_last_name(),
                "phone_number" => $order->get_billing_phone(),
                "billing_address" => $order->get_billing_address_1(),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                "postal_code" => $order->get_billing_postcode(),
                "postal_code_shipping" => $order->get_billing_postcode(),
                'country' => $this->getCountryIsoCode($order->get_billing_country()),
                'email' => $order->get_billing_email(),
                'amount' => $order->get_total() + $order->get_total_discount(),
                'discount' => $order->get_total_discount(),
                "currency" => strtoupper(get_woocommerce_currency()),
                "title" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'ip_customer' => $_SERVER['REMOTE_ADDR'],
                'ip_merchant' => (getenv('REMOTE_ADDR') ? getenv('REMOTE_ADDR') : $_SERVER['SERVER_ADDR']),
                "return_url" => $redirect,
                'cms_with_version' => ' WooCommerce  :' . WOOCOMMERCE_VERSION,
                'reference_no' => $txnid,
                'site_url' => $this->website,
                'msg_lang' => is_rtl() ? 'Arabic' : 'English'
            );

            // Shipping
            if ('yes' == $this->send_shipping) {
                $paysky_args['address_shipping'] = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
                $paysky_args['city_shipping'] = $order->get_billing_city();
                $paysky_args['state_shipping'] = $order->get_billing_state();
                $paysky_args['country_shipping'] = $this->getCountryIsoCode($order->get_billing_country());
                $paysky_args['postal_code_shipping'] = $order->get_billing_postcode();
            } else {
                $paysky_args['address_shipping'] = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
                $paysky_args['city_shipping'] = $order->get_billing_city();
                $paysky_args['state_shipping'] = $order->get_billing_state();
                $paysky_args['country_shipping'] = $this->getCountryIsoCode($order->get_billing_country());
                $paysky_args['postal_code_shipping'] = $order->get_billing_postcode();
            }
            $paysky_args['products_per_title'] = "";
            $paysky_args['ProductName'] = "";
            $paysky_args['unit_price'] = "";
            $paysky_args['quantity'] = "";
            $paysky_args['other_charges'] = "";
            $paysky_args['ProductCategory'] = "";

            if ($order->get_billing_postcode() == '') {
                $paysky_args['postal_code'] = substr($order->get_billing_phone(), 0, 5);
                $paysky_args['postal_code_shipping'] = substr($order->get_billing_phone(), 0, 5);
            }

            // Cart Contents
            $item_loop = 0;
            $total_product_value = 0;
            foreach ($order->get_items() as $item) {
                if ($item['qty']) {

                    $item_loop++;
                    $product = $order->get_product_from_item($item);
                    $item_name = $item['name'];
                    $item_meta = new WC_Order_Item_Meta($item['item_meta']);
                    if ($meta = $item_meta->display(true, true)) {
                        $item_name .= ' ( ' . $meta . ' )';
                    }
                    //product description
                    if ($paysky_args['products_per_title'] != '') {
                        $paysky_args['products_per_title'] = $paysky_args['products_per_title'] . ' || ' . $item_name;
                    } else {
                        $paysky_args['products_per_title'] = $item_name;
                    }
                    //product description
                    if ($paysky_args['ProductName'] != '') {
                        $paysky_args['ProductName'] = $paysky_args['ProductName'] . ' || ' . $item_name;
                    } else {
                        $paysky_args['ProductName'] = $item_name;
                    }
                    //product quantity
                    if ($paysky_args['quantity'] != '') {
                        $paysky_args['quantity'] = $paysky_args['quantity'] . ' || ' . $item['qty'];
                    } else {
                        $paysky_args['quantity'] = $item['qty'];
                    }
                    //product  unit price
                    if ($paysky_args['unit_price'] != '') {
                        $paysky_args['unit_price'] = $paysky_args['unit_price'] . ' || ' . $order->get_item_subtotal($item, false);
                    } else {
                        $paysky_args['unit_price'] = $order->get_item_subtotal($item, false);
                    }
                    $total_product_value = $total_product_value + $item['qty'] * $order->get_item_subtotal($item, false);
                    //product category name
                    if ($paysky_args['ProductCategory'] != '') {
                        $paysky_args['ProductCategory'] = $paysky_args['ProductCategory'] . '||' . $item['type'];
                    } else {
                        $paysky_args['ProductCategory'] = $item['type'];
                    }
                }
            }
            $total = $order->get_total() - $total_product_value + $order->get_total_discount();
            $paysky_args['other_charges'] = $total;

            $paysky_args["ShippingMethod"] = $order->get_shipping_method();
            $paysky_args["DeliveryType"] = $order->get_shipping_method();
            $paysky_args["CustomerId"] = get_current_user_id();
            $paysky_args["channelOfOperations"] = "channelOfOperations";

            $paysky_args = apply_filters('woocommerce_paysky_args', $paysky_args);
            $pay_url = $this->before_process($paysky_args);

            return $pay_url;
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {

            global $woocommerce;
            $order = wc_get_order($order_id);
            $_SESSION['paysky_order_id'] = $order_id;
            if (!$this->form_submission_method) {
                $_SESSION['paysky_amount'] = $order->get_total();
                $_SESSION['paysky_ref_number'] = $order->get_id() . '_' . date("ymds");
                return array(
                    'result' => 'success',
                    'redirect' => "?lightbox=true"
                );
            } else {
                wc_add_notice('<strong>Error:</strong> ' . __('Transaction declined .', 'woocommerce'), 'error');
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
        }


        /**
         * Check process for form submittion
         **/
        function before_process($array)
        {
            $request_string = http_build_query($array);
            $response_data = $this->sendRequest($this->liveurl, $request_string);
            $object = json_decode($response_data);
            return $object;
        }


        /**
         * Get response throgh 3 rd party
         **/
        function sendRequest($gateway_url, $request_string)
        {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $gateway_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_string));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }

        //show message when success or not success or payment status
        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }


        function get_cancel_endpoint()
        {

            $cancel_endpoint = wc_get_page_permalink('cart');
            if (!$cancel_endpoint) {
                $cancel_endpoint = home_url();
            }

            if (false === strpos($cancel_endpoint, '?')) {
                $cancel_endpoint = trailingslashit($cancel_endpoint);
            }
            return $cancel_endpoint;
        }


        //Cancel order
        function get_cancel_order_url($orderId)
        {
            // Get cancel endpoint
            $cancel_endpoint = $this->get_cancel_endpoint();
            return apply_filters('woocommerce_get_cancel_order_url', wp_nonce_url(add_query_arg(array(
                'cancel_order' => true,
                'order_id' => $orderId
            ), $cancel_endpoint), 'woocommerce-cancel_order'));
        }


        function getTime()
        {

            $now = new DateTime();
            $time = $now->format('Y-m-d H:i:s');

            $date = strtotime($time);
            $day = date('d', $date);
            $month = date('m', $date);
            $year = date('y', $date);
            $hour = date('H', $date);
            $minutes = date('i', $date);
            $seconds = date('s', $date);
            return $year . $month . $day . $hour . $minutes . $seconds . '';
        }


        function getTimeNow()
        {

            $now = new DateTime();
            $time = $now->format('Y-m-d H:i:s');

            $date = strtotime($time);
            $day = date('d', $date);
            $month = date('m', $date);
            $year = date('Y', $date);
            return $year . $month . $day . '';
        }


        function strToHex($string)
        {
            $hex = '';
            for ($i = 0; $i < strlen($string); $i++) {
                $hex .= dechex(ord($string[$i]));
            }
            return $hex;
        }

        function hexToStr($hex)
        {
            $string = '';
            for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
                $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
            }
            return $string;
        }

        function generateSecureHash($time)
        {
            $merchantId = $this->merchant_id;
            $terminalId = $this->terminal_id;
            $secretKey = $this->secret_key;
            $hashing = "DateTimeLocalTrxn=$time&MerchantId=$merchantId&TerminalId=$terminalId";
            return hash_hmac('sha256', $hashing, $this->hexToStr($secretKey));
        }

        /*
        When transaction completed it is check the status
        is transaction completed or rejected
        */
        function complete_transaction()
        {
            global $woocommerce;
            $order = wc_get_order($_SESSION['paysky_order_id']);
            // $this->secret_key
            $time = $this->getTime();
            $secureHash = $this->generateSecureHash($time);
            $request_string = array(
                'SecureHash' => $secureHash,
                'DateTimeLocalTrxn' => $time,
                'TerminalId' => $this->terminal_id,
                'MerchantId' => $this->merchant_id,
                'DateFrom' => $this->getTimeNow(),
                'DateTo' => $this->getTimeNow(),
                'MerchantReference' => $_SESSION['paysky_ref_number'],
                'FetchType' => "0",
                'DisplayLength' => 1
            );


            $getdataresponse = $this->sendRequest($this->liveurl, $request_string);

            $object = json_decode($getdataresponse);

            if ($object != null) {
                $done = true;
                //if get response successfull
                if ($object->TotalCountAllTransaction > 0) {
                    $paidAmount = doubleval($_SESSION['paysky_amount']) * 1000;
                    $isPaymentApproved = false;
                    $transactions = $object->Transactions;
                    $orderRefNumber = $_SESSION['paysky_ref_number'];
                    foreach ($transactions as $transaction) {
                        $dateTransactions = $transaction->DateTransactions;
                        foreach ($dateTransactions as $dateTransaction) {
                            if ($dateTransaction->MerchantReference ==  $orderRefNumber) {
                                $serverAmount = doubleval($dateTransaction->AmountTrxn);
                                if ($dateTransaction->Status == 'Approved' && ($paidAmount == $serverAmount)) {
                                    $isPaymentApproved = true;
                                    break;
                                } else {
                                    $isPaymentApproved = false;
                                    break;
                                }
                            }
                        }
                    }

                    if ($isPaymentApproved) {

                        $this->msg['class'] = 'woocommerce_message';
                        $payRef = "Payment Reference Number " . $_SESSION['paysky_ref_number'];
                        $order->payment_complete($payRef);
                        $order->reduce_order_stock();
                        $woocommerce->cart->empty_cart();

                        if ($this->complete_paid_order == 'yes') {
                            $order->update_status('completed');
                        } else {
                            $order->update_status('processing');
                        }

                        // clear session.
                        unset($_SESSION['paysky_order_id']);
                        unset($_SESSION['paysky_amount']);
                        unset($_SESSION['paysky_ref_number']);

                        wc_add_notice('' . __('Thank you for shopping with us. Your account has been charged and your transaction is successful.
            We will be shipping your order to you soon.', 'woocommerce'), 'success');

                        wp_redirect($this->get_return_url($order));
                        exit;
                    } else {
                        // payment declined.
                        $order->update_status('failed', __('Payment Cancelled', 'error'));
                        // Add error for the customer when we return back to the cart
                        $message = $object->result;
                        wc_add_notice('<strong></strong> ' . __($message, 'error'), 'error');
                        // Redirect back to the last step in the checkout process
                        wp_redirect($this->get_cancel_order_url($order->get_id()));
                        exit;
                    }
                } else {

                    // Change the status to pending / unpaid
                    $order->update_status('failed', __('Payment Cancelled', 'error'));
                    // Add error for the customer when we return back to the cart
                    $message = $object->result;
                    wc_add_notice('<strong></strong> ' . __($message, 'error'), 'error');
                    // Redirect back to the last step in the checkout process
                    wp_redirect($this->get_cancel_order_url($order->get_id()));
                    exit;
                }
            }
        }


        function getccPhone($code)
        {
            $countries = array(
                "AF" => '+93', //array("AFGHANISTAN", "AF", "AFG", "004"),
                "AL" => '+355', //array("ALBANIA", "AL", "ALB", "008"),
                "DZ" => '+213', //array("ALGERIA", "DZ", "DZA", "012"),
                "AS" => '+376', //array("AMERICAN SAMOA", "AS", "ASM", "016"),
                "AD" => '+376', //array("ANDORRA", "AD", "AND", "020"),
                "AO" => '+244', //array("ANGOLA", "AO", "AGO", "024"),
                "AG" => '+1-268', //array("ANTIGUA AND BARBUDA", "AG", "ATG", "028"),
                "AR" => '+54', //array("ARGENTINA", "AR", "ARG", "032"),
                "AM" => '+374', //array("ARMENIA", "AM", "ARM", "051"),
                "AU" => '+61', //array("AUSTRALIA", "AU", "AUS", "036"),
                "AT" => '+43', //array("AUSTRIA", "AT", "AUT", "040"),
                "AZ" => '+994', //array("AZERBAIJAN", "AZ", "AZE", "031"),
                "BS" => '+1-242', //array("BAHAMAS", "BS", "BHS", "044"),
                "BH" => '+973', //array("BAHRAIN", "BH", "BHR", "048"),
                "BD" => '+880', //array("BANGLADESH", "BD", "BGD", "050"),
                "BB" => '1-246', //array("BARBADOS", "BB", "BRB", "052"),
                "BY" => '+375', //array("BELARUS", "BY", "BLR", "112"),
                "BE" => '+32', //array("BELGIUM", "BE", "BEL", "056"),
                "BZ" => '+501', //array("BELIZE", "BZ", "BLZ", "084"),
                "BJ" => '+229', // array("BENIN", "BJ", "BEN", "204"),
                "BT" => '+975', //array("BHUTAN", "BT", "BTN", "064"),
                "BO" => '+591', //array("BOLIVIA", "BO", "BOL", "068"),
                "BA" => '+387', //array("BOSNIA AND HERZEGOVINA", "BA", "BIH", "070"),
                "BW" => '+267', //array("BOTSWANA", "BW", "BWA", "072"),
                "BR" => '+55', //array("BRAZIL", "BR", "BRA", "076"),
                "BN" => '+673', //array("BRUNEI DARUSSALAM", "BN", "BRN", "096"),
                "BG" => '+359', //array("BULGARIA", "BG", "BGR", "100"),
                "BF" => '+226', //array("BURKINA FASO", "BF", "BFA", "854"),
                "BI" => '+257', //array("BURUNDI", "BI", "BDI", "108"),
                "KH" => '+855', //array("CAMBODIA", "KH", "KHM", "116"),
                "CA" => '+1', //array("CANADA", "CA", "CAN", "124"),
                "CV" => '+238', //array("CAPE VERDE", "CV", "CPV", "132"),
                "CF" => '+236', //array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
                "CM" => '+237', //array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
                "TD" => '+235', //array("CHAD", "TD", "TCD", "148"),
                "CL" => '+56', //array("CHILE", "CL", "CHL", "152"),
                "CN" => '+86', //array("CHINA", "CN", "CHN", "156"),
                "CO" => '+57', //array("COLOMBIA", "CO", "COL", "170"),
                "KM" => '+269', //array("COMOROS", "KM", "COM", "174"),
                "CG" => '+242', //array("CONGO", "CG", "COG", "178"),
                "CR" => '+506', //array("COSTA RICA", "CR", "CRI", "188"),
                "CI" => '+225', //array("COTE D'IVOIRE", "CI", "CIV", "384"),
                "HR" => '+385', //array("CROATIA (local name: Hrvatska)", "HR", "HRV", "191"),
                "CU" => '+53', //array("CUBA", "CU", "CUB", "192"),
                "CY" => '+357', //array("CYPRUS", "CY", "CYP", "196"),
                "CZ" => '+420', //array("CZECH REPUBLIC", "CZ", "CZE", "203"),
                "DK" => '+45', //array("DENMARK", "DK", "DNK", "208"),
                "DJ" => '+253', //array("DJIBOUTI", "DJ", "DJI", "262"),
                "DM" => '+1-767', //array("DOMINICA", "DM", "DMA", "212"),
                "DO" => '+1-809', //array("DOMINICAN REPUBLIC", "DO", "DOM", "214"),
                "EC" => '+593', //array("ECUADOR", "EC", "ECU", "218"),
                "EG" => '+20', //array("EGYPT", "EG", "EGY", "818"),
                "SV" => '+503', //array("EL SALVADOR", "SV", "SLV", "222"),
                "GQ" => '+240', //array("EQUATORIAL GUINEA", "GQ", "GNQ", "226"),
                "RS" => '+381', //array("SERBIA", "RS", "SRB", "688"),
                "ME" => '+382', //array("MONTENERGO","ME","MNE","382"),
                "CD" => '+243', //array("CONGO", "CD", "COD", "243"),
                "TF" => '+262', //array("FRENCH SOUTHERN TERRITORIES", "TF", "ATF", "260"),
                "VG" => '+1', //array("VIRGIN ISLANDS (BRITISH)", "VG", "VGB", "92"),
                "ER" => '+291', //array("ERITREA", "ER", "ERI", "232"),
                "EE" => '+372', //array("ESTONIA", "EE", "EST", "233"),
                "ET" => '+251', //array("ETHIOPIA", "ET", "ETH", "210"),
                "FJ" => '+679', //array("FIJI", "FJ", "FJI", "242"),
                "FI" => '+358', //array("FINLAND", "FI", "FIN", "246"),
                "FR" => '+33', //array("FRANCE", "FR", "FRA", "250"),
                "GA" => '+241', //array("GABON", "GA", "GAB", "266"),
                "GM" => '+220', //array("GAMBIA", "GM", "GMB", "270"),
                "GE" => '+995', //array("GEORGIA", "GE", "GEO", "268"),
                "DE" => '+49', //array("GERMANY", "DE", "DEU", "276"),
                "GH" => '+233', //array("GHANA", "GH", "GHA", "288"),
                "GR" => '+30', //array("GREECE", "GR", "GRC", "300"),
                "GD" => '+1-473', //array("GRENADA", "GD", "GRD", "308"),
                "GT" => '+502', //array("GUATEMALA", "GT", "GTM", "320"),
                "GN" => '+224', //array("GUINEA", "GN", "GIN", "324"),
                "GW" => '+245', //array("GUINEA-BISSAU", "GW", "GNB", "624"),
                "GY" => '+592', //array("GUYANA", "GY", "GUY", "328"),
                "HT" => '+509', //array("HAITI", "HT", "HTI", "332"),
                "HN" => '+504', //array("HONDURAS", "HN", "HND", "340"),
                "HK" => '+852', //array("HONG KONG", "HK", "HKG", "344"),
                "HU" => '+36', //array("HUNGARY", "HU", "HUN", "348"),
                "IS" => '+354', //array("ICELAND", "IS", "ISL", "352"),
                "IN" => '+91', //array("INDIA", "IN", "IND", "356"),
                "ID" => '+62', //array("INDONESIA", "ID", "IDN", "360"),
                "IR" => '+98', //array("IRAN, ISLAMIC REPUBLIC OF", "IR", "IRN", "364"),
                "IQ" => '+964', //array("IRAQ", "IQ", "IRQ", "368"),
                "IE" => '+353', //array("IRELAND", "IE", "IRL", "372"),
                "IL" => '+972', //array("ISRAEL", "IL", "ISR", "376"),
                "IT" => '+39', //array("ITALY", "IT", "ITA", "380"),
                "JM" => '+1-876', //array("JAMAICA", "JM", "JAM", "388"),
                "JP" => '+81', //array("JAPAN", "JP", "JPN", "392"),
                "JO" => '+962', //array("JORDAN", "JO", "JOR", "400"),
                "KZ" => '+7', //array("KAZAKHSTAN", "KZ", "KAZ", "398"),
                "KE" => '+254', //array("KENYA", "KE", "KEN", "404"),
                "KI" => '+686', //array("KIRIBATI", "KI", "KIR", "296"),
                "KP" => '+850', //array("KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF", "KP", "PRK", "408"),
                "KR" => '+82', //array("KOREA, REPUBLIC OF", "KR", "KOR", "410"),
                "KW" => '+965', //array("KUWAIT", "KW", "KWT", "414"),
                "KG" => '+996', //array("KYRGYZSTAN", "KG", "KGZ", "417"),
                "LA" => '+856', //array("LAO PEOPLE'S DEMOCRATIC REPUBLIC", "LA", "LAO", "418"),
                "LV" => '+371', //array("LATVIA", "LV", "LVA", "428"),
                "LB" => '+961', //array("LEBANON", "LB", "LBN", "422"),
                "LS" => '+266', //array("LESOTHO", "LS", "LSO", "426"),
                "LR" => '+231', //array("LIBERIA", "LR", "LBR", "430"),
                "MO" => '+231', //array("LIBERIA", "LR", "LBR", "430"),
                "LY" => '+218', //array("LIBYAN ARAB JAMAHIRIYA", "LY", "LBY", "434"),
                "LI" => '+423', //array("LIECHTENSTEIN", "LI", "LIE", "438"),
                "LU" => '+352', //array("LUXEMBOURG", "LU", "LUX", "442"),
                "MO" => '+389', //array("MACAU", "MO", "MAC", "446"),
                "MG" => '+261', //array("MADAGASCAR", "MG", "MDG", "450"),
                "MW" => '+265', //array("MALAWI", "MW", "MWI", "454"),
                "MY" => '+60', //array("MALAYSIA", "MY", "MYS", "458"),
                "MX" => '+52', //array("MEXICO", "MX", "MEX", "484"),
                "MC" => '+377', //array("MONACO", "MC", "MCO", "492"),
                "MA" => '+212', //array("MOROCCO", "MA", "MAR", "504")
                "NP" => '+977', //array("NEPAL", "NP", "NPL", "524"),
                "NL" => '+31', //array("NETHERLANDS", "NL", "NLD", "528"),
                "NZ" => '+64', //array("NEW ZEALAND", "NZ", "NZL", "554"),
                "NI" => '+505', //array("NICARAGUA", "NI", "NIC", "558"),
                "NE" => '+227', //array("NIGER", "NE", "NER", "562"),
                "NG" => '+234', //array("NIGERIA", "NG", "NGA", "566"),
                "NO" => '+47', //array("NORWAY", "NO", "NOR", "578"),
                "OM" => '+968', //array("OMAN", "OM", "OMN", "512"),
                "PK" => '+92', //array("PAKISTAN", "PK", "PAK", "586"),
                "PA" => '+507', //array("PANAMA", "PA", "PAN", "591"),
                "PG" => '+675', //array("PAPUA NEW GUINEA", "PG", "PNG", "598"),
                "PY" => '+595', // array("PARAGUAY", "PY", "PRY", "600"),
                "PE" => '+51', // array("PERU", "PE", "PER", "604"),
                "PH" => '+63', // array("PHILIPPINES", "PH", "PHL", "608"),
                "PL" => '48', //array("POLAND", "PL", "POL", "616"),
                "PT" => '+351', //array("PORTUGAL", "PT", "PRT", "620"),
                "QA" => '+974', //array("QATAR", "QA", "QAT", "634"),
                "RU" => '+7', //array("RUSSIAN FEDERATION", "RU", "RUS", "643"),
                "RW" => '+250', //array("RWANDA", "RW", "RWA", "646"),
                "SA" => '+966', //array("SAUDI ARABIA", "SA", "SAU", "682"),
                "SN" => '+221', //array("SENEGAL", "SN", "SEN", "686"),
                "SG" => '+65', //array("SINGAPORE", "SG", "SGP", "702"),
                "SK" => '+421', //array("SLOVAKIA (Slovak Republic)", "SK", "SVK", "703"),
                "SI" => '+386', //array("SLOVENIA", "SI", "SVN", "705"),
                "ZA" => '+27', //array("SOUTH AFRICA", "ZA", "ZAF", "710"),
                "ES" => '+34', //array("SPAIN", "ES", "ESP", "724"),
                "LK" => '+94', //array("SRI LANKA", "LK", "LKA", "144"),
                "SD" => '+249', //array("SUDAN", "SD", "SDN", "736"),
                "SZ" => '+268', //array("SWAZILAND", "SZ", "SWZ", "748"),
                "SE" => '+46', //array("SWEDEN", "SE", "SWE", "752"),
                "CH" => '+41', //array("SWITZERLAND", "CH", "CHE", "756"),
                "SY" => '+963', //array("SYRIAN ARAB REPUBLIC", "SY", "SYR", "760"),
                "TZ" => '+255', //array("TANZANIA, UNITED REPUBLIC OF", "TZ", "TZA", "834"),
                "TH" => '+66', //array("THAILAND", "TH", "THA", "764"),
                "TG" => '+228', //array("TOGO", "TG", "TGO", "768"),
                "TO" => '+676', //array("TONGA", "TO", "TON", "776"),
                "TN" => '+216', //array("TUNISIA", "TN", "TUN", "788"),
                "TR" => '+90', //array("TURKEY", "TR", "TUR", "792"),
                "TM" => '+993', //array("TURKMENISTAN", "TM", "TKM", "795"),
                "UA" => '+380', //array("UKRAINE", "UA", "UKR", "804"),
                "AE" => '+971', //array("UNITED ARAB EMIRATES", "AE", "ARE", "784"),
                "GB" => '+44', //array("UNITED KINGDOM", "GB", "GBR", "826"),
                "US" => '+1' //array("UNITED STATES", "US", "USA", "840"),

            );


            return $countries[$code];
        }

        /*
        Get country code function
        */
        function getCountryIsoCode($code)
        {

            $countries = array(
                "AF" => array("AFGHANISTAN", "AF", "AFG", "004"),
                "AL" => array("ALBANIA", "AL", "ALB", "008"),
                "DZ" => array("ALGERIA", "DZ", "DZA", "012"),
                "AS" => array("AMERICAN SAMOA", "AS", "ASM", "016"),
                "AD" => array("ANDORRA", "AD", "AND", "020"),
                "AO" => array("ANGOLA", "AO", "AGO", "024"),
                "AI" => array("ANGUILLA", "AI", "AIA", "660"),
                "AQ" => array("ANTARCTICA", "AQ", "ATA", "010"),
                "AG" => array("ANTIGUA AND BARBUDA", "AG", "ATG", "028"),
                "AR" => array("ARGENTINA", "AR", "ARG", "032"),
                "AM" => array("ARMENIA", "AM", "ARM", "051"),
                "AW" => array("ARUBA", "AW", "ABW", "533"),
                "AU" => array("AUSTRALIA", "AU", "AUS", "036"),
                "AT" => array("AUSTRIA", "AT", "AUT", "040"),
                "AZ" => array("AZERBAIJAN", "AZ", "AZE", "031"),
                "BS" => array("BAHAMAS", "BS", "BHS", "044"),
                "BH" => array("BAHRAIN", "BH", "BHR", "048"),
                "BD" => array("BANGLADESH", "BD", "BGD", "050"),
                "BB" => array("BARBADOS", "BB", "BRB", "052"),
                "BY" => array("BELARUS", "BY", "BLR", "112"),
                "BE" => array("BELGIUM", "BE", "BEL", "056"),
                "BZ" => array("BELIZE", "BZ", "BLZ", "084"),
                "BJ" => array("BENIN", "BJ", "BEN", "204"),
                "BM" => array("BERMUDA", "BM", "BMU", "060"),
                "BT" => array("BHUTAN", "BT", "BTN", "064"),
                "BO" => array("BOLIVIA", "BO", "BOL", "068"),
                "BA" => array("BOSNIA AND HERZEGOVINA", "BA", "BIH", "070"),
                "BW" => array("BOTSWANA", "BW", "BWA", "072"),
                "BV" => array("BOUVET ISLAND", "BV", "BVT", "074"),
                "BR" => array("BRAZIL", "BR", "BRA", "076"),
                "IO" => array("BRITISH INDIAN OCEAN TERRITORY", "IO", "IOT", "086"),
                "BN" => array("BRUNEI DARUSSALAM", "BN", "BRN", "096"),
                "BG" => array("BULGARIA", "BG", "BGR", "100"),
                "BF" => array("BURKINA FASO", "BF", "BFA", "854"),
                "BI" => array("BURUNDI", "BI", "BDI", "108"),
                "KH" => array("CAMBODIA", "KH", "KHM", "116"),
                "CM" => array("CAMEROON", "CM", "CMR", "120"),
                "CA" => array("CANADA", "CA", "CAN", "124"),
                "CV" => array("CAPE VERDE", "CV", "CPV", "132"),
                "KY" => array("CAYMAN ISLANDS", "KY", "CYM", "136"),
                "CF" => array("CENTRAL AFRICAN REPUBLIC", "CF", "CAF", "140"),
                "TD" => array("CHAD", "TD", "TCD", "148"),
                "CL" => array("CHILE", "CL", "CHL", "152"),
                "CN" => array("CHINA", "CN", "CHN", "156"),
                "CX" => array("CHRISTMAS ISLAND", "CX", "CXR", "162"),
                "CC" => array("COCOS (KEELING) ISLANDS", "CC", "CCK", "166"),
                "CO" => array("COLOMBIA", "CO", "COL", "170"),
                "KM" => array("COMOROS", "KM", "COM", "174"),
                "CG" => array("CONGO", "CG", "COG", "178"),
                "CD" => array("CONGO", "CD", "COD", "243"),
                "CK" => array("COOK ISLANDS", "CK", "COK", "184"),
                "CR" => array("COSTA RICA", "CR", "CRI", "188"),
                "CI" => array("COTE D'IVOIRE", "CI", "CIV", "384"),
                "HR" => array("CROATIA (local name: Hrvatska)", "HR", "HRV", "191"),
                "CU" => array("CUBA", "CU", "CUB", "192"),
                "CY" => array("CYPRUS", "CY", "CYP", "196"),
                "CZ" => array("CZECH REPUBLIC", "CZ", "CZE", "203"),
                "DK" => array("DENMARK", "DK", "DNK", "208"),
                "DJ" => array("DJIBOUTI", "DJ", "DJI", "262"),
                "DM" => array("DOMINICA", "DM", "DMA", "212"),
                "DO" => array("DOMINICAN REPUBLIC", "DO", "DOM", "214"),
                "TL" => array("EAST TIMOR", "TL", "TLS", "626"),
                "EC" => array("ECUADOR", "EC", "ECU", "218"),
                "EG" => array("EGYPT", "EG", "EGY", "818"),
                "SV" => array("EL SALVADOR", "SV", "SLV", "222"),
                "GQ" => array("EQUATORIAL GUINEA", "GQ", "GNQ", "226"),
                "ER" => array("ERITREA", "ER", "ERI", "232"),
                "EE" => array("ESTONIA", "EE", "EST", "233"),
                "ET" => array("ETHIOPIA", "ET", "ETH", "210"),
                "FK" => array("FALKLAND ISLANDS (MALVINAS)", "FK", "FLK", "238"),
                "FO" => array("FAROE ISLANDS", "FO", "FRO", "234"),
                "FJ" => array("FIJI", "FJ", "FJI", "242"),
                "FI" => array("FINLAND", "FI", "FIN", "246"),
                "FR" => array("FRANCE", "FR", "FRA", "250"),
                "FX" => array("FRANCE, METROPOLITAN", "FX", "FXX", "249"),
                "GF" => array("FRENCH GUIANA", "GF", "GUF", "254"),
                "PF" => array("FRENCH POLYNESIA", "PF", "PYF", "258"),
                "TF" => array("FRENCH SOUTHERN TERRITORIES", "TF", "ATF", "260"),
                "GA" => array("GABON", "GA", "GAB", "266"),
                "GM" => array("GAMBIA", "GM", "GMB", "270"),
                "GE" => array("GEORGIA", "GE", "GEO", "268"),
                "DE" => array("GERMANY", "DE", "DEU", "276"),
                "GH" => array("GHANA", "GH", "GHA", "288"),
                "GI" => array("GIBRALTAR", "GI", "GIB", "292"),
                "GR" => array("GREECE", "GR", "GRC", "300"),
                "GL" => array("GREENLAND", "GL", "GRL", "304"),
                "GD" => array("GRENADA", "GD", "GRD", "308"),
                "GP" => array("GUADELOUPE", "GP", "GLP", "312"),
                "GU" => array("GUAM", "GU", "GUM", "316"),
                "GT" => array("GUATEMALA", "GT", "GTM", "320"),
                "GN" => array("GUINEA", "GN", "GIN", "324"),
                "GW" => array("GUINEA-BISSAU", "GW", "GNB", "624"),
                "GY" => array("GUYANA", "GY", "GUY", "328"),
                "HT" => array("HAITI", "HT", "HTI", "332"),
                "HM" => array("HEARD ISLAND & MCDONALD ISLANDS", "HM", "HMD", "334"),
                "HN" => array("HONDURAS", "HN", "HND", "340"),
                "HK" => array("HONG KONG", "HK", "HKG", "344"),
                "HU" => array("HUNGARY", "HU", "HUN", "348"),
                "IS" => array("ICELAND", "IS", "ISL", "352"),
                "IN" => array("INDIA", "IN", "IND", "356"),
                "ID" => array("INDONESIA", "ID", "IDN", "360"),
                "IR" => array("IRAN, ISLAMIC REPUBLIC OF", "IR", "IRN", "364"),
                "IQ" => array("IRAQ", "IQ", "IRQ", "368"),
                "IE" => array("IRELAND", "IE", "IRL", "372"),
                "IL" => array("ISRAEL", "IL", "ISR", "376"),
                "IT" => array("ITALY", "IT", "ITA", "380"),
                "JM" => array("JAMAICA", "JM", "JAM", "388"),
                "JP" => array("JAPAN", "JP", "JPN", "392"),
                "JO" => array("JORDAN", "JO", "JOR", "400"),
                "KZ" => array("KAZAKHSTAN", "KZ", "KAZ", "398"),
                "KE" => array("KENYA", "KE", "KEN", "404"),
                "KI" => array("KIRIBATI", "KI", "KIR", "296"),
                "KP" => array("KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF", "KP", "PRK", "408"),
                "KR" => array("KOREA, REPUBLIC OF", "KR", "KOR", "410"),
                "KW" => array("KUWAIT", "KW", "KWT", "414"),
                "KG" => array("KYRGYZSTAN", "KG", "KGZ", "417"),
                "LA" => array("LAO PEOPLE'S DEMOCRATIC REPUBLIC", "LA", "LAO", "418"),
                "LV" => array("LATVIA", "LV", "LVA", "428"),
                "LB" => array("LEBANON", "LB", "LBN", "422"),
                "LS" => array("LESOTHO", "LS", "LSO", "426"),
                "LR" => array("LIBERIA", "LR", "LBR", "430"),
                "LY" => array("LIBYAN ARAB JAMAHIRIYA", "LY", "LBY", "434"),
                "LI" => array("LIECHTENSTEIN", "LI", "LIE", "438"),
                "LT" => array("LITHUANIA", "LT", "LTU", "440"),
                "LU" => array("LUXEMBOURG", "LU", "LUX", "442"),
                "MO" => array("MACAU", "MO", "MAC", "446"),
                "MK" => array("MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF", "MK", "MKD", "807"),
                "MG" => array("MADAGASCAR", "MG", "MDG", "450"),
                "MW" => array("MALAWI", "MW", "MWI", "454"),
                "MY" => array("MALAYSIA", "MY", "MYS", "458"),
                "MV" => array("MALDIVES", "MV", "MDV", "462"),
                "ML" => array("MALI", "ML", "MLI", "466"),
                "MT" => array("MALTA", "MT", "MLT", "470"),
                "MH" => array("MARSHALL ISLANDS", "MH", "MHL", "584"),
                "MQ" => array("MARTINIQUE", "MQ", "MTQ", "474"),
                "MR" => array("MAURITANIA", "MR", "MRT", "478"),
                "MU" => array("MAURITIUS", "MU", "MUS", "480"),
                "YT" => array("MAYOTTE", "YT", "MYT", "175"),
                "MX" => array("MEXICO", "MX", "MEX", "484"),
                "FM" => array("MICRONESIA, FEDERATED STATES OF", "FM", "FSM", "583"),
                "MD" => array("MOLDOVA, REPUBLIC OF", "MD", "MDA", "498"),
                "ME" => array("MONTENERGO", "ME", "MNE", "382"),
                "MC" => array("MONACO", "MC", "MCO", "492"),
                "MN" => array("MONGOLIA", "MN", "MNG", "496"),
                "MS" => array("MONTSERRAT", "MS", "MSR", "500"),
                "MA" => array("MOROCCO", "MA", "MAR", "504"),
                "MZ" => array("MOZAMBIQUE", "MZ", "MOZ", "508"),
                "MM" => array("MYANMAR", "MM", "MMR", "104"),
                "NA" => array("NAMIBIA", "NA", "NAM", "516"),
                "NR" => array("NAURU", "NR", "NRU", "520"),
                "NP" => array("NEPAL", "NP", "NPL", "524"),
                "NL" => array("NETHERLANDS", "NL", "NLD", "528"),
                "AN" => array("NETHERLANDS ANTILLES", "AN", "ANT", "530"),
                "NC" => array("NEW CALEDONIA", "NC", "NCL", "540"),
                "NZ" => array("NEW ZEALAND", "NZ", "NZL", "554"),
                "NI" => array("NICARAGUA", "NI", "NIC", "558"),
                "NE" => array("NIGER", "NE", "NER", "562"),
                "NG" => array("NIGERIA", "NG", "NGA", "566"),
                "NU" => array("NIUE", "NU", "NIU", "570"),
                "NF" => array("NORFOLK ISLAND", "NF", "NFK", "574"),
                "MP" => array("NORTHERN MARIANA ISLANDS", "MP", "MNP", "580"),
                "NO" => array("NORWAY", "NO", "NOR", "578"),
                "OM" => array("OMAN", "OM", "OMN", "512"),
                "PK" => array("PAKISTAN", "PK", "PAK", "586"),
                "PW" => array("PALAU", "PW", "PLW", "585"),
                "PA" => array("PANAMA", "PA", "PAN", "591"),
                "PG" => array("PAPUA NEW GUINEA", "PG", "PNG", "598"),
                "PY" => array("PARAGUAY", "PY", "PRY", "600"),
                "PE" => array("PERU", "PE", "PER", "604"),
                "PH" => array("PHILIPPINES", "PH", "PHL", "608"),
                "PN" => array("PITCAIRN", "PN", "PCN", "612"),
                "PL" => array("POLAND", "PL", "POL", "616"),
                "PT" => array("PORTUGAL", "PT", "PRT", "620"),
                "PR" => array("PUERTO RICO", "PR", "PRI", "630"),
                "QA" => array("QATAR", "QA", "QAT", "634"),
                "RE" => array("REUNION", "RE", "REU", "638"),
                "RO" => array("ROMANIA", "RO", "ROU", "642"),
                "RU" => array("RUSSIAN FEDERATION", "RU", "RUS", "643"),
                "RW" => array("RWANDA", "RW", "RWA", "646"),
                "KN" => array("SAINT KITTS AND NEVIS", "KN", "KNA", "659"),
                "LC" => array("SAINT LUCIA", "LC", "LCA", "662"),
                "VC" => array("SAINT VINCENT AND THE GRENADINES", "VC", "VCT", "670"),
                "WS" => array("SAMOA", "WS", "WSM", "882"),
                "SM" => array("SAN MARINO", "SM", "SMR", "674"),
                "ST" => array("SAO TOME AND PRINCIPE", "ST", "STP", "678"),
                "SA" => array("SAUDI ARABIA", "SA", "SAU", "682"),
                "SN" => array("SENEGAL", "SN", "SEN", "686"),
                "RS" => array("SERBIA", "RS", "SRB", "688"),
                "SC" => array("SEYCHELLES", "SC", "SYC", "690"),
                "SL" => array("SIERRA LEONE", "SL", "SLE", "694"),
                "SG" => array("SINGAPORE", "SG", "SGP", "702"),
                "SK" => array("SLOVAKIA (Slovak Republic)", "SK", "SVK", "703"),
                "SI" => array("SLOVENIA", "SI", "SVN", "705"),
                "SB" => array("SOLOMON ISLANDS", "SB", "SLB", "90"),
                "SO" => array("SOMALIA", "SO", "SOM", "706"),
                "ZA" => array("SOUTH AFRICA", "ZA", "ZAF", "710"),
                "ES" => array("SPAIN", "ES", "ESP", "724"),
                "LK" => array("SRI LANKA", "LK", "LKA", "144"),
                "SH" => array("SAINT HELENA", "SH", "SHN", "654"),
                "PM" => array("SAINT PIERRE AND MIQUELON", "PM", "SPM", "666"),
                "SD" => array("SUDAN", "SD", "SDN", "736"),
                "SR" => array("SURINAME", "SR", "SUR", "740"),
                "SJ" => array("SVALBARD AND JAN MAYEN ISLANDS", "SJ", "SJM", "744"),
                "SZ" => array("SWAZILAND", "SZ", "SWZ", "748"),
                "SE" => array("SWEDEN", "SE", "SWE", "752"),
                "CH" => array("SWITZERLAND", "CH", "CHE", "756"),
                "SY" => array("SYRIAN ARAB REPUBLIC", "SY", "SYR", "760"),
                "TW" => array("TAIWAN, PROVINCE OF CHINA", "TW", "TWN", "158"),
                "TJ" => array("TAJIKISTAN", "TJ", "TJK", "762"),
                "TZ" => array("TANZANIA, UNITED REPUBLIC OF", "TZ", "TZA", "834"),
                "TH" => array("THAILAND", "TH", "THA", "764"),
                "TG" => array("TOGO", "TG", "TGO", "768"),
                "TK" => array("TOKELAU", "TK", "TKL", "772"),
                "TO" => array("TONGA", "TO", "TON", "776"),
                "TT" => array("TRINIDAD AND TOBAGO", "TT", "TTO", "780"),
                "TN" => array("TUNISIA", "TN", "TUN", "788"),
                "TR" => array("TURKEY", "TR", "TUR", "792"),
                "TM" => array("TURKMENISTAN", "TM", "TKM", "795"),
                "TC" => array("TURKS AND CAICOS ISLANDS", "TC", "TCA", "796"),
                "TV" => array("TUVALU", "TV", "TUV", "798"),
                "UG" => array("UGANDA", "UG", "UGA", "800"),
                "UA" => array("UKRAINE", "UA", "UKR", "804"),
                "AE" => array("UNITED ARAB EMIRATES", "AE", "ARE", "784"),
                "GB" => array("UNITED KINGDOM", "GB", "GBR", "826"),
                "US" => array("UNITED STATES", "US", "USA", "840"),
                "UM" => array("UNITED STATES MINOR OUTLYING ISLANDS", "UM", "UMI", "581"),
                "UY" => array("URUGUAY", "UY", "URY", "858"),
                "UZ" => array("UZBEKISTAN", "UZ", "UZB", "860"),
                "VU" => array("VANUATU", "VU", "VUT", "548"),
                "VA" => array("VATICAN CITY STATE (HOLY SEE)", "VA", "VAT", "336"),
                "VE" => array("VENEZUELA", "VE", "VEN", "862"),
                "VN" => array("VIET NAM", "VN", "VNM", "704"),
                "VG" => array("VIRGIN ISLANDS (BRITISH)", "VG", "VGB", "92"),
                "VI" => array("VIRGIN ISLANDS (U.S.)", "VI", "VIR", "850"),
                "WF" => array("WALLIS AND FUTUNA ISLANDS", "WF", "WLF", "876"),
                "EH" => array("WESTERN SAHARA", "EH", "ESH", "732"),
                "YE" => array("YEMEN", "YE", "YEM", "887"),
                "YU" => array("YUGOSLAVIA", "YU", "YUG", "891"),
                "ZR" => array("ZAIRE", "ZR", "ZAR", "180"),
                "ZM" => array("ZAMBIA", "ZM", "ZMB", "894"),
                "ZW" => array("ZIMBABWE", "ZW", "ZWE", "716"),
            );

            return $countries[$code][2];
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_paysky_creditcard_wc_gateway($methods)
    {
        $methods[] = 'WC_Gateway_paysky_creditcard_wc';
        return $methods;
    }


    function excuse_hook_javascript($amount, $terminalId, $merchantId, $refNumber, $secretKey)
    {

        echo '  <script type="text/javascript">
            console.log("excuse_hook_javascript");
            var amount =  ' . $amount * 1000 . ';
            var terminalId = ' . $terminalId . ';
            var merchantId = ' . $merchantId . ';
            var refNumber =  "' . $refNumber . '";
            var secretKey =  "' . $secretKey . '";

        setTimeout(function(){
          callLightbox(amount, merchantId , terminalId , refNumber ,secretKey);
             }, 2000);
        </script>';
    }

    /**
     * load crypto hmac libs to use them in generate secure has
     */
    function wpb_load_crypto_hmac_sha256_javascript()
    {
?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/hmac-sha256.min.js"></script>

    <?php
    }

    function wpb_load_test_server_javascript()
    {
    ?>
        <script type="text/javascript">
            loadScript('https://tnpg.moamalat.net:6006/js/lightbox.js');
        </script>


    <?php
    }

    function wpb_load_live_server_javascript()
    {
    ?>
        <script type="text/javascript">
            loadScript('https://npg.moamalat.net:6006/js/lightbox.js');
        </script>


<?php
    }


    function completeRequest()
    {
        $order = wc_get_order($_SESSION['paysky_order_id']);
        $url = $order->get_checkout_payment_url(true);
        $data = array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }


    function wpb_hook_javascript()
    {

        echo " <script type='text/javascript'>
            console.log('callLightbox');

            function loadScript(url) {
                console.log(url);

                // Adding the script tag to the head as suggested before
                var head = document.head;
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;

                // Then bind the event to the callback function.
                // There are several events for cross browser compatibility.
                //script.onreadystatechange = callback;
                // script.onload = callback;

                // Fire the loading
                head.appendChild(script);
            }
 
            function genereateSecureHash(amount, dateTimeLocalTrxn, mID, tID, merchantReference, key) {
                var msg = 'Amount='+amount+'&DateTimeLocalTrxn='+dateTimeLocalTrxn+'&MerchantId='+mID+'&MerchantReference='+merchantReference+'&TerminalId='+tID;
                console.log(msg);
                var hash = CryptoJS.HmacSHA256(msg, key).toString().toUpperCase();
                console.log({'hash' : hash});
                return hash;
            }
            
            function callLightbox(amount, mID, tID, merchantReference, key) {

                var dateResponse = null;
                var dateTimeLocalTrxn = Number.parseInt(Date.now() / 1000).toString();
                if (mID === '' || tID === '') {
                    return;
                }

                Lightbox.Checkout.configure = { 
                    MID: mID,
                    TID: tID,
                    AmountTrxn: amount,
                    MerchantReference: merchantReference,
                    TrxDateTime: dateTimeLocalTrxn,
                    SecureHash: genereateSecureHash(amount, dateTimeLocalTrxn, mID, tID, merchantReference, key),
                    completeCallback: function (data) {
                        dateResponse = data;

                    },
                    errorCallback: function () {
                      
                    },
                    cancelCallback: function () {
                                    
                   if (dateResponse != null) 
                    window.location =    window.location.href.split('?')[0] +'?ordercomplete=true&systemreference='+dateResponse.SystemReference ;
                    }
                };

                Lightbox.Checkout.showLightbox();
            }


        </script> ";
    }


    add_filter('woocommerce_payment_gateways', 'woocommerce_add_paysky_creditcard_wc_gateway');
}

?>