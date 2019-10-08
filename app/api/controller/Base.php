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
}
