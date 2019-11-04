<?php
/**
 * Created by PhpStorm.
 * User: 何浩
 * Date: 2018/10/25
 * Time: 11:37
 */

namespace app\api\service;



class WeChatPayService
{
    //统一下单接口
    protected $unifiedorderUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    //退款接口
    protected $refundUrl = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
    //提现接口哦
    protected $transfers = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
    //商户密钥
    protected $key = 'ar329tfwhpqhngpw3ptw9yr2tjwepfts';
    //微信Appid
    protected $app_id = 'wx3b3bef28e20cd3f2';
    //微信商户号
    protected $mch_id = '1555289501';
    //证书路径(Ps：主要用户退款，提现)
    protected $SSLCERT_PATH = 'service/cert/apiclient_cert.pem';
    //证书路径
    protected $SSLKEY_PATH = 'service/cert/apiclient_key.pem';


    public function __construct()
    {
        $this->app_id = GetConfig('wx_app_id') ? GetConfig('wx_app_id') : $this->app_id;
        $this->mch_id = GetConfig('mch_id') ? GetConfig('mch_id') : $this->mch_id;
        $this->key = GetConfig('mch_key') ? GetConfig('mch_key') : $this->key;
//        $this->SSLCERT_PATH = config('apiclient_cert') ? config('apiclient_cert') : $this->key;
//        $this->SSLKEY_PATH = config('apiclient_key') ? config('apiclient_key') : $this->key;

    }

    /**
     * @param $out_trade_no 订单编号
     * @param $total_fee 支付金额
     * @param $openid 用户OpenId
     * @param $notifyUrl 回调地址
     * @param $body 支付内容
     * @return array|bool
     * Date: 2019/5/31 0031
     * 小程序支付
     */
    public function Mini_Pay($out_trade_no, $total_fee,  $openid, $notifyUrl, $body)
    {
        $paydata = [
            'appid' => $this->app_id,
            'mch_id' => $this->mch_id,
            'device_info' => '小程序',
            'nonce_str' => $this->nonce_str(),
            'sign_type' => 'MD5',
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'fee_type' => 'CNY',
            'total_fee' => intval($total_fee * 100),
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $notifyUrl,
            'trade_type' => 'JSAPI',
            'openid' => $openid
        ];
        $paydata['sign'] = $this->getSign($paydata);
        $paydata = $this->arrayToXml($paydata);
        $resultData = $this->postXmlOrJson($this->unifiedorderUrl, $paydata);
        //接收下单结果 返回格式是xml的
        $resultData = $this->XmlToArr($resultData);
        // 在resultData 中就有微信服务器返回的prepay_id
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                $reData = [
                    'appId' => $this->app_id,
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

    /**
     * @param $out_trade_no 订单编号
     * @param $total_fee 订单金额
     * @param $openid 用户OPENID
     * @param $notifyUrl 回调地址
     * @param $body 支付内容
     * @return array|bool
     * Date: 2019/5/31 0031
     * 公众号下单
     */
    public function WX_Pay($out_trade_no, $total_fee, $openid, $notifyUrl, $body)
    {
        $paydata = [
            'appid' => $this->app_id,
            'mch_id' => $this->mch_id,
            'device_info' => '公众号',
            'nonce_str' => $this->nonce_str(),
            'sign_type' => 'MD5',
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'fee_type' => 'CNY',
            'total_fee' => intval($total_fee * 100),
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $notifyUrl,
            'trade_type' => 'JSAPI',
            'openid' => $openid
        ];
//        dd($paydata);
        //添加签名
        $paydata['sign'] = $this->getSign($paydata);
        $paydata = $this->arrayToXml($paydata);
        $resultData = $this->postXmlOrJson($this->unifiedorderUrl, $paydata);
        //接收下单结果 返回格式是xml的
//        Log::info($resultData);
        $resultData = $this->XmlToArr($resultData);
        // 在resultData 中就有微信服务器返回的prepay_id
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                $reData = [
                    'appId' => $this->app_id,
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

    /**
     * @param $out_trade_no 订单编号唯一的
     * @param $total_fee 订单金额
     * @param $notifyUrl 回调地址
     * @param $body 支付内容
     * @return array|bool 返回一条url，转成二维码就可以支付了
     * Date: 2019/5/31 0031
     * 扫码支付
     */
    public function Native_Pay($out_trade_no, $total_fee, $notifyUrl,$body)
    {
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $paydata = array(
            'appid' => $this->app_id,
            'attach' => 'pay',             //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
            'body' => $body,
            'mch_id' => $this->mch_id,
            'nonce_str' => $this->nonce_str(),
            'notify_url' => $notifyUrl,
            'out_trade_no' => $out_trade_no,
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'total_fee' => intval($total_fee * 100),       //单位 转为分
            'trade_type' => 'NATIVE',
        );
        $paydata['sign'] = $this->getSign($paydata);
        $responseXml = $this->postXmlOrJson($this->unifiedorderUrl, $this->arrayToXml($paydata));
        $resultData = $this->XmlToArr($responseXml);

        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                $reData = [
                    'appId' => $this->app_id,
                    'timeStamp' => (string)time(),
                    'nonceStr' => $this->nonce_str(),
                    'signType' => 'MD5',
                    'package' => 'prepay_id=' . $resultData['prepay_id'],
                    "code_url" => $resultData['code_url'][0],
                ];
                $reData['paySign'] = $this->getSign($reData);
                return $reData;
            }
        }
        return false;
    }

    /**
     * @param $out_trade_no 订单编号
     * @param $total_fee 订单价格
     * @param $notifyUrl 回调地址
     * @param $body 支付内容
     * @param $web_url 网站链接
     * @param $redirect_url 跳转链接
     * @return bool|string
     * Date: 2019/5/31 0031
     * H5微信支付
     */
    public function H5_Pay($out_trade_no, $total_fee,$notifyUrl, $body,$web_url,$redirect_url)
    {
        $paydata = array(
            'appid' => $this->app_id,
            'mch_id' => $this->mch_id,
            'nonce_str' => $this->nonce_str(),
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'total_fee' => intval($total_fee * 100),     //单位 转为分
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => $notifyUrl,
            'trade_type' => 'MWEB',
            'scene_info' => '{"h5_info": {"type":"Wap","wap_url": "'.$web_url.'","wap_name": "h5pay"}}',
        );
        $paydata['sign'] = $this->getSign($paydata);
        $responseXml = $this->postXmlOrJson($this->unifiedorderUrl, $this->arrayToXml($paydata));
        $resultData = $this->XmlToArr($responseXml);
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                $url_encode_redirect_url = urlencode($redirect_url);
                $url = $resultData['mweb_url'] . '&redirect_url=' . $url_encode_redirect_url;
                return $url;
            }
        }
        return false;
    }


