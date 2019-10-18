<?php
namespace app\api\controller;

use app\admin\model\Users;
use app\api\service\WeChatQrService;
use app\api\service\WeChatService;
use think\Cache;
use think\Request;

class Wechat extends Base
{

    /**
     * 微信授权登录
     */
    public function login(Request $request)
    {
        $code = $request->param('code');
        if ($code){
            $wechat = new WeChatService();
            $info = $wechat->authorization($code);

            if (isset($info['openid'])){
                $openid = $info['openid'];

                $nickname = $request->param('nickname');

                $avatar = $request->param('avatar');

                $gender = $request->param('gender');

                $first_user_id = $request->param('first_user_id');

                $time = time();

                $user = Users::where('openid',$openid)->find();
                if (!$user){
                    $user = new Users();
                    $user->openid = $openid;

                    $user->signature = '这个人很懒, 啥也没写';
                    if ($first_user_id){
                        $user->first_user_id = $first_user_id;
                        $second_user = Users::where('id',$first_user_id)->find();
                        if ($second_user){
                            $second_user_id = $second_user->first_user_id;
                            $user->second_user_id = $second_user_id;
//                            $user->
                        }
                    }

                }
                if (!$user->signature){
                    $user->signature = '这个人很懒, 啥也没写';
                }
                $user->last_time = $time;
                $user->last_ip = $_SERVER['REMOTE_ADDR'];
                $user->nickname = $nickname;
                $user->avatar = $avatar;
                $user->gender = $gender;

                if ($user->save()){
//                    $id = $user->getLastInsID();
                    //生成随机token
//                    var_dump($user->id);exit;
//                    $user->promote_qrcode = new WeChatQrService('pages/distribution/protocol/protocol',$user->id);
//                    $user->save();
//                    var_dump(__PUBLIC__);
                    //推广二维码
                    if (!$user->promote_qrcode){
                        $res = new WeChatQrService('pages/distribution/protocol/protocol',$user->id);
                        $user->promote_qrcode = $res->url;
                        $user->save();
                    }
                    //分享二维码
                    if(!$user->share_qrcode){
                        $res = new WeChatQrService('pages/index/index',$user->id);
                        $user->share_qrcode = $res->url;
                        $user->save();
                    }
                    $token = $this->saveCache($user->id);
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

    /**
     * 保存缓存
     */
    public function saveCache($user_id)
    {
        $token = $this->getToken();
        Cache::set($token, $user_id);
        return $token;
    }

    public function saveUser(Request $request)
    {
        $user_id = $request->param('user_id');
        $token = $this->getToken();
        Cache::set($token, $user_id);
        return $token;
    }

    /**
     * 获取TOKEN
     */
    protected function getToken()
    {
        $str = md5(uniqid(md5(microtime(true)), true));  //生成一个不会重复的字符串
        $str = sha1($str);
        return $str;
    }
}
