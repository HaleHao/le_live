<?php

namespace app\api\controller;

use app\admin\model\MenusImage;
use app\admin\model\Users;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;
use app\admin\model\Menus;
use app\admin\model\Address;

class Mine extends Base
{
    /**
     * 个人中心
     */
    public function index()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->field(['avatar', 'gender', 'nickname', 'skill', 'is_auth', 'follower_num', 'fan_num'])->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'detail' => $user
        ];
        return JsonSuccess($data);

    }

    /**
     * 个人信息
     */
    public function info()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->field(['avatar', 'nickname', 'gender', 'mobile', 'province', 'city', 'district', 'signature', 'skill', 'image'])->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'detail' => $user
        ];
        return JsonSuccess($data);
    }

    /**
     * 个人信息编辑
     */
    public function info_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = $request->param();
        if ($user->save($data)) {
            return JsonSuccess();
        }
        return JsonError();
    }


    /**
     * 我发布的菜谱
     */
    public function menus_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }
        $page = $request->param('page');
        $list = Menus::alias('m')
            ->join('menus_reserve r', 'm.id=r.menu_id', 'left')
            ->where('m.user_id', $this->user_id)
            ->order('create_time', 'desc')
            ->field(['m.create_time', 'm.cover_image', 'm.id', 'm.title', 'm.introduce', 'm.like_num', 'r.price'])->page($page, 10)->select();
        $count = Menus::where('user_id', $this->user_id)
            ->order('create_time', 'desc')
            ->count();
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 我发布的可预约
     */
    public function reserve_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }
        $page = $request->param('page');
        $list = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'r.menu_id=m.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->field(['m.create_time', 'm.cover_image', 'm.id', 'm.title', 'm.introduce', 'm.like_num', 'r.price'])
            ->page($page, 10)
            ->select();

        $count = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'r.menu_id=m.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->count();

        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }


    /**
     * 菜谱详情
     */
    public function menus_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $menu_id = $request->param('menu_id');
        if (!$menu_id) {
            return JsonError('参数获取失败');
        }
        $menu = Db::name('menus')->alias('m')
            ->join('column c', 'm.column_id=c.id', 'left')
            ->where('m.id', $menu_id)
            ->where('m.user_id', $this->user_id)
            ->field(['m.id', 'm.title', 'm.introduce', 'm.column_id', 'c.title as column_title'])
            ->find();
        if (!$menu) {
            return JsonError('数据获取失败');
        }
        $images = Db::name('menus_image')->where('menu_id', $menu_id)->field(['image'])->select();
        foreach ($images as $key => $image) {
            $images[$key]['show_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $image['image'];
        }
        $menu['images'] = $images;
        $data = [
            'detail' => $menu
        ];
        return JsonSuccess($data);

    }


    /**
     * 菜品保存
     */
    public function menus_save(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $menu_id = $request->param('menu_id');

        if (!$menu_id) {
            return JsonError('参数获取失败');
        }
        $post = $request->param();
        $validate = new Validate([
            ['title', 'require', '菜品标题不能为空'],
            ['introduce', 'require', '菜品介绍不能为空'],
            ['column_id', 'require', '请选择栏目'],
        ]);
        if (!$validate->check($post)) {
            return JsonError($validate->getError());
        }

        $images = json_decode($request->param('images'), true);
        if (!$images) {
            return JsonError('请上传图片');
        }

        Db::startTrans();
        try {
            $menu = Menus::where('id', $menu_id)->where('user_id', $this->user_id)->find();
            if (!$menu) {
                return JsonError('数据获取失败');
            }

            $menu->title = $post['title'];
            $menu->introduce = $post['introduce'];
            $menu->cover_image = $images[0];
            $menu->column_id = $post['column_id'];
            $menu->user_id = $this->user_id;


            $menu->save();
            //将之前的图片删除了
            Db::name('menus_image')->where('menu_id', $menu_id)->delete();
            //保存图片
            foreach ($images as $image) {
                $image_model = new MenusImage();
                $image_model->image = $image;
                $image_model->menu_id = $menu_id;
                $image_model->save();
            }
            Db::commit();
            return JsonSuccess(['id' => $menu_id], '修改成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('修改失败');
        }
    }

    /**
     * 菜谱删除
     */
    public function menus_delete(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $menu_id = $request->param('menu_id');

        if (!$menu_id) {
            return JsonError('参数获取失败');
        }

        Db::startTrans();
        try {
            $menu = Menus::where('menu_id',$menu_id)->where('user_id',$this->user_id)->find();
            if (!$menu){
                return JsonError('参数获取失败');
            }
            $reserve = Db::name('menus_reserve')->where('menu_id',$menu_id)->find();
            if (!$reserve){
                return JsonError('该菜品为预约菜品，不能删除');
            }
            $menu->delete();
            //查找出图片进行删除
            Db::name('menus_image')->where('menu_id',$menu_id)->delete();
            Db::commit();
            return JsonSuccess([],'删除成功');
        } catch (Exception $exception) {
            Db::rollback();;
            return JsonError('删除十遍');

        }
    }


    /**
     * 预约菜单编辑
     */
    public function reserve_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $reserve_id = $request->param('reserve_id');
        if (!$reserve_id) {
            return JsonError('参数获取失败');
        }
        $reserve = Db::name('menus_reserve')->where('id',$reserve_id)->find();
        $data = [
            'detail' => $reserve
        ];
        return JsonSuccess($data);
    }

}