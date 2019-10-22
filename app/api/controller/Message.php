<?php

namespace app\api\controller;

use app\admin\model\MenusComment;
use think\Db;
use think\Request;
use app\admin\model\MenusOrder;

class Message extends Base
{
    /**
     * 回复列表
     */
    public function replay_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus_comment')->alias('c')
            ->join('menus m', 'c.menu_id=m.id', 'left')
            ->join('menus_comment c2', 'c.parent_id=c2.id', 'left')
            ->join('users u', 'u.id=c.to_user_id', 'left')
            ->join('users r', 'r.id=c.user_id', 'left')
            ->where('c.user_id', $this->user_id)
            ->where('c.parent_id', '>', 0)
            ->order('c.create_time', 'desc')
            ->field(['c.id', 'c.create_time', 'c.content as replay', 'c2.images', 'c2.content', 'm.id as menu_id', 'm.title', 'm.introduce', 'm.cover_image', 'r.nickname as replay_nickname', 'r.id as replay_user_id', 'u.avatar as replay_avatar', 'u.nickname', 'u.id as user_id'])
            ->page($page, 10)
            ->select();

        $count = Db::name('menus_comment')->alias('c')
            ->join('menus m', 'c.menu_id=m.id', 'left')
            ->join('menus_comment c2', 'c.parent_id=c2.id', 'left')
            ->join('users u', 'u.id=c.to_user_id', 'left')
            ->join('users r', 'r.id=c.user_id', 'left')
            ->where('c.user_id', $this->user_id)
            ->where('c.parent_id', '>', 0)
            ->order('c.create_time', 'desc')
            ->count();
        foreach ($list as $key => $val) {
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['replay_avatar'])) {
                $list[$key]['replay_avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['replay_avatar'];
            }
            $list[$key]['create_time'] = date('m-d H:i:s', $val['create_time']);
            $images = json_decode($val['images'],true);
            $arr = [];
            if ($images){
                foreach($images as $v){
                    $arr[] = GetConfig('img_prefix', 'http://www.le-live.com').$v;
                }
            }
            $list[$key]['images'] = $arr;
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 获赞列表
     */
    public function like_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus_like')->alias('l')
            ->join('menus m', 'l.menu_id=m.id', 'left')
            ->join('users u', 'l.user_id=u.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->field(['l.id', 'l.create_time', 'u.nickname', 'u.avatar', 'u.id as user_id', 'm.id as menu_id', 'm.title', 'm.introduce', 'm.cover_image'])
            ->page($page, 10)
            ->select();
        $count = Db::name('menus_like')->alias('l')
            ->join('menus m', 'l.menu_id=m.id', 'left')
            ->join('users u', 'l.user_id=u.id', 'left')
            ->where('m.user_id', $this->user_id)->count();
        foreach ($list as $key => $val) {
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['avatar'])) {
                $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['avatar'];
            }
            $list[$key]['create_time'] = date('m-d H:i:s', $val['create_time']);
            $list[$key]['content'] = '点赞了这条动态';
        }

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);

    }

    /**
     * 评论列表
     */
    public function comment_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus_comment')->alias('c')
            ->join('menus m', 'c.menu_id=m.id', 'left')
            ->join('users u', 'c.user_id=u.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->where('c.to_user_id', $this->user_id)
            ->order('c.create_time', 'desc')
            ->field(['c.id', 'c.content', 'c.images', 'c.create_time', 'u.nickname', 'u.avatar', 'u.id as user_id', 'm.id as menu_id', 'm.title', 'm.title', 'm.introduce', 'm.cover_image'])
            ->page($page, 10)->select();
        $count = Db::name('menus_comment')->alias('c')
            ->join('menus m', 'c.menu_id=m.id', 'left')
            ->join('users u', 'c.user_id=u.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->where('c.to_user_id', $this->user_id)
            ->order('c.create_time', 'desc')->count();
        foreach ($list as $key => $val) {
            $list[$key]['create_time'] = date('m-d H:i:s', $val['create_time']);
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['avatar'])) {
                $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['avatar'];
            }
            $images = json_decode($val['images'], true);
            $arr = [];
            if ($images) {
                foreach ($images as $image) {
                    $arr[] = GetConfig('img_prefix', 'http://www.le-live.com') . $image;
                }
            }
            $list[$key]['images'] = $arr;
        }
        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }

    /**
     * 系统消息
     */
    public function system_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $page = $request->param('page', 1);
        $list = MenusOrder::with('menus')
            ->where('user_id', $this->user_id)
            ->whereOr('chef_id', $this->user_id)
            ->where('order_status','>',0)
            ->order('update_time','desc')
            ->field(['id','order_no','order_status','delivery_type','is_comment','chef_id','update_time'])
            ->page($page, 10)->select();
        $count = MenusOrder::where('user_id', $this->user_id)->whereOr('chef_id', $this->user_id)->where('order_status','>',0)->count();

        foreach ($list as $key => $val)
        {
            $content = '';
            if ($val['order_status'] == 1){
                if ($val['chef_id'] == $this->user_id){
                    $content = '订单'.$val['order_no'].'已付款，等待您的发货';
                }else{
                    $content = '您的订单'.$val['order_no'].'已付款,等待微厨发货';
                }
            }

            if ($val['order_status'] == 2){
                if ($val['chef_id'] == $this->user_id){
                    $content = '订单'.$val['order_no'].'已付款，等待您的发货';
                }else{
                    $content = '您的订单'.$val['order_no'].'已发货,请注意查收';
                }
            }
            if ($val['order_status'] == 3){
                if ($val['chef_id'] == $this->user_id){
                    $content = '订单'.$val['order_no'].'已付款，等待用户自提';
                }else{
                    $content = '您的订单'.$val['order_no'].'已付款,等待您前去自提';
                }
            }
            if ($val['order_status'] == 4){
                if ($val['chef_id'] == $this->user_id){
                    $content = '您的订单'.$val['order_no'].'已签收,订单完成';
                }else{
                    if ($val['is_comment']){
                        $content = '您的订单'.$val['order_no'].'已签收,订单完成';
                    }else{
                        $content = '您的订单'.$val['order_no'].'已签收,可以评价了哦';
                    }
                }
            }
            if ($val['order_status'] == 5){
                if ($val['chef_id'] == $this->user_id){
                    $content = '订单'.$val['order_no'].'，买家已取消';
                }else{
                    $content = '您的订单'.$val['order_no'].'已取消,稍后退款至微信';
                }
            }

            if ($val['order_status'] == 0){
                $content = '订单'.$val['order_no'].'未支付';
            }
            $list[$key]['content'] = $content;
//            $list[$key]['update_time'] = date('m-d H:i:s', strtotime()$val['update_time']);
            $list[$key]['avatar'] = GetConfig('img_prefix').'/uploads/admin/admin_thumb/20180104/1.jpg';
            $list[$key]['nickname'] = '乐Live';
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);

    }


    /**
     * 消息回复
     */
    public function comment_replay(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $comment_id = $request->param('comment_id');
        if (!$comment_id){
            return JsonError('参数获取失败');
        }
        $comment = Db::name('menus_comment')->where('id',$comment_id)->find();
        if (!$comment){
           return JsonError('数据获取失败');
        }

        $content = $request->param('content');
        if (!$content){
            return JsonError('回复内容不能为空');
        }


        $model = new MenusComment();
        $model->content = $content;
        $model->menu_id = $comment['menu_id'];
        $model->user_id = $this->user_id;
        $model->parent_id = $comment_id;
        $model->to_user_id = $comment['user_id'];
        $model->type = 1;
        if ($model->save()) {
            return JsonSuccess([], '评论成功');
        }
        return JsonError('评论失败');

    }
}