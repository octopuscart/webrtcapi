<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require(APPPATH . 'libraries/REST_Controller.php');
require(APPPATH . 'libraries/AgoraToken/RtcTokenBuilder.php');

class Api extends REST_Controller {

    public function __construct() {
        parent::__construct();
        $this->API_ACCESS_KEY = 'AIzaSyBlRI5PaIZ6FJPwOdy0-hc8bTiLF5Lm0FQ';
        // (iOS) Private key's passphrase.
        $this->passphrase = 'joashp';
        // (Windows Phone 8) The name of our push channel.
        $this->channelName = "joashp";

        $this->load->library('session');
        $this->checklogin = $this->session->userdata('logged_in');
        $this->user_id = $this->session->userdata('logged_in')['login_id'];
    }

    public function index() {
        $this->load->view('welcome_message');
    }

    public function getAccessToken_get($sender_id, $receiver_id) {
        $appID = "da60aa0af04c4dc1bdb154557cf32f71";
        $appCertificate = "ba1eb1ef6a47460ca7de3672c468094e";
        $channelName = "myc" . rand(10000, 999999);
        $uid = 0;
        $uidStr = "0";
        $role = RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 3600;
        $currentTimestamp = (new DateTime("now", new DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $uid, $role, $privilegeExpiredTs);
//        echo 'Token with int uid: ' . $token;

        $token = RtcTokenBuilder::buildTokenWithUserAccount($appID, $appCertificate, $channelName, $uidStr, $role, $privilegeExpiredTs);
        // echo 'Token with user account: ' . $token . PHP_EOL;

        $insertArray = array(
            "token" => $token,
            "channel" => $channelName,
            "sender_id" => $sender_id,
            "receiver_id" => $receiver_id,
            "status" => "calling"
        );
        $this->db->insert("videocall", $insertArray);

        $this->response(array('token' => $token, "channel" => $channelName));
    }

    function getVideoCall_get($user_id) {
        $this->db->where("receiver_id", $user_id);
        $this->db->where("status", "calling");
        $query = $this->db->get('videocall');
        $userlistdata = $query->result_array();

        $this->db->where("id", $userlistdata[0]['sender_id']);
        $query = $this->db->get('app_user');
        $senderdata = $query->result_array();
        $callobj = array();
        if ($userlistdata) {
            $callobj = $userlistdata[0];
            $callobj['name'] = $senderdata[0]['name'];
            $callobj['contact_no'] = $senderdata[0]['contact_no'];
        }
        $this->response($callobj);
    }

    function getVideoCallStatus_get($channel, $status) {

        $data = array("status" => "$status");
        $this->db->set($data);
        $this->db->where("channel", $channel);
        $this->db->update(videocall, $data);
    }

    private function useCurl($url, $headers, $fields = null) {
        // Open connection
        $ch = curl_init();
        if ($url) {
            // Set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Disabling SSL Certificate support temporarly
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($fields) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            }

            // Execute post
            $result = curl_exec($ch);
            if ($result === FALSE) {
                die('Curl failed: ' . curl_error($ch));
            }

            // Close connection
            curl_close($ch);

            return $result;
        }
    }

    public function android($data, $reg_id_array) {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $insertArray = array(
            'title' => $data['title'],
            'message' => $data['message'],
            "datetime" => date("Y-m-d H:i:s a")
        );
        $this->db->insert("notification", $insertArray);

        $message = array(
            'title' => $data['title'],
            'message' => $data['message'],
            'subtitle' => '',
            'tickerText' => '',
            'msgcnt' => 1,
            'vibrate' => 1
        );

        $headers = array(
            'Authorization: key=' . $this->API_ACCESS_KEY,
            'Content-Type: application/json'
        );

        $fields = array(
            'registration_ids' => $reg_id_array,
            'data' => $message,
        );

        return $this->useCurl($url, $headers, json_encode($fields));
    }

