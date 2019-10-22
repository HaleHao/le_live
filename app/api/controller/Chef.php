<?php

namespace app\api\controller;

use app\admin\model\Users;
use app\admin\model\UsersFollower;
use think\Db;
use think\Exception;
use think\Request;
use app\admin\model\Menus;

class Chef extends Base
{

    /**
     * 厨师列表
     * Date: 2019/9/17 0017
     */
    public function lists(Request $request)
    {
        $page = $request->param('page', 1);
        $type = $request->param('type', 1);


        $list = Db::name('users')->where('is_enter', 1)->field(['id,image,nickname,skill,signature', 'credit_line'])
            ->page($page, 10)
            ->select();
        $count = Db::name('users')->where('is_enter', 1)->count();
        foreach ($list as $key => &$val) {
            $list[$key]['image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['image'];
        }
        //按距离排序
        if ($type == 1) {
            $longitude = $request->param('longitude');
            $latitude = $request->param('latitude');
            $list = Db::name('users')->alias('u')
                ->join('address a', 'u.id=a.user_id', 'left')
                ->where('u.is_enter', 1)
                ->where('a.type', 2)
                ->field(['u.id,u.image,u.nickname,u.skill,u.signature,a.longitude,a.latitude,u.credit_line'])
                ->page($page, 10)
                ->select();
            $count = Db::name('users')->alias('u')
                ->join('address a', 'u.id=a.user_id', 'left')
                ->where('u.is_enter', 1)
                ->where('a.type', 2)->count();
            $to = [$longitude, $latitude];
            if ($list) {
                foreach ($list as $key => &$val) {
                    $form = [$val['longitude'], $val['latitude']];
                    $val['distance'] = GetDistance($form, $to);
                    $list[$key]['image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['image'];
                    $distance[] = $list[$key]['distance'];
                }
                array_multisort($distance, SORT_ASC, $list);
            }
        }
        //按人气排序
        if ($type == 2) {
            $list = Db::name('users')->where('is_enter', 1)
                ->order('like_num', 'desc')
                ->order('fan_num', 'desc')
                ->order('create_time', 'desc')->field(['id,image,nickname,skill,signature', 'credit_line'])
                ->page($page, 10)
                ->select();
            $count = Db::name('users')->where('is_enter', 1)->count();
            foreach ($list as $key => &$val) {
                $list[$key]['image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['image'];
            }
        }

        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }


    /**
     * 厨师详情
     * Date: 2019/9/17 0017
     */
    public function detail(Request $request)
    {
        $chef_id = $request->param('chef_id');
        if (!$chef_id) {
            return JsonError('参数获取失败');
        }
        $chef = Users::where('id', $chef_id)
            ->field(['id', 'like_num', 'fan_num', 'follower_num', 'avatar', 'gender', 'nickname', 'city', 'signature', 'skill', 'is_auth', 'credit_line'])
            ->find();

        $res = Db::name('users_follower')->where('user_id', $this->user_id)->where('chef_id', $chef_id)->find();
        $is_follower = 0;
        if ($res) {
            $is_follower = 1;
        }

        $reserve_num = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'm.id=r.menu_id and m.user_id=' . $chef_id . '', 'left')
            ->where('m.id', '>', 0)
            ->where('r.serving_date', '>', date('Y-m-d', time()))
            ->where('r.total_amount', '>', 0)
            ->count();

        $menus_num = Menus::where('user_id', $chef_id)->count();

        $posts_num = Db::name('menus_log')->alias('l')
            ->join('menus m', 'l.menu_id=m.id', 'left')
            ->join('menus_reserve r', 'l.menu_id=r.menu_id', 'left')
            ->join('menus_like k', 'l.menu_id=k.menu_id and k.user_id=' . $this->user_id . '', 'left')
            ->where('m.id', '>', 0)
            ->where('l.user_id', $chef_id)->count();

        $data = [
            'detail' => $chef,
            'is_follower' => $is_follower,
            'reserve_num' => $reserve_num,
            'menus_num' => $menus_num,
            'posts_num' => $posts_num
        ];

        return JsonSuccess($data);
    }


    /**
     * 菜谱
     */
    public function menus(Request $request)
    {
        $chef_id = $request->param('chef_id');
        if (!$chef_id) {
            return JsonError('参数获取失败');
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus')->alias('m')
            ->join('menus_like l', 'l.menu_id=m.id and l.user_id=' . $this->user_id . '', 'left')
            ->where('m.user_id', $chef_id)
//            ->where('l.user_id',$this->user_id)
            ->field(['m.id', 'm.cover_image', 'm.title', 'm.introduce', 'm.like_num', 'l.id as is_like'])
            ->page($page, 10)->select();
        foreach ($list as $key => $val) {
            $list[$key]['is_like'] = $val['is_like'] ? 1 : 0;
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
        }
        $count = Menus::where('user_id', $chef_id)->count();
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 可预约的菜谱
     */
    public function reserve(Request $request)
    {
        $chef_id = $request->param('chef_id');
        if (!$chef_id) {
            return JsonError('参数获取失败');
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'm.id=r.menu_id and m.user_id=' . $chef_id . '', 'left')
            ->join('address a', 'a.user_id=' . $chef_id . ' and a.type=2', 'left')
            ->join('menus_like l', 'r.menu_id=l.menu_id and l.user_id=' . $this->user_id, 'left')
            ->join('column c', 'm.column_id=c.id', 'left')
            ->where('m.id', '>', 0)
            ->where('r.serving_date', '>', date('Y-m-d', time()))
            ->where('r.total_amount', '>', 0)
            ->field(['m.id', 'm.title', 'm.introduce', 'm.cover_image', 'm.like_num', 'r.price', 'c.title as label', 'a.longitude', 'a.latitude', 'l.id as is_like'])
            ->page($page, 10)->select();
        $count = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'm.id=r.menu_id and m.user_id=' . $chef_id . '', 'left')
            ->where('m.id', '>', 0)
            ->where('r.serving_date', '>', date('Y-m-d', time()))
            ->where('r.total_amount', '>', 0)
            ->count();

        $longitude = $request->param('longitude');
        $latitude = $request->param('latitude');
        if ($list) {
            $to = [$longitude, $latitude];
            foreach ($list as $key => &$val) {
                $form = [$val['longitude'], $val['latitude']];
                $val['distance'] = GetDistance($form, $to);
                $distance[] = $list[$key]['distance'];
                $val['is_like'] = $val['is_like'] ? 1 : 0;
                $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            }
            array_multisort($distance, SORT_ASC, $list);
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }


    /**
     * 动态
     */
    public function posts(Request $request)
    {
        $chef_id = $request->param('chef_id');
        if (!$chef_id) {
            return JsonError('参数获取失败');
        }
        $page = $request->param('page', 1);
        $query = Db::name('menus_log')->alias('l')
            ->join('menus m', 'l.menu_id=m.id', 'left')
            ->join('menus_reserve r', 'l.menu_id=r.menu_id', 'left')
            ->join('menus_like k', 'l.menu_id=k.menu_id and k.user_id=' . $this->user_id . '', 'left')
            ->where('m.id', '>', 0)
            ->where('l.user_id', $chef_id)
            ->order('l.create_time', 'desc');
        $query1 = clone $query;
        $list = $query->page($page, 10)
            ->field(['l.content', 'l.create_time', 'm.id', 'm.cover_image', 'l.type', 'm.like_num', 'k.id as is_like', 'm.introduce', 'r.price'])
            ->select();
        $count = $query1->count();
        foreach ($list as $key => $val) {
            $list[$key]['is_like'] = $val['is_like'] ? 1 : 0;
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            $list[$key]['price'] = $val['price'] ? $val['price'] : 0;
            $list[$key]['month'] = date('m', $val['create_time']);
            $list[$key]['day'] = date('d', $val['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 关注
     */
    public function follow(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $chef_id = $request->param('chef_id');
        if (!$chef_id) {
            return JsonError('参数获取失败');
        }
        if ($chef_id == $this->user_id) {
            return JsonError('不能关注自己');
        }
        Db::startTrans();
        try {
            //
            $follower = UsersFollower::where('user_id', $this->user_id)->where('chef_id', $chef_id)->find();
            if (!$follower) {
                $follower = new UsersFollower();
                $follower->user_id = $this->user_id;
                $follower->chef_id = $chef_id;
                $follower->save();
                Users::where('id', $chef_id)->setInc('fan_num', 1);
                Users::where('id', $this->user_id)->setInc('follower_num', 1);
                Db::commit();
                return JsonSuccess([], '关注成功');
            } else {
                $follower->delete();
                Users::where('id', $chef_id)->setDec('fan_num', 1);
                Users::where('id', $this->user_id)->setDec('follower_num', 1);
                Db::commit();
                return JsonSuccess([], '取消关注成功');
            }

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('操作失败');
        }
    }


}