<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/10/25
 * Time: 11:37
 */

namespace App\Service;


use Illuminate\Support\Facades\Log;

class WeChatPayService
{
    //统一下单接口
    protected $unifiedorderUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    protected $refundUrl = 'https://api.mch.weixin.qq.com/secapi/pay/refund';


    protected $refundNotifyUrl = 'http://api.zerobike.cn/api/pay/refund/notify';

    protected $key = 'poiuytgnbvcdxsfrewfbglkiujm12368';

    protected $appid = 'wxdadaa1493ebcd7bf';

    protected $mch_id = '1536924251';

    protected $SSLCERT_PATH = 'Service/Cert/apiclient_cert.pem';

    protected $SSLKEY_PATH = 'Service/Cert/apiclient_key.pem';

    protected $transfers = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';


    public function __construct()
    {
        $this->appid = config('appid') ? config('appid') : $this->appid;
        $this->mch_id = config('mch_id') ? config('mch_id') : $this->mch_id;
        $this->key = config('key') ? config('key') : $this->key;
    }

    //小程序下单
    public function ordering($out_trade_no, $total_fee, $spbill_create_ip, $product_id, $openid, $notifyUrl, $body)
    {
        $paydata = [
            'appid' => $this->appid,
            'mch_id' => $this->mch_id,
            'device_info' => '小程序',
            'nonce_str' => $this->nonce_str(),
            'sign_type' => 'MD5',
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'fee_type' => 'CNY',
            'total_fee' => $total_fee * 100,
            'spbill_create_ip' => $spbill_create_ip,
            'notify_url' => $notifyUrl,
            'trade_type' => 'JSAPI',
            'product_id' => $product_id,
            'openid' => $openid
        ];
//        dd($paydata);
        //添加签名
        $paydata['sign'] = $this->getSign($paydata);
        $paydata = $this->arrayToXml($paydata);
        $resultData = $this->postXmlOrJson($this->unifiedorderUrl, $paydata);
        //接收下单结果 返回格式是xml的
        $resultData = $this->XmlToArr($resultData);
        Log::info($resultData);
        // 在resultData 中就有微信服务器返回的prepay_id
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                $reData = [
                    'appId' => $this->appid,
                    'timeStamp' => (string)time(),
                    'nonceStr' => $this->nonce_str(),
                    'signType' => 'MD5',
                    'package' => 'prepay_id=' . $resultData['prepay_id']
                ];
                $reData['paySign'] = $this->getSign($reData);
                return $reData;
            }
        }
        return false;
    }

    //生成随机字符串
    protected function nonce_str()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()+-';
        $random = $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)];
        $content = uniqid() . $random;
        return md5(sha1($content));
    }

    //生成签名
    protected function getSign($data)
    {
        //去除数组空键值
        $data = array_filter($data);
        //如果数组中有签名删除签名
        if (isset($data['sing'])) {
            unset($data['sing']);
        }
        //按照键名字典排序
        ksort($data);

        $str = http_build_query($data) . "&key=" . $this->key;

        //转码
        $str = $this->arrToUrl($str);

        return strtoupper(md5($str));
    }

    //URL解码为中文
    public function arrToUrl($str)
    {
        return urldecode($str);
    }

    //转换xml
    public function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml = $xml . '<' . $key . '>' . $this->arrayToXml($val) . '</' . $key . '>';
            } else {
                $xml = $xml . '<' . $key . '>' . $val . '</' . $key . '>';
            }

        }
        $xml .= '</xml>';
        return $xml;
    }

    //Xml转数组
    public function XmlToArr($xml)
    {
        if ($xml == '') return '';
        libxml_disable_entity_loader(true);
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $arr;
    }

    //提交XML方法
    protected function postXmlOrJson($url, $data)
    {
        //$data = 'XML或者JSON等字符串';
        $ch = curl_init();
        $params[CURLOPT_URL] = $url;    //请求url地址
        $params[CURLOPT_HEADER] = false; //是否返回响应头信息
        $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
        $params[CURLOPT_FOLLOWLOCATION] = true; //是否重定向
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $data;

        //防止curl请求 https站点报错 禁用证书验证
        $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;


        //curl_setopt($ch, CURLOPT_SSLCERT,app_path('/Cert/apiclient_cert.pem'));
        curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
        return $content;
    }

    //需要使用证书的请求
    function postXmlSSLCurl($url, $xml, $second = 30)
    {
        Log::info('证书路径' . app_path($this->SSLCERT_PATH));
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置证书
        //使用证书：cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, app_path($this->SSLCERT_PATH));
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, app_path($this->SSLKEY_PATH));
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            Log::info('curl出错，错误码' . $error);
            curl_close($ch);
            return false;
        }
    }


}