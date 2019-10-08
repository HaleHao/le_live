<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/7/25
 * Time: 10:53
 */

namespace app\api\service;



class WeChatService
{
    protected $authorizationUrl = 'https://api.weixin.qq.com/sns/jscode2session';

    protected $appid = 'wxdadaa1493ebcd7bf';

    protected $secret = 'fefd4e42938ff57c036e282655a237de';

    protected $template_id = '6TsQjY3xxibwOp0R7iMNuN6UpaGMG6v8ro56rmAf3HU';

    public function __construct()
    {
        $this->appid = GetConfig('wx_app_id') ? GetConfig('wx_app_id') : $this->appid;
        $this->secret = GetConfig('wx_secret') ? GetConfig('wx_secret') : $this->secret;
    }

    public function authorization($code)
    {
        $url = 'https://api.weixin.qq.com/sns/jscode2session' . '?appid=' . $this->appid . '&secret=' . $this->secret . '&js_code=' . $code . '&grant_type=authorization_code';
        $res = json_decode(file_get_contents($url), true);
        return $res;
    }

    //获取access_token
    protected function getAccessToken()
    {
        $url_access_token = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->secret;
        $json_access_token = $this->HttpClient($url_access_token);
        $arr_access_token = json_decode($json_access_token, true);
        $access_token = $arr_access_token['access_token'];
        return $access_token;
    }

    //发送消息模板
    public function sendMessage($openid, $formid, $data)
    {
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $access_token;  //此处变量插入字符串不能使用{}！！！
        Log::info($openid);
        $data = '{
		  "touser":"' . $openid . '",
		  "template_id":"' . $this->template_id . '",
		  "form_id":"' . $formid . '",   
		  "data": {
			  "keyword1": {
				  "value":"' . $data[0] . '"
			  }, 
			  "keyword2": {
				  "value":"' . $data[1] . '" 
			  }, 
			  "keyword3": {
				  "value":"' . $data[2] . '"
			  } , 
			  "keyword4": {
				  "value":"' . $data[3] . '" 
			  },
			  "keyword5": {
				  "value":"' . $data[4] . '" 
			  }
		  }
		}';
        $result = $this->HttpClient($url, $data, 'POST');

        $result = json_decode($result);
        if ($result->errcode == 0) {
            return true;
        } else {
            return false;
        }
    }

    protected function HttpClient($url, $data = [], $type = 'get')
    {
//        if ($type == 'get') {
//            $client = new Client();
//            $response = $client->get($url, ['verify' => false]);
//            if ($response->getStatusCode() == 200) {
//                $data = $response->getBody()->getContents();
//                return $data;
//            } else {
//                return false;
//            }
//        } else {
            $curl = curl_init(); // 启动一个CURL会话
            curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检测
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:')); //解决数据包大不能提交
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
            curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
            curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循
            curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回

            $tmpInfo = curl_exec($curl); // 执行操作
            if (curl_errno($curl)) {
                echo 'Errno' . curl_error($curl);
            }
            curl_close($curl); // 关键CURL会话
            return $tmpInfo; // 返回数据
//        }
    }
}