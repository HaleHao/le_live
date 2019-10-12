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
class Menus extends Permissions
{
    /**
     * 菜单列表
     */
    public function index()
    {
        return $this->fetch();
    }
    public function indexAll(){
        $info = Db::name('menus')->alias('m')->where('m.title','like',"%".input('keywords')."%")
            ->where('u.nickname','like',"%".input('cname')."%")
            ->where('c.title','like',"%".input('column')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_column c','c.id=m.column_id')
            ->field('m.*,u.nickname,c.title as column_name')
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $v['is_reserve']==0?$info[$k]['is_reserve']='否':$info[$k]['is_reserve']='是';
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
            $info[$k]['cover_image'] = str_replace('/uploads',GetConfig('img_prefix').'/uploads',$v['cover_image']);
        }
        $count = Db::name('menus')->alias('m')->where('m.title','like',"%".input('keywords')."%")
            ->where('u.nickname','like',"%".input('cname')."%")
            ->where('c.title','like',"%".input('column')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_column c','c.id=m.column_id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 菜单评论列表
     */
    public function menus_comment(){
        $this->assign('menu_id',input('menu_id'));
        return $this->fetch();
    }
    public function menus_commentall(){
        $info = Db::name('menus_comment')->alias('m')
            ->where('m.menu_id',input('menu_id'))
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('uu.nickname','like',"%".input('uunickname')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_users uu','uu.id=m.to_user_id')
            ->field('m.*,u.nickname as unickname,uu.nickname as uunickname')
            ->order('create_time desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $info[$k]['title'] = Db::name('menus')->where('id',$v['menu_id'])->value('title');
            $v['type']==1?$info[$k]['type']='普通评价':$info[$k]['type']='餐后评价';
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
            $images = json_decode($v['images'],true);
            if(!empty($images)){
                foreach ($images as $ko=>$vo){
                    $images[$ko] = GetConfig('img_prefix').$vo;
                }
            }
            $info[$k]['images'] = array();
            $info[$k]['images'] = $images;
        }
        $count = Db::name('menus_comment')->alias('m')
            ->where('m.menu_id',input('menu_id'))
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('uu.nickname','like',"%".input('uunickname')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_users uu','uu.id=m.to_user_id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 删除菜单评论
     */
    public function del_menus_comments()
    {
        $id = input('id');
        $parent_id = Db::name('menus_comment')->where('id',$id)->value('parent_id');
        if($parent_id == 0){
            $ret = Db::name('menus_comment')->where('id',$id)->delete();
            if(!$ret){
                return $data=[
                    'code' => 0,
                    'msg'  => "操作失败",
                ];
            }
            $obj = $this->del_for_comments($id);
        }else{
            $obj = Db::name('menus_comment')->where('id',input('id'))->delete();
        }
        if($obj){
            return $data=[
                'code' => 1,
                'msg'  => "操作成功",
            ];
        }else{
            return $data=[
                'code' => 0,
                'msg'  => "操作失败",
            ];
        }
    }

    /**
     * 循环删除菜单评论
     */
    public function del_for_comments($id){
        $arr = Db::name('menus_comment')->where('parent_id',$id)->select();
        if(!empty($arr)){
            foreach ($arr as $k=>$v){
                $obj = Db::name('menus_comment')->where('id',$v['id'])->delete();
                if(!$obj){
                    return false;
                }
                $this->del_for_comments($v['id']);
            }
        }
        return true;
    }

    /**
     * 菜单订单列表
     */
    public function menus_order()
    {
        return $this->fetch();
    }
    public function menus_order_all(){
        input('status')>0?$where['m.order_status']=input('status')-1:$where='';
        $info = Db::name('menus_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('uu.nickname','like',"%".input('uunickname')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_users uu','uu.id=m.chef_id')
            ->field('m.*,u.nickname as unickname,uu.nickname as uunickname')
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($info as $k=>$v){
            $v['order_status']==0&&$info[$k]['order_status']='待支付';
            $v['order_status']==1&&$info[$k]['order_status']='待发货';
            $v['order_status']==2&&$info[$k]['order_status']='待收货';
            $v['order_status']==3&&$info[$k]['order_status']='待自提';
            $v['order_status']==4&&$info[$k]['order_status']='已完成';
            $v['order_status']==5&&$info[$k]['order_status']='退款';
            $v['pay_type']==1?$info[$k]['pay_type']='微信':$info[$k]['pay_type']='其他';
            $v['delivery_type']==1?$info[$k]['delivery_type']='UU配送':$info[$k]['delivery_type']='自提';
            $info[$k]['submit_time'] = date('Y-m-d H:i:s',$v['submit_time']);
        }
        $count = Db::name('menus_order')->alias('m')->where($where)->where('m.order_no','like',"%".input('order_no')."%")
            ->where('u.nickname','like',"%".input('unickname')."%")
            ->where('uu.nickname','like',"%".input('uunickname')."%")
            ->join('le_users u','u.id=m.user_id')
            ->join('le_users uu','uu.id=m.chef_id')->count();
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
    public function menus_order_detail(){
        $obj = Db::name('menus_order')->alias('o')->where('o.id',input('id'))
            ->join('le_users u','u.id=o.user_id')
            ->join('le_users uu','uu.id=o.chef_id')
            ->field('o.*,u.nickname as unickname,uu.nickname as uunickname')
            ->find();
        $send_address = json_decode($obj['send_address'],true);
        !empty($send_address)&&$obj['send_address'] = $send_address['province'].$send_address['city'].$send_address['district'].$send_address['detail'];
        $reci_address = json_decode($obj['reci_address'],true);
        !empty($reci_address)&&$obj['reci_address'] = $reci_address['province'].$reci_address['city'].$reci_address['district'].$reci_address['detail'];
        $objArr = Db::name('menus_order_detail')->alias('d')->where('d.order_id',input('id'))->select();
        foreach ($objArr as $k=>$v){
            $objArr[$k]['cover_image'] = GetConfig('img_prefix').$v['cover_image'];
            $v['is_comment']==1?$objArr[$k]['is_comment']='已评价':$objArr[$k]['is_comment']='未评价';
        }
        $this->assign('info',$obj);
        $this->assign('goods',$objArr);
        return $this->fetch();
    }

}
