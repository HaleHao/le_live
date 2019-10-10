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
}
