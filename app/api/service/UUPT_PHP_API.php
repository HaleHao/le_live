<?php

// UU跑腿API PHP DEMO

/**
 * 发起http post请求
 */
function request_post($url = '', $post_data = array()) {
    if (empty($url) || empty($post_data)) {
        return false;
    }
    
    $arr = [];
    foreach ($post_data as $key => $value) {
      $arr[] = $key.'='.$value;
    }

    $curlPost = implode('&', $arr);

    var_dump($arr);
    echo '<br />';
    var_dump('签名后:'.$curlPost);

    $postUrl = $url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$postUrl);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
    $data = curl_exec($ch);
    curl_close($ch);
    
    return $data;
}

// 生成guid
function guid(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);                
        return $uuid;
    }
}

// 生成签名
function sign($data, $appKey) {
  $arr = [];
  foreach ($data as $key => $value) {
    $arr[] = $key.'='.$value;
  }

  $arr[] = 'key='.$appKey;
  $str = strtoupper(implode('&', $arr));
  //$str = http_build_query($arr, '&');
  var_dump('签名前:'.$str);
  echo "<br />";
  return strtoupper(md5($str));
}

// 
// 示例
//

header("Content-type: text/html; charset=utf-8"); 

$guid = str_replace('-', '', guid());

$appid = 'myappid';
$appKey = 'myappKey';
// 远程地址
$url = "http://openapi.uupaotui.com/v2_0/binduserapply.ashx";

// POST数据
$data = array(
            'appid'       => $appid,
            'nonce_str'   => strtolower($guid),
            'timestamp'   => time(),
            'user_mobile' => '13700000000',
            'user_ip'     => '192.168.1.66'       
          );
ksort($data);
$data['sign'] = sign($data, $appKey);

//var_dump($data);

$res = request_post($url, $data);

echo '<br />';

var_dump($res);
