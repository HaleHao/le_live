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


use think\Db;

class Identity extends Permissions
{

    //微厨
    public function chef()
    {
        return $this->fetch();
    }

    public function chef_all()
    {

        $list = \app\admin\model\Users::where('user_type', 1)->where('store_id', 1)->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($list as $k => $v) {
            $store = Db::name('store')->where('type', $v['user_type'])->find();
            if ($store) {
                $identity = $store['name'];
            } else {
                $identity = '普通用户';
            }
            $list[$k]['identity'] = $identity;
        }
        $count = \app\admin\model\Users::where('user_type', 1)->where('store_id', 1)->count();
        return $data = [
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $list,
        ];
    }

    //联盟
    public function alliance()
    {
        return $this->fetch();
    }

    public function alliance_all()
    {
        $list = \app\admin\model\Users::where('user_type', 2)->where('store_id', 2)->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($list as $k => $v) {
            $store = Db::name('store')->where('type', $v['user_type'])->find();
            if ($store) {
                $identity = $store['name'];
            } else {
                $identity = '普通用户';
            }
            $list[$k]['identity'] = $identity;
        }
        $count = \app\admin\model\Users::where('user_type', 2)->where('store_id', 2)->count();
        return $data = [
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $list,
        ];
    }


    //合伙人
    public function partner()
    {
        return $this->fetch();
    }

    public function partner_all()
    {
        $list = \app\admin\model\Users::where('user_type', 3)->where('store_id', 3)->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($list as $k => $v) {
            $store = Db::name('store')->where('type', $v['user_type'])->find();
            if ($store) {
                $identity = $store['name'];
            } else {
                $identity = '普通用户';
            }
            $list[$k]['identity'] = $identity;
        }
        $count = \app\admin\model\Users::where('user_type', 3)->where('store_id', 3)->count();
        return $data = [
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $list,
        ];
    }

    //事业部
    public function division()
    {
        return $this->fetch();
    }

    public function division_all()
    {
        $list = \app\admin\model\Users::where('user_type', 4)->where('store_id', 3)->order('id desc')->limit((input('page') - 1) * input('limit'), input('limit'))->select();
        foreach ($list as $k => $v) {
            $store = Db::name('store')->where('type', $v['user_type'])->find();
            if ($store) {
                $identity = $store['name'];
            } else {
                $identity = '普通用户';
            }
            $list[$k]['identity'] = $identity;
        }
        $count = \app\admin\model\Users::where('user_type', 3)->where('store_id', 3)->count();
        return $data = [
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $list,
        ];
    }

    public function detail($id)
    {
        $this->assign('id',$id);
        return $this->fetch();
    }

    public function detail_all($id)
    {
        input('create_time')?$where['p.date']=input('create_time'):$where='';
//        input('user_type')?$where['p.user_type']=input('user_type'):$where='';
        $info = Db::name('users_profit')->alias('p')->where($where)
            ->where('user_id',$id)
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
        $count = Db::name('users_profit')->alias('p')->where($where)->where('user_id',$id)->join('le_users u','p.user_id=u.id')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }
}