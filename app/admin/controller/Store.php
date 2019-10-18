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
     * 店铺列表
     */
    public function index()
    {
        return $this->fetch();
    }
    public function index_all(){
        $info = Db::name('store')
            ->where('name','like',"%".input('keywords')."%")
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))
            ->select();
        foreach ($info as $k=>$v){
            $v['type']==1?$info[$k]['type']='个体商户':$info[$k]['type']='101合伙人';
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $count = Db::name('store')->where('name','like',"%".input('keywords')."%")->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 编辑店铺
     */
    public function edit()
    {
        $id = input('id');
        if (request()->isPost()) {
            $post = $this->request->post();
            $id = $post['id'];
            $data['name'] = $post['name'];
            $data['price'] = $post['price'];
            $img = request()->file('photo');
            if(!empty($img)){
                $info = $img->move(ROOT_PATH . 'public' . DS . 'uploads/admin/goods_image');
                if ($info) {
                    $file_name =$info->getSaveName();
                    $file_name =   str_replace('\\', '/',$file_name);
                    $data['agreement'] = '/uploads/admin/goods_image/' . $file_name;
                } else {
                    $res =  $info->getError();
                }
            }
            $obj = Db::name('store')->where('id',$id)->update($data);
            if ($obj) {
                $this->success('编辑成功', url('store/index'));
            } else {
                $this->error('编辑失败');
            }
        } else {
            $ret = array();
            if($id){
                $ret = Db::name('store')->where('id',$id)->find();
                $ret['type']==1?$ret['type']='个体商户':$ret['type']='101合伙人';
            }
            $this->assign('info',$ret);
        }
        $this->assign('id',$id);
        return $this->fetch();
    }

    /**
     * 店铺权益列表
     */
    public function interests()
    {
        return $this->fetch();
    }

    public function interestsAll(){
        $info = Db::name('store_privilege')->alias('s')
            ->where('o.name','like',"%".input('store_name')."%")
            ->where('s.title','like',"%".input('keywords')."%")
            ->join('le_store o','o.id=s.store_id')
            ->field('s.*,o.name')
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))
            ->select();
        foreach ($info as $k=>$v){
            $info[$k]['image'] = GetConfig('img_prefix').$v['image'];
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $count = Db::name('store_privilege')->alias('s')
            ->where('o.name','like',"%".input('store_name')."%")
            ->where('s.title','like',"%".input('keywords')."%")
            ->join('le_store o','o.id=s.store_id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 删除店铺权益
     */
    public function del_interests()
    {
        $obj = Db::name('store_privilege')->where('id', input('id'))->delete();
        if ($obj) {
            return $data=[
                'code' => 0,
                'msg'  => '删除成功',
            ];
        } else {
            return $data=[
                'code' => 1,
                'msg'  => '删除失败',
            ];
        }
    }

    /**
     * 编辑店铺权益
     */
    public function edit_interests()
    {
        $id = input('id');
        if (request()->isPost()) {
            $post = $this->request->post();
            $id = $post['id'];
            $data['title'] = $post['title'];
            $data['store_id'] = $post['store_id'];
            $data['introduce'] = $post['introduce'];
            $img = request()->file('photo');
            if(!empty($img)){
                $info = $img->move(ROOT_PATH . 'public' . DS . 'uploads/admin/goods_image');
                if ($info) {
                    $file_name =$info->getSaveName();
                    $file_name =   str_replace('\\', '/',$file_name);
                    $data['image'] = '/uploads/admin/goods_image/' . $file_name;
                } else {
                    $res =  $info->getError();
                }
            }
            if($id){
                $obj = Db::name('store_privilege')->where('id',$id)->update($data);
            }else{
                $data['create_time'] = time();
                $obj = Db::name('store_privilege')->insertGetId($data);
            }
            if ($obj) {
                $this->success('编辑成功', url('store/interests'));
            } else {
                $this->error('编辑失败');
            }
        } else {
            $ret = array();
            if($id){
                $ret = Db::name('store_privilege')->where('id',$id)->find();
                $ret['image'] = GetConfig('img_prefix').$ret['image'];
                $this->assign('info',$ret);
            }
            $store = Db::name('store')->field('id,name')->select();
            $this->assign('store',$store);
        }
        $this->assign('id',$id);
        return $this->fetch();
    }

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
