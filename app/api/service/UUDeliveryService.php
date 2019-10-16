<?php

namespace app\api\service;


use app\admin\model\MenusOrder;

class UUDeliveryService
{

    public $appid;
    public $appKey;

    public function __construct()
    {
        $this->appid = GetConfig('uu_appid','b099c26b5c4549c0a147e84c86038c1a');
        $this->appKey = GetConfig('uu_appkey','b099c26b5c4549c0a147e84c86038c1a');
    }

    /**
     * 获取订单价格
     */
    public function GetOrderPrice($order)
    {
        header("Content-type: text/html; charset=utf-8");

        $guid = str_replace('-', '', $this->guid());
// 远程地址
        $url = "http://openapi.uupaotui.com/v2_0/getorderprice.ashx";
// POST数据
//        $data = array(
//            'appid'       => $appid,
//            'nonce_str'   => strtolower($guid),
//            'timestamp'   => time(),
//            'user_mobile' => '13700000000',
//            'user_ip'     => '192.168.1.66'
//        );

        $reci_address = json_decode($order->reci_address, true);
        $send_address = Json_decode($order->send_address, true);
//        $send_address = Json_decode($order->send_address,true);
//        $reci_address['detail'] = '天汇大厦B栋639';
//        $send_address['detail'] = '万众步行街32号';
        $data = [
            'origin_id' => $order->id,
            'from_address' => $reci_address['province'] . $reci_address['city'] . $reci_address['district'],
            'from_usernote' => $reci_address['detail'],
            'to_address' => $send_address['province'] . $send_address['city'] . $send_address['district'],
            'to_usernote' => $send_address['detail'],
            'city_name' => $reci_address['city'],
//            'subscribe_type' => 0,
//            'county_name' => $reci_address['district'],
//            'subscribe_time' => $reci_address['district'],
//            'coupon_id' => '-1',
            'send_type' => 0,
            'to_lat' => 22.643299,
            'to_lng' => 114.046106,
            'from_lat' => 22.638491,
            'from_lng' => 114.039073,
            'nonce_str' => $order->order_no,
            'timestamp' => time(),
            'openid' => '9a88e9152c224c98aa737fa13cbbbc8a',
            'appid' =>  $this->appid,
        ];
        ksort($data);
        $data['sign'] = $this->sign($data, $this->appKey);


        $res = $this->request_post($url, $data);
        $res = json_decode($res, true);
        if ($res) {
            return $res;
        }
        return false;
    }

    /**
     * 发布订单
     */
    public function AddOrder($order, $post)
    {
        header("Content-type: text/html; charset=utf-8");

        $guid = str_replace('-', '', $this->guid());

// 远程地址
        $url = "http://openapi.uupaotui.com/v2_0/addorder.ashx";
// POST数据
        $reci_address = json_decode($order->reci_address, true);
        $send_address = Json_decode($order->send_address, true);

        $data = [
            'price_token' => $post['price_token'],
            'order_price' => $post['total_money'],
            'balance_paymoney' => $post['need_paymoney'],
            'receiver' => $send_address['name'],
            'receiver_phone' => $send_address['mobile'],
//            'note' => $order['remark'],
//            'callback_url' => '',
            'push_type' => 2,
            'special_type' => 1,
            'callme_withtake' => 1,
            'pubusermobile' => $reci_address['mobile'],
            'nonce_str' => $order->order_no,
            'timestamp' => time(),
            'openid' => '9a88e9152c224c98aa737fa13cbbbc8a',
            'appid' => $this->appid
        ];
        ksort($data);
        $data['sign'] = $this->sign($data, $this->appKey);


        $res = $this->request_post($url, $data);
        $res = json_decode($res, true);
        if ($res) {
            return $res;
        }
        return false;
    }


    /**
     * 取消订单
     */
    public function CancelOrder($order)
    {
        header("Content-type: text/html; charset=utf-8");

        $guid = str_replace('-', '', $this->guid());

// 远程地址
        $url = "http://openapi.uupaotui.com/v2_0/cancelorder.ashx";
// POST数据
        $data = [
            'order_code' => $order->order_code,
            'origin_id' => $order->id,
            'reason' => '取消订单',
            'nonce_str' => $order->order_no,
            'timestamp' => time(),
            'openid' => '9a88e9152c224c98aa737fa13cbbbc8a',
            'appid' => $this->appid
        ];
        ksort($data);
        $data['sign'] = $this->sign($data, $this->appKey);


        $res = $this->request_post($url, $data);
        $res = json_decode($res, true);
        if ($res) {
            return $res;
        }
        return false;
    }

    /**
     * 获取订单详情
     */
    public function GetOrderDetail($order)
    {
        header("Content-type: text/html; charset=utf-8");

        $guid = str_replace('-', '', $this->guid());

        // 远程地址
        $url = "http://openapi.uupaotui.com/v2_0/getorderdetail.ashx";
        // POST数据
        $data = [
            'order_code' => $order->order_code,
            'origin_id' => $order->id,
            'nonce_str' => $order->order_no,
            'timestamp' => time(),
            'openid' => '9a88e9152c224c98aa737fa13cbbbc8a',
            'appid' => $this->appid
        ];
        ksort($data);
        $data['sign'] = $this->sign($data, $this->appKey);
        $res = $this->request_post($url, $data);
        $res = json_decode($res, true);
        if ($res) {
            return $res;
        }
        return false;
    }

    /**
     * 发起http post请求
     */
    public function request_post($url = '', $post_data = array())
    {
        if (empty($url) || empty($post_data)) {
            return false;
        }

        $arr = [];
        foreach ($post_data as $key => $value) {
            $arr[] = $key . '=' . $value;
        }

        $curlPost = implode('&', $arr);

        $postUrl = $url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * 生成guid
     */
    public function guid()
    {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            return $uuid;
        }
    }

    /**
     * 生成签名
     */
    public function sign($data, $appKey)
    {
        $arr = [];
        foreach ($data as $key => $value) {
            $arr[] = $key . '=' . $value;
        }

        $arr[] = 'key=' . $appKey;
        $str = strtoupper(implode('&', $arr));
        //$str = http_build_query($arr, '&');
//        var_dump('签名前:'.$str);
//        echo "<br />";
        return strtoupper(md5($str));
    }

}