<?php
namespace app\api\controller;

use app\admin\model\Users;
use app\api\service\WeChatService;
use think\Cache;
use think\Request;

class Wechat extends Base
{

    /**
     * 微信授权登录
     * Date: 2019/9/16 0016
     */
    public function login(Request $request)
    {
        $code = $request->param('code');
        if ($code){
            $wechat = new WeChatService();
            $info = $wechat->authorization($code);
            if ($info){
                $openid = $info['openid'];

                $nickname = $request->param('nickname');

                $avatar = $request->param('avatar');

                $gender = $request->param('gender');

                $time = time();

                $user = Users::where('openid',$openid)->find();
                if (!$user){
                    $user = new Users();
                    $user->openid = $openid;
                }
                $user->last_time = $time;
                $user->last_ip = $_SERVER['REMOTE_ADDR'];
                $user->nickname = $nickname;
                $user->avatar = $avatar;
                $user->gender = $gender;

                if ($user->save()){
                    //生成随机token
                    $user_id = $user->getLastInsID();

                    $token = $this->saveCache($user_id);
                    $data = [
                        'token' => $token
                    ];
                    return JsonSuccess($data);
                }
                return JsonError('登录失败');
            }
            return JsonError('获取openid失败');
        }
        return JsonError('code获取失败');
    }


    public function saveCache($user_id)
    {
        $token = $this->getToken();
        Cache::set($token, $user_id);
        return $token;
    }

    //获取TOKEN
    protected function getToken()
    {
        $str = md5(uniqid(md5(microtime(true)), true));  //生成一个不会重复的字符串
        $str = sha1($str);
        return $str;
    }
}
