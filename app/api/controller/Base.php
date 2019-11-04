<?php
namespace app\api\controller;

use think\Cache;
use think\Controller;

class Base extends Controller
{

    public $user_id;
    /**
     * 判断用户是否授权登录
     * Date: 2019/9/16 0016
     */
    public function _initialize()
    {
        $access_token = request()->header('Access-Token');
        $user_id = Cache::get($access_token,0);
        $this->user_id = $user_id;
    }

    /*
   * 地址逆解析
   * @param float $lng
   * @param float $lat
   * return array
   * */
    public function location($lat,$lng){
        // 获取key
        $key = 'LHEBZ-BJG63-GPO3M-3UVRZ-AKELZ-KPFN7';// 'AQ2BZ-DXLLQ-DY75H-GWUAX-Q5QG5-FZBRQ';
        /*
         * 返回格式设置
         * json示例：
         * http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location=39.983424,116.322987&output=json&pois=1&ak=您的ak
         * xml示例：
         * http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location=39.983424,116.322987&output=xml&pois=1&ak=您的ak
         * */
        $url = "https://apis.map.qq.com/ws/geocoder/v1/?location=" . $lat . "," . $lng . "&key=" . $key . "&get_poi=1";
        $html = file_get_contents($url);
        $result = json_decode($html,true);
        if(isset($result['result']) && isset($result['result']['address_component'])){
            return $result['result']['address_component'];
        }
        return false;

    }


    /**
     * 生成激活码
     * @param int $length
     * @param int $type
     * @return bool|string
     * Date: 2019/10/11 0011
     */
    public function random($length = 6, $type = 1)
    {
        // 取字符集数组
        $number = range(0, 9);
        $lowerLetter = range('a', 'z');
        $upperLetter = range('A', 'Z');
        // 根据type合并字符集
        if ($type == 1) {
            $charset = $number;
        } elseif ($type == 2) {
            $charset = $lowerLetter;
        } elseif ($type == 3) {
            $charset = $upperLetter;
        } elseif ($type == 4) {
            $charset = array_merge($number, $lowerLetter);
        } elseif ($type == 5) {
            $charset = array_merge($number, $upperLetter);
        } elseif ($type == 6) {
            $charset = array_merge($lowerLetter, $upperLetter);
        } elseif ($type == 7) {
            $charset = array_merge($number, $lowerLetter, $upperLetter);
        } else {
            $charset = $number;
        }
        $str = '';
        // 生成字符串
        for ($i = 0; $i < $length; $i++) {
            $str .= $charset[mt_rand(0, count($charset) - 1)];
            // 验证规则
            if ($type == 4 && strlen($str) >= 2) {
                if (!preg_match('/\d+/', $str) || !preg_match('/[a-z]+/', $str)) {
                    $str = substr($str, 0, -1);
                    $i = $i - 1;
                }
            }
            if ($type == 5 && strlen($str) >= 2) {
                if (!preg_match('/\d+/', $str) || !preg_match('/[A-Z]+/', $str)) {
                    $str = substr($str, 0, -1);
                    $i = $i - 1;
                }
            }
            if ($type == 6 && strlen($str) >= 2) {
                if (!preg_match('/[a-z]+/', $str) || !preg_match('/[A-Z]+/', $str)) {
                    $str = substr($str, 0, -1);
                    $i = $i - 1;
                }
            }
            if ($type == 7 && strlen($str) >= 3) {
                if (!preg_match('/\d+/', $str) || !preg_match('/[a-z]+/', $str) || !preg_match('/[A-Z]+/', $str)) {
                    $str = substr($str, 0, -2);
                    $i = $i - 2;
                }
            }
        }
        return $str;
    }


    /**
     * 去除emoji表情
     */
    public function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return $str;
    }


    /**
     * 菜品价格计算
     */
    public function menus_price($price){
        return $price + ($price * (GetConfig('menus_ratio',15)/100)) + GetConfig('box_price',1);
    }
}