    public function androidAdmin($data, $reg_id_array) {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $insertArray = array(
            'title' => $data['title'],
            'message' => $data['message'],
            "datetime" => date("Y-m-d H:i:s a")
        );
        $this->db->insert("notification", $insertArray);

        $message = array(
            'title' => $data['title'],
            'message' => $data['message'],
            'subtitle' => '',
            'tickerText' => '',
            'msgcnt' => 1,
            'vibrate' => 1
        );

        $headers = array(
            'Authorization: key=' . "AIzaSyBlRI5PaIZ6FJPwOdy0-hc8bTiLF5Lm0FQ",
            'Content-Type: application/json'
        );

        $fields = array(
            'registration_ids' => $reg_id_array,
            'data' => $message,
        );

        return $this->useCurl($url, $headers, json_encode($fields));
    }

    public function iOS($data, $devicetoken) {
        $deviceToken = $devicetoken;
        $ctx = stream_context_create();
        // ck.pem is your certificate file
        stream_context_set_option($ctx, 'ssl', 'local_cert', 'ck.pem');
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->passphrase);
        // Open a connection to the APNS server
        $fp = stream_socket_client(
                'ssl://gateway.sandbox.push.apple.com:2195', $err,
                $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
        if (!$fp)
            exit("Failed to connect: $err $errstr" . PHP_EOL);
        // Create the payload body
        $body['aps'] = array(
            'alert' => array(
                'title' => $data['mtitle'],
                'body' => $data['mdesc'],
            ),
            'sound' => 'default'
        );
        // Encode the payload as JSON
        $payload = json_encode($body);
        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));

        // Close the connection to the server
        fclose($fp);
        if (!$result)
            return 'Message not delivered' . PHP_EOL;
        else
            return 'Message successfully delivered' . PHP_EOL;
    }

    function broadCastMessgeAdmin($messagedict) {
        $this->db->where('user_type', "Admin");
        $query = $this->db->get('gcm_registration');
        $regarray2 = $query->result_array();
        $temparray = [];
        foreach ($regarray2 as $key => $value) {
            array_push($temparray, $value['reg_id']);
        }
        $this->androidAdmin($messagedict, $temparray);
    }

    function broadCastMessge($messagedict) {
//        $this->db->where('user_type', "Guest");
        $query = $this->db->get('gcm_registration');
        $regarray2 = $query->result_array();
        $temparray = [];
        foreach ($regarray2 as $key => $value) {
            array_push($temparray, $value['reg_id']);
        }
        $this->android($messagedict, $temparray);
    }

    function singleMessage($messagedict, $userid) {
        $this->db->where('user_id', $userid);
        $query = $this->db->get('gcm_registration');
        $regarray2 = $query->result_array();
        $temparray = [];
        foreach ($regarray2 as $key => $value) {
            array_push($temparray, $value['reg_id']);
        }
        $this->android($messagedict, $temparray);
    }

    //Login Function 
    //function for product list
    function loginOperation_post() {
        $email = $this->post('contact_no');
        $password = $this->post('password');
        $this->db->where('contact_no', $email);
        $this->db->where('password', $password);
        $query = $this->db->get('app_user');
        $userdata = $query->row_array();

        if ($userdata) {
            if ($userdata["password"] == $password) {
                $this->response(array("status" => "100", "userdata" => $userdata, "message" => "You have logged in successfully"));
            } else {
                $this->response(array("status" => "401", "message" => "You have entered incorrect Password"));
            }
        } else {
            $this->response(array("status" => "401", "message" => "Mobile no. not registered"));
        }
    }

    function getUsers_get($user_id) {
        $this->db->where('id!=', $user_id);
        $query = $this->db->get('app_user');
        $userdata = $query->result_array();
        $this->response($userdata);
    }

    function singleUser($user_id) {
        $this->db->where('id', $user_id);
        $query = $this->db->get('app_user');
        $userdata = $query->row_array();
        return $userdata;
    }

    function getCall_get($receiver_id) {
        $this->db->where('receiver_id', $receiver_id);
        $this->db->where('status', "calling");
        $this->db->order_by("id desc");
        $query = $this->db->get('videocall');
        $userdata = $query->row_array();
        $userdata["user"] = $this->singleUser($userdata["sender_id"]);
        $this->response($userdata);
    }

    function setCall_get($receiver_id, $status) {
        $this->db->where('receiver_id', $receiver_id);
        $this->db->set('status', $status);
        $query = $this->db->update('videocall');
        $this->response(array("status" => $status));
    }

}

?>