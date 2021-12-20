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
    }

    public function index() {
        $this->load->view('welcome_message');
    }


    function raozaPayOrder_get($amount) {
        $oderdata =  $this->razorpayapi->order->create(array('amount' => intval($amount), 'currency' => 'INR'));
        $this->response($oderdata->toArray());
        
    }

//
}

?>