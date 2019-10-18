<?php

namespace app\admin\controller;

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Coupon extends Permissions
{

    /**
     * 代金券列表
     */
    public function index()
    {
        return $this->fetch();
    }
    public function index_all(){
        $info = Db::name('coupon')
            ->where('title','like',"%".input('keywords')."%")
            ->order('id desc')
            ->limit((input('page') - 1) * input('limit'), input('limit'))
            ->select();
        foreach ($info as $k=>$v){
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $count = Db::name('coupon')->where('title','like',"%".input('keywords')."%")->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 编辑代金券
     */
    public function edit()
    {
        $id = input('id');
        if (request()->isPost()) {
            $post = $this->request->post();
            $post['end_date']<$post['start_date']&&$this->error('开始时间不能大于结束时间');
            $id = $post['id'];
            $data['title'] = $post['title'];
            $data['price'] = $post['price'];
            $data['conditions'] = $post['conditions'];
            $data['number'] = $post['number'];
            $data['start_date'] = $post['start_date'];
            $data['end_date'] = $post['end_date'];
            $data['start_time'] = strtotime($post['start_date']);
            $data['end_time'] = strtotime($post['end_date']);
            if($id){
                $obj = Db::name('coupon')->where('id',$id)->update($data);
            }else{
                $obj = Db::name('coupon')->insertGetId($data);
            }
            if ($obj) {
                $this->success('编辑成功', url('coupon/index'));
            } else {
                $this->error('编辑失败');
            }
        } else {
            $ret = array();
            $id&&$ret = Db::name('coupon')->where('id',$id)->find();
            $this->assign('info',$ret);
        }
        $this->assign('id',$id);
        return $this->fetch();
    }

    /**
     * 删除代金券
     */
    public function del()
    {
        $obj = Db::name('coupon')->where('id', input('id'))->delete();
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

}
