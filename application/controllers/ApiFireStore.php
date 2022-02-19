<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require(APPPATH . 'libraries/REST_Controller.php');
require(APPPATH . 'libraries/AgoraToken/RtcTokenBuilder.php');
require(APPPATH . "libraries/razorpay/Razorpay.php");

use Razorpay\Api\Api;

class ApiPayment extends REST_Controller {

    public function __construct() {
        parent::__construct();

        $this->razorpayapi = new Api("rzp_test_p6vuirGgVtNQEl", "D0f2MqmxhdDaTbKkzANJ4gAy");
        $this->db->where_in('attr_key', ["payu_merchant_key", "payu_salt_key",]);
        $query = $this->db->get('configuration_attr');
        $paymentattr = $query->result_array();
        $this->paymentconf = array();
        foreach ($paymentattr as $key => $value) {
            $this->paymentconf[$value['attr_key']] = $value['attr_val'];
        }
    }

    public function index() {
        $this->load->view('welcome_message');
    }

    function raozaPayOrder_get($amount) {
        $oderdata = $this->razorpayapi->order->create(array('amount' => intval($amount), 'currency' => 'INR'));
        $this->response($oderdata->toArray());
    }

    function payuProces_get() {
        $order_no = date("YMDhis");
        $success = site_url('PayuPayment/success/' . $order_no);
        $fail = site_url('PayuPayment/failure/' . $order_no);
        $MERCHANT_KEY = $this->paymentconf["payu_merchant_key"];
        $SALT = $this->paymentconf["payu_salt_key"];
        $data['key'] = $MERCHANT_KEY;
        $productinfo = "Order No. " . $order_no . ", Total Amount: " . "10.00";
        $payu_array = array(
            "key" => $MERCHANT_KEY,
            "email" => "pankaj21pathak@gmail.com",
            "amount" => 10.00,
            "firstname" => "pankaj pathak",
            "phone" => "8602648733",
            "productinfo" => $productinfo,
            "surl" => $success,
            "furl" => $fail,
            "service_provider" => "payu_paisa");
        // Merchant Key and Salt as provided by Payu.
        //$PAYU_BASE_URL = "https://sandboxsecure.payu.in";		// For Sandbox Mode
        $PAYU_BASE_URL = "https://secure.payu.in";     // For Production Mode
        $action = '';
        $posted = array();
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $payu_array['txnid'] = $txnid;
        foreach ($payu_array as $key => $value) {
            $posted[$key] = $value;
        }
        $formError = 0;
        $hash = '';
        // Hash Sequence
        $hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
        if (empty($posted['hash']) && sizeof($posted) > 0) {
            if (
                    empty($posted['key']) || empty($posted['txnid']) || empty($posted['amount']) || empty($posted['firstname']) || empty($posted['email']) || empty($posted['phone']) || empty($posted['productinfo']) || empty($posted['surl']) || empty($posted['furl']) || empty($posted['service_provider'])
            ) {
                $formError = 1;
            } else {
                //$posted['productinfo'] = json_encode(json_decode('[{"name":"tutionfee","description":"","value":"500","isRequired":"false"},{"name":"developmentfee","description":"monthly tution fee","value":"1500","isRequired":"false"}]'));
                $hashVarsSeq = explode('|', $hashSequence);
                $hash_string = '';
                foreach ($hashVarsSeq as $hash_var) {
                    $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
                    $hash_string .= '|';
                }

                $hash_string .= $SALT;

                $hash = strtolower(hash('sha512', $hash_string));
                $action = $PAYU_BASE_URL . '/_payment';
            }
        } elseif (!empty($posted['hash'])) {
            $hash = $posted['hash'];

            $action = $PAYU_BASE_URL . '/_payment';
        }
        $exportarray = array("action" => $action, "hash" => $hash, "payu_array" => $payu_array);
        $this->response($exportarray);
//        $this->load->view('payu/paymentoption', $exportarray);
    }

    function payuHash_get($amount, $user_id) {
        $order_no = date("Ymdhis");
        $success = site_url('PayuPayment/success/' . $order_no);
        $fail = site_url('PayuPayment/failure/' . $order_no);
        $MERCHANT_KEY = $this->paymentconf["payu_merchant_key"];
        $SALT = $this->paymentconf["payu_salt_key"];
        $data['key'] = $MERCHANT_KEY;
        $productinfo = "Order No. " . $order_no . ", Total Amount: " . $amount;
        $payu_array = array(
            "order_no"=>$order_no,
            "key" => $MERCHANT_KEY,
            "email" => "pankaj21pathak@gmail.com",
            "amount" => $amount,
            "firstname" => "pankaj pathak",
            "phone" => "8602648733",
            "productinfo" => $productinfo,
            "surl" => $success,
            "furl" => $fail,
            "service_provider" => "payu_paisa");
        // Merchant Key and Salt as provided by Payu.
        //$PAYU_BASE_URL = "https://sandboxsecure.payu.in";		// For Sandbox Mode
        $PAYU_BASE_URL = "https://secure.payu.in";     // For Production Mode
        $action = '';
        $posted = array();
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $payu_array['txnid'] = $txnid;
        foreach ($payu_array as $key => $value) {
            $posted[$key] = $value;
        }
        $formError = 0;
        $hash = '';
        // Hash Sequence
        $hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
        if (empty($posted['hash']) && sizeof($posted) > 0) {
            if (
                    empty($posted['key']) || empty($posted['txnid']) || empty($posted['amount']) || empty($posted['firstname']) || empty($posted['email']) || empty($posted['phone']) || empty($posted['productinfo']) || empty($posted['surl']) || empty($posted['furl']) || empty($posted['service_provider'])
            ) {
                $formError = 1;
            } else {
                //$posted['productinfo'] = json_encode(json_decode('[{"name":"tutionfee","description":"","value":"500","isRequired":"false"},{"name":"developmentfee","description":"monthly tution fee","value":"1500","isRequired":"false"}]'));
                $hashVarsSeq = explode('|', $hashSequence);
                $hash_string = '';
                foreach ($hashVarsSeq as $hash_var) {
                    $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
                    $hash_string .= '|';
                }

                $hash_string .= $SALT;

                $hash = strtolower(hash('sha512', $hash_string));
                $action = $PAYU_BASE_URL . '/_payment';
            }
        } elseif (!empty($posted['hash'])) {
            $hash = $posted['hash'];

            $action = $PAYU_BASE_URL . '/_payment';
        }
        $payu_array["hash"] = $hash;
        $this->response($payu_array);
    }

}

?>