<?php
namespace app\api\controller;

use app\admin\model\EarningsLog;
use app\admin\model\Users;
use app\admin\model\WalletLog;
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
        if ($code) {
            $wechat = new WeChatService();
            $info = $wechat->authorization($code);

            if (isset($info['openid'])) {
                $openid = $info['openid'];

                $nickname = $request->param('nickname');

                $avatar = $request->param('avatar');

                $gender = $request->param('gender');

                $first_user_id = $request->param('first_user_id');

                $red_packet_id = $request->param('red_packet_id');

                $time = time();

                $user = Users::where('openid', $openid)->find();
                if (!$user) {
                    $user = new Users();
                    $user->openid = $openid;

                    $user->signature = '这个人很懒, 啥也没写';
                    if ($first_user_id) {
                        $user->first_user_id = $first_user_id;
                        $second_user = Users::where('id', $first_user_id)->find();
                        if ($second_user) {
                            $second_user_id = $second_user->first_user_id;
                            $user->second_user_id = $second_user_id;
//                            $user->
                        }
                    }

                    //分享奖励
                    if ($red_packet_id){

                        $red_user = Users::where('id',$red_packet_id)->find();

                        if ($red_user){
                            $log = new WalletLog();
                            $log->user_id = $red_user->id;
                            $log->content = '['.$nickname.']红包奖励';
                            $log->money = GetConfig('red_packet_money',10);
                            $log->type = 1;
                            $log->save();
                            $red_user->setInc('balance',GetConfig('red_packet_money',10));
                        }

                    }



                }
                if (!$user->signature) {
                    $user->signature = '这个人很懒, 啥也没写';
                }
                $user->last_time = $time;
                $user->last_ip = $_SERVER['REMOTE_ADDR'];
                $user->nickname = $nickname;
                $user->avatar = $avatar;
                $user->gender = $gender;

                if ($user->save()) {
//                    $id = $user->getLastInsID();
                    //生成随机token
//                    var_dump($user->id);exit;
//                    $user->promote_qrcode = new WeChatQrService('pages/distribution/protocol/protocol',$user->id);
//                    $user->save();
//                    var_dump(__PUBLIC__);
                    //推广二维码
                    if (!$user->promote_qrcode) {
                        $res = new WeChatQrService('pages/distribution/protocol/protocol', $user->id);
                        $user->promote_qrcode = $res->url;
                        $user->save();
                    }
                    //分享二维码
                    if (!$user->share_qrcode) {
                        $res = new WeChatQrService('pages/index/index', $user->id);
                        $user->share_qrcode = $res->url;
                        $url = $this->createPoster($res->url);
                        $user->red_poster = $url;
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

    //生成海报
    public function createPoster($qrcode)
    {
        $dst_path = ROOT_PATH.'public/uploads/qrcode/background.jpg';//背景图片路径
        $src_path = ROOT_PATH.'public'.$qrcode;//覆盖图
        //创建图片的实例
        $image = \think\Image::open($src_path);
        // 按照原图的比例生成一个最大为200*200的缩略图并替换原来的图片(保存在原来的路径,文件名相同会被替换)
        $image->thumb(260, 260)->save($src_path);
//        exit;
        $dst = imagecreatefromstring(file_get_contents($dst_path));
        $src = imagecreatefromstring(file_get_contents($src_path));
//获取覆盖图图片的宽高
        list($src_w, $src_h) = getimagesize($src_path);
//将覆盖图复制到目标图片上，最后个参数100是设置透明度（100是不透明），这里实现不透明效果
        imagecopymerge($dst, $src, 248, 854, 0, 0, $src_w, $src_h, 100);
        header("Content-type: image/png");

        $str = date('YmdHis') . md5(time()+rand(1000000,9999999)) . '.jpg';
        $filename = 'public/uploads/qrcode/' .$str;
        $url = '/uploads/qrcode/' .$str;

        imagepng($dst,ROOT_PATH.$filename);//根据需要生成相应的图片
//    imagejpeg($dst,'../uploads/user/'.$uid.'.jpg');
        imagedestroy($dst);
        imagedestroy($src);
        return $url;
    }


}
