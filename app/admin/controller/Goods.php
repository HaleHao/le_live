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

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Goods extends Permissions
{

    /**
     * 商品订单列表
     */
    public function goods_order()
    {
        return $this->fetch();
    }
    public function goods_order_all(){
        input('status')>0?$where['m.order_status']=input('status')-1:$where='';
        $info = Db::name('goods_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('m.goods_name','like',"%".input('goods_name')."%")
            ->join('le_users u','u.id=m.user_id')
            ->field('m.*,u.nickname')
            ->order('id desc')
            ->select();
        foreach ($info as $k=>$v){
            $v['order_status']==0&&$info[$k]['order_status']='待支付';
            $v['order_status']==1&&$info[$k]['order_status']='待发货';
            $v['order_status']==2&&$info[$k]['order_status']='待收货';
            $v['order_status']==3&&$info[$k]['order_status']='已完成';
            $v['order_status']==4&&$info[$k]['order_status']='已退款';
            $v['pay_type']==1?$info[$k]['pay_type']='微信':$info[$k]['pay_type']='余额';
            $info[$k]['submit_time'] = date('Y-m-d H:i:s',$v['submit_time']);
        }
        $count = Db::name('goods_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->join('le_users u','u.id=m.user_id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 订单详情
     */
    public function goods_order_detail(){
        $obj = Db::name('goods_order')->alias('o')->where('o.id',input('id'))
            ->join('le_users u','u.id=o.user_id')
            ->field('o.*,u.nickname as unickname')
            ->find();
        $obj['cover_image']&&$obj['cover_image']=GetConfig('img_prefix').$obj['cover_image'];
        $obj['spec_image']&&$obj['spec_image']=GetConfig('img_prefix').$obj['spec_image'];
        $this->assign('info',$obj);
        return $this->fetch();
    }

}
