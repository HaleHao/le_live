<?php

namespace app\admin\controller;

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Fenrun extends Permissions
{

    /**
     * 分销列表
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 主页
     */
    public function index_all(){
        input('create_time')?$where['p.date']=input('create_time'):$where='';
//        input('user_type')?$where['p.user_type']=input('user_type'):$where='';
        $info = Db::name('users_profit')->alias('p')->where($where)->where('u.nickname','like',"%".input('keywords')."%")
            ->join('le_users u','p.user_id=u.id')
            ->field('p.*,u.nickname')
            ->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
            $store = Db::name('store')->where('type',$v['user_type'])->find();
            if ($store){
                $identity = $store['name'];
            }else{
                $identity = '普通用户';
            }
            $info[$k]['identity'] = $identity;
        }
        $count = Db::name('users_profit')->alias('p')->where($where)->where('u.nickname','like',"%".input('keywords')."%")
            ->join('le_users u','p.user_id=u.id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }
}
