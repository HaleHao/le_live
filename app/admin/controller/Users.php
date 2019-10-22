<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------


namespace app\admin\controller;

use \think\Cache;
use \think\Controller;
use think\Loader;
use think\Db;
use \think\Cookie;
use app\admin\controller\Permissions;
use app\admin\model\AdminMenu as menuModel;
class Users extends Permissions
{
    /**
     * 用户列表
     */
    public function index()
    {
        return $this->fetch();
    }
    public function indexAll(){
        $info = Db::name('users')->where('nickname','like',"%".input('keywords')."%")
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $info[$k]['avatar'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['avatar']);
            $info[$k]['promote_qrcode'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['promote_qrcode']);
            $info[$k]['share_qrcode'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['share_qrcode']);
            $v['is_enter']==1?$info[$k]['is_enter']='是':$info[$k]['is_enter']='否';
            $v['is_auth']==1?$info[$k]['is_auth']='是':$info[$k]['is_auth']='否';
            $v['is_partner']==1?$info[$k]['is_partner']='是':$info[$k]['is_partner']='否';
        }
        $count = Db::name('users')->where('nickname','like',"%".input('keywords')."%")->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 微厨认证
     */
    public function certification()
    {
        return $this->fetch();
    }
    public function certificationAll(){
        $info = Db::name('users')->where('is_auth',0)->where('nickname','like',"%".input('keywords')."%")
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $info[$k]['avatar'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['avatar']);
            $info[$k]['card_front'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['card_front']);
            $info[$k]['card_back'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['card_back']);
        }
        $count = Db::name('users')->where('is_auth',0)->where('nickname','like',"%".input('keywords')."%")->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 微厨认证
     *  is_auth=1确认认证is_auth=2拒绝认证
     */
    public function cert()
    {
        $obj = Db::name('users')->where('id',input('id'))->update(['is_auth'=>input('is_auth')]);
        if ($obj) {
            return $data=[
                'code' => 1,
                'msg'  => "操作成功",
            ];
        } else {
            return $data=[
                'code' => 0,
                'msg'  => "操作失败",
            ];
        }
    }

    /**
     * 编辑事业部状态
     */
    public function edit_is_bu(){
        Db::name('users')->where('id',input('id'))->update(['is_bu'=>input('switch')]);
    }


}
