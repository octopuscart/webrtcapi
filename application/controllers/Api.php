<?php

defined('BASEPATH') OR exit('No direct script access allowed');
require(APPPATH . 'libraries/REST_Controller.php');
require(APPPATH . 'libraries/AgoraToken/RtcTokenBuilder.php');

class Api extends REST_Controller {

    public function __construct() {
        parent::__construct();
        $this->API_ACCESS_KEY = "AAAAMrifI78:APA91bEW_lzyKfgT4oV_D2y2ULbpecWEiKwGb-alR_V6-I7uVkJ3WlzzMeeNIAgEgaGn4z7AP2jDxLwfsYqPcc4fNBLFMjqaskCmqD1-JP8R5ujirEg-ZsV-Axa4Wc8ZDsd36dvl1Tgd";
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
        $headers = array(
            'Authorization: key=' . $this->API_ACCESS_KEY,
            'Content-Type: application/json'
        );
        return $this->useCurl($url, $headers, json_encode($data));
    }

    function sendCallNotification($receiver_id, $sender_id) {
        $reg_id = $this->singleUserGCMToken($receiver_id);
        $tokenid = $reg_id;
        $userobj = $this->singleUser($sender_id);
        //$tokenid = "dV_EyWWoTgeZnUlZanHft3:APA91bETNd6OrqnRBMhZhu-zeDKgY9TfIlloJKOzaVnxNGkqoyaHB549zyAO4kh-96L53EcglgCflBTZnfSZgtHX_KtInAEFa2RgXaYBe-mfaqkoaSGhxuY_BHpA0fsCSmpFoL-jZyRr";
        $data = [
            "to" => $tokenid,
            "notification" => [
                "body" => "Incomming Call From",
                "page" => "chat",
                "icon" => "ic_launcher",
                "image" => "https://lh3.googleusercontent.com/a-/AOh14GiB7yiRkI4V4-YdxtDt27CWqF1U-0ZhfQ3mT_96uA"
            ],
            "data" => array(
                "uuid" => $userobj?$userobj["id"]:"",
                "caller_id" => "MyApple",
                "caller_name" => $userobj?$userobj["name"]:"",
                "caller_id_type" => "number",
                "has_video" => "true"
            )
        ];
        $this->android($data, [$tokenid]);
    }

    function singleUser($user_id) {
        $this->db->where('id', $user_id);
        $query = $this->db->get('app_user');
        $userdata = $query->row_array();
        return $userdata;
    }

    function singleUserGCMToken($user_id) {
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('gcm_registration');
        $userdata = $query->row_array();
        return $userdata ? $userdata["reg_id"] : "";
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
        
        $this->sendCallNotification($receiver_id, $sender_id);

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
        $finaluser = [];
        foreach ($userdata as $key => $value) {
            $value["presence"] = "Offline";
            $value["presence_datetime"] = "";
            array_push($finaluser, $value);
        }
        $this->response($finaluser);
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

    function setFCMToken_post() {
        $postdata = $this->post();
        $insertArray = array(
            "model" => "",
            "manufacturer" => "",
            "uuid" => "",
            "datetime" => date("Y-m-d H:m:s a"),
            "user_id" => $postdata["user_id"],
            "reg_id" => $postdata["token_id"],
        );
        $this->db->where("user_id", $postdata["user_id"]);
        $query = $this->db->get("gcm_registration");
        $querydata = $query->result_array();
        if ($querydata) {
            $this->db->set($insertArray)->where("user_id", $postdata["user_id"])->update("gcm_registration");
            $this->response(array("status" => "200", "last_id" => $querydata[0]["id"]));
        } else {
            $this->db->insert("gcm_registration", $insertArray);
            $insert_id = $this->db->insert_id();
        }
        $this->response(array("status" => "200", "last_id" => $insert_id));
    }

    function testNotification_get() {
        $tokenid = "f5HkMsqbTTucMq_sjk4z6J:APA91bGPM5wiz4K5s12O63Bl1H6m5rcu4auZfAdF_wvmanpejiD06jvzCSXjgNxpcs6SzPvZILqo9lYGU2RFzQ_Yz5M1YfyM0IZsmrsyZyjVyusApfigMD8Usd8hn7_qtGXGcVdyMNcs";
        //$tokenid = "dV_EyWWoTgeZnUlZanHft3:APA91bETNd6OrqnRBMhZhu-zeDKgY9TfIlloJKOzaVnxNGkqoyaHB549zyAO4kh-96L53EcglgCflBTZnfSZgtHX_KtInAEFa2RgXaYBe-mfaqkoaSGhxuY_BHpA0fsCSmpFoL-jZyRr";
        $data = [
            "to" => $tokenid,
            "notification" => [
                "body" => "This is message body 32322323 ",
                "page" => "chat",
                "icon" => "ic_launcher",
                "image" => "https://lh3.googleusercontent.com/a-/AOh14GiB7yiRkI4V4-YdxtDt27CWqF1U-0ZhfQ3mT_96uA"
            ],
            "data" => array(
                "uuid" => "xxxxx-xxxxx-xxxxx-xxxxx",
                "caller_id" => "Pankaj Pathyak",
                "caller_name" => "Draco",
                "caller_id_type" => "number",
                "has_video" => "true"
            )
        ];
        echo $this->android($data, [$tokenid]);
    }

}

?>