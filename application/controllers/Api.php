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
        $this->user_id = $this->checklogin ? $this->session->userdata('logged_in')['login_id'] : 0;
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
                "uuid" => $userobj ? $userobj["id"] : "",
                "caller_id" => "MyApple",
                "caller_name" => $userobj ? $userobj["name"] : "",
                "caller_id_type" => "number",
                "has_video" => "true"
            )
        ];
        $this->android($data, [$tokenid]);
    }

    function sendCallNotificationV2($receiver_id, $sender_id, $calldata) {
        $reg_id = $this->singleUserGCMToken($receiver_id);
        $tokenid = $reg_id;
        $userobj = $this->singleUser($sender_id);
        $name = $userobj ? $userobj["name"] : " Someone";
        $calldata["name"] = $name;
        //$tokenid = "dV_EyWWoTgeZnUlZanHft3:APA91bETNd6OrqnRBMhZhu-zeDKgY9TfIlloJKOzaVnxNGkqoyaHB549zyAO4kh-96L53EcglgCflBTZnfSZgtHX_KtInAEFa2RgXaYBe-mfaqkoaSGhxuY_BHpA0fsCSmpFoL-jZyRr";
        $data = [
            "to" => $tokenid,
            "notification" => [
                "body" => "Incomming Call From $name",
                "page" => "chat",
                "icon" => "ic_launcher",
                "image" => "https://lh3.googleusercontent.com/a-/AOh14GiB7yiRkI4V4-YdxtDt27CWqF1U-0ZhfQ3mT_96uA"
            ],
            "data" => $calldata
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

    function setCallLog($logarray) {
        $this->db->insert("user_videocall_log", $logarray);
        $last_id = $this->db->insert_id();
    }

    function updateCallStatus_post() {
        $insertArray = array(
            "user_videocall_id" => $this->post("user_videocall_id"),
            "sender_id" => $this->post("sender_id"),
            "receiver_id" => $this->post("receiver_id"),
            "status" => $this->post("status"),
            "call_date" => date("Y-m-d"),
            "call_time" => date("H:i:s A"),
            "call_duration" => $this->post("call_duration")
        );
        $this->setCallLog($insertArray);
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
            "status" => "callinit",
            "call_date" => date("Y-m-d"),
            "call_time" => date("H:i:s A"),
            "call_duration" => ""
        );
        $this->db->insert("user_videocall", $insertArray);
        $last_id = $this->db->insert_id();
        $insertArray["user_videocall_id"] = $last_id;
        $this->sendCallNotificationV2($receiver_id, $sender_id, $insertArray);

        $insertArray = array(
            "user_videocall_id" => $last_id,
            "sender_id" => $sender_id,
            "receiver_id" => $receiver_id,
            "status" => "Outgoing Call",
            "call_date" => date("Y-m-d"),
            "call_time" => date("H:i:s A"),
            "call_duration" => ""
        );
        $this->setCallLog($insertArray);
        $insertArray = array(
            "user_videocall_id" => $last_id,
            "sender_id" => $receiver_id,
            "receiver_id" => $sender_id,
            "status" => "Incomming Call",
            "call_date" => date("Y-m-d"),
            "call_time" => date("H:i:s A"),
            "call_duration" => ""
        );
        $this->setCallLog($insertArray);

        $this->response(array('token' => $token, "channel" => $channelName, "user_videocall_id" => $last_id));
    }

    function getVideoCall_get($user_id) {
        $this->db->where("receiver_id", $user_id);
        $this->db->where("status", "callinit");
        $query = $this->db->get('user_videocall');
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
        $this->db->update(user_videocall, $data);
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
        $query = $this->db->get('user_videocall');
        $userdata = $query->row_array();
        $userdata["user"] = $this->singleUser($userdata["sender_id"]);
        $this->response($userdata);
    }

    function setCall_get($receiver_id, $status) {
        $this->db->where('receiver_id', $receiver_id);
        $this->db->set('status', $status);
        $query = $this->db->update('user_videocall');
        $this->response(array("status" => $status));
    }

    function setFCMToken_post() {
        $postdata = $this->post();
        $insertArray = array(
            "model" => "",
            "manufacturer" => "",
            "uuid" => "",
            "datetime" => date("Y-m-d H:i:s a"),
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
        $tokenid = "c3m5tbYuQmmYXCJvi_FyMk:APA91bG8CgKAbE2Tqg_Bxd_4kjtpQy5ydyNKFi2KfyCI668G8P5vwaxHe3Ie5JR9FcXHjmU2su9sFo2hr9_IY2djytPQHn_zanqgBXknNiCaSN5wQZCEEHABqOBcyt9uuycQTbYChp0q";
        $tokenid = "f2njC-3wT-ehBmvRb9GUff:APA91bE77Jy6Tr1K1AE3c7lDIXNGdqy7ZW73v4uYSZaCYTFLOpucaQFOw0r5tD2ZD9RiEoPjqA2s7o2S1CYPQg6ZKhgJ_NTmZDRm4B7O_dInxPzNzqU6ed0Z9TVa_4CSQEHL3WQCQcNM";
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

    //packet update apilist
    function getPackageList_get() {

        $this->db->order_by("id desc");

        $query = $this->db->get("set_packages");
        $packagelist = $query->result_array();
        $this->response($packagelist);
    }

    //set membership 
    function orderMembership_post() {
        $membershipdata = $this->post();

        $daylimit = $membershipdata['valid_days'];
        $current_date = date("Y-m-d");
        $current_time = date("H:i:s A");
        $last_date = date('Y-m-d', strtotime($current_date . " + $daylimit days"));
        $insertArray = array(
            "member_id" => $membershipdata['member_id'],
            "package_id" => $membershipdata['package_id'],
            "contact_limit" => $membershipdata['contact_limit'],
            "valid_days" => $membershipdata['valid_days'],
            "order_id" => $membershipdata['order_id'],
            "user_id" => $membershipdata['user_id'],
            "last_date" => $last_date,
            "price" => $membershipdata['price'],
            "discount" => $membershipdata['discount'],
            "final_price" => $membershipdata['final_price'],
            "discount_coupon" => $membershipdata['discount_coupon'],
            "payment_data" => $membershipdata['payment_data'],
            "payment_date" => $current_date,
            "payment_time" => $current_time,
            "payment_mode" => $membershipdata['payment_mode'],
            "payment_id" => "",
            "status" => "Active"
        );
        $this->db->insert("user_calling_package", $insertArray);
        $last_id = $this->db->insert_id();
        $responsedata = array("order_id" => $last_id);
        $this->response($responsedata);
    }

    function ordersList_get($user_id) {
        $this->db->where("user_id", $user_id);
        $query = $this->db->get("user_calling_package");
        $orders_list = $query->result_array();
        $this->response($orders_list);
    }

    function orderDetails_get($order_id) {
        $this->db->where("id", $order_id);
        $query = $this->db->get("user_calling_package");
        $order_details = $query->row_array();

        $this->db->where("id", $order_details["package_id"]);
        $query = $this->db->get("set_packages");
        $packagedetails = $query->row_array();

        $this->db->where("id", $order_details["user_id"]);
        $query = $this->db->get("app_user");
        $userdetails = $query->row_array();


        $resultdata = array(
            "order_details" => $order_details,
            "user_details" => $userdetails,
            "package_details" => $packagedetails,
            "member_details" => $userdetails
        );
        $this->response($resultdata);
    }

    function getCouponDiscount_get($coupon_code, $total_amount) {
        $couponArray = array(
            "coupon_id" => "0",
            "coupon_code" => "",
            "discount_amount" => 0,
            "coupon_value" => 0,
            "msg" => "Sorry Wrong Coupon Code"
        );
        if ($coupon_code == "APPLE99") {
            $coupon_discount = ($total_amount * 99) / 100;
            if (($total_amount - $coupon_discount) < 1) {
                $coupon_discount = $total_amount - 1;
            }
            $couponArray = array(
                "coupon_id" => "1",
                "coupon_code" => "APPLE99",
                "discount_amount" => $coupon_discount,
                "msg" => "Coupon code applied successfully"
            );
        }

        $this->response($couponArray);
    }

    //member package statuss
    function daysBetween($dt1, $dt2) {
        return date_diff(
                        date_create($dt2), date_create($dt1)
                )->format('%a');
    }

    function getCurrentPackage_get($member_id) {
        $current_date = date("Y-m-d");
        $this->db->select("package_id, last_date, valid_days, sum(contact_limit) as total_contacts, '' as title, '0' as validity, '' as image   ");
        $this->db->where("member_id", $member_id);
        $this->db->where("last_date>", $current_date);
        $this->db->order_by("id desc");
        $this->db->limit(1);
        $query = $this->db->get("user_calling_package");
        $packageobj = $query->row_array();
        if ($packageobj["total_contacts"]) {
            $packageobj["status"] = "active";
        } else {
            $packageobj["status"] = "inactive";
            $packageobj["total_contacts"] = "0";
            $packageobj["last_date"] = "";
            $packageobj["package_id"] = "";
            $packageobj["valid_days"] = "";
        }
        $packageobj["contact_left"] = 0;
        $packageobj["contact_used"] = 0;
        if ($packageobj["status"] == "active") {
            $this->db->where("id", $packageobj["package_id"]);
            $query = $this->db->get("set_packages");
            $packagedetails = $query->row_array();
            $packageobj["title"] = $packagedetails["title"];
            $packageobj["image"] = $packagedetails["image"];
            $packageobj["validity"] = $this->daysBetween($current_date, $packageobj["last_date"]);

//            $this->db->select("count(id) as used_contact");
//            $this->db->where("member_id", $member_id);
////            $this->db->group_by("connect_member_id");
//            $query = $this->db->get("shadi_saved_profile");
//            $totalusedcontact = $query->row_array();
            $usedcontact = 0;
            $packageobj["contact_used"] = $usedcontact;
            $totalleft = ($packageobj["total_contacts"] - $usedcontact);
            if ($totalleft > 0) {
                
            } else {
                $packageobj["status"] = "inactive";
            }
            $packageobj["contact_left"] = "" . $totalleft;
        }
        $this->response($packageobj);
    }

    function getLastToken_get() {
        $this->db->order_by("id desc");
        $query = $this->db->get("user_videocall");
        print_r($query->row_array());
    }

//
}

?>