    /**
     * @param $transaction_id 微信支付流水号
     * @param $out_refund_no 商家订单号
     * @param $total_fee 支付金额
     * @param $refund_desc 退款说明
     * @param string $notifyUrl 回调地址
     * @return array|bool
     * Date: 2019/5/31 0031
     * 退款（Ps:退款可以用商家自己生成的地址，也可以用微信支付的流水号）
     */
    public function Refund($out_trade_no, $total_fee,$refund_desc)
    {
        $refund = [
            'appid' => $this->app_id,
            'mch_id' => $this->mch_id,
            'nonce_str' => $this->nonce_str(),
            'sign_type' => 'MD5',
            'refund_desc' => $refund_desc,//退款说明
            'out_trade_no' => $out_trade_no,//商家订单号
            'out_refund_no' => $out_trade_no,//商家订单号
            'total_fee' => intval($total_fee * 100),
            'refund_fee' => intval($total_fee * 100),
//            'notify_url' => $notifyUrl//回调地址
        ];

        //添加签名
        $refund['sign'] = $this->getSign($refund);
        $refund = $this->arrayToXml($refund);
        $resultData = $this->postXmlSSLCurl($this->refundUrl, $refund);
        $resultData = $this->XmlToArr($resultData);
//        var_dump($resultData);exit;
        if ($resultData){
            if ($resultData['return_code'] == 'SUCCESS') {
                if ($resultData['result_code'] == 'SUCCESS') {
                    $postData = [
                        'transaction_id' => $resultData['transaction_id'],
                        ];
                    return $postData;
                }
            }
        }
        return false;
    }


    /**
     * @param $partner_trade_no 商家商户号
     * @param $openid 用户OpenId
     * @param $amount 提现金额
     * @param $desc 提现描述
     * @return bool
     * Date: 2019/5/31 0031
     * 用户提现到零钱
     */
    public function transfers($partner_trade_no, $openid, $total_fee, $desc)
    {
        $transfers = [
            'mch_appid' => $this->app_id,
            'mchid' => $this->mch_id,
            'nonce_str' => $this->nonce_str(),
            'sign_type' => 'MD5',
            'partner_trade_no' => $partner_trade_no,
            'openid' => $openid,
            'check_name' => 'NO_CHECK',
            'amount' => intval($total_fee * 100),
            'desc' => $desc,
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR']
        ];
        //添加签名
        $transfers['sign'] = $this->getSign($transfers);
        $transfers = $this->arrayToXml($transfers);
        $resultData = $this->postXmlSSLCurl($this->transfers, $transfers);
        $resultData = $this->XmlToArr($resultData);
        if ($resultData['return_code'] == 'SUCCESS') {
            if ($resultData['result_code'] == 'SUCCESS') {
                return true;
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
        curl_setopt($ch, CURLOPT_SSLCERT, ROOT_PATH.'app/api/service/cert/apiclient_cert.pem');
        //默认格式为PEM，可以注释
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, ROOT_PATH.'app/api/service/cert/apiclient_key.pem');
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

            curl_close($ch);
            return false;
        }
    }


}