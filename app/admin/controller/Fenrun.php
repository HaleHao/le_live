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
    public function index_all(){
        input('create_time')?$where['p.date']=input('create_time'):$where='';
        $info = Db::name('users_profit')->alias('p')->where($where)->where('u.nickname','like',"%".input('keywords')."%")
            ->join('le_users u','p.user_id=u.id')
            ->field('p.*,u.nickname')
            ->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
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
