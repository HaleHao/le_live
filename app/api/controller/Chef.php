<?php

namespace app\api\controller;

use app\admin\model\Users;
use think\Request;
use app\admin\model\Menus;

class Chef extends Base
{

    /**
     * 厨师列表
     * Date: 2019/9/17 0017
     */
    public function lists()
    {
        $chef = Users::where('is_auth',1)
            ->order('like_num','desc')
            ->order('fan_num','desc')
            ->order('create_time','desc')
            ->paginate();
        $data = [
            'chef' => $chef
        ];
        return JsonSuccess($data);
    }


    /**
     * 厨师详情
     * Date: 2019/9/17 0017
     */
    public function detail(Request $request)
    {
        $id = $request->param('id');
        if ($id){
            $chef = Users::where('id',$id)->find();
            //菜谱
            $menus = Menus::where('user_id',$id)->select();
            //可预约的菜谱
            $reserve = Menus::where('is_reserve',1)->select();
            //TODO 动态

        }
        return JsonError('参数获取失败');
    }

    /**
     * 菜谱
     */
    public function menus()
    {

    }

    /**
     * 可预约的菜谱
     */
    public function reserve()
    {

    }



    /**
     * 关注
     */
    public function follow(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $id = $request->param('id');
        if (!$id){
            return JsonError('参数获取失败');
        }
        //TODO 判断是否关注
//        $follow = Foll

    }



}