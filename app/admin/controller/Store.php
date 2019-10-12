<?php

namespace app\admin\controller;

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Store extends Permissions
{

    /**
     * 店铺订单列表
     */
    public function store_order()
    {
        return $this->fetch();
    }
    public function store_order_all(){
        input('status')>0?$where['m.order_status']=input('status')-1:$where='';
        input('store_type')&&$where['m.store_type']=input('store_type');
        $info = Db::name('store_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('m.store_name','like',"%".input('store_name')."%")
            ->join('le_users u','u.id=m.user_id')
            ->field('m.*,u.nickname')
            ->order('id desc')
            ->select();
        foreach ($info as $k=>$v){
            $v['order_status']==0?$info[$k]['order_status']='未支付':$info[$k]['order_status']='已支付';
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $count = Db::name('store_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('m.store_name','like',"%".input('store_name')."%")
            ->join('le_users u','u.id=m.user_id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }
}
