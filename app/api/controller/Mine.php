<?php

namespace app\api\controller;

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

        $user = Users::where('id', $this->user_id)->field([''])->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'user_info' => $user
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

        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'user_info' => $user
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
        $page = $request->param('page');
        $list = Menus::where('user_id', $this->user_id)->order('create_time', 'desc')->page($page, 10);
        $data = [
            'list' => $list
        ];
        return JsonSuccess($data);
    }

    /**
     * 菜谱详情
     */
    public function menus_detail(Request $request)
    {

    }


    /**
     * 菜谱编辑
     */
    public function menus_edit(Request $request)
    {

    }

    /**
     * 菜谱删除
     */
    public function menus_delete(Request $request)
    {

    }

    /**
     * 今日可预约的
     */
    public function reserve_list(Request $request)
    {

    }

    /**
     * 地址列表
     */
    public function address_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $type = $request->param('type');
        $list = Address::where('user_id', $this->user_id)->where('type', $type)->select();
        $data = [
            'list' => $list
        ];
        return JsonSuccess($data);

    }

    /**
     * 地址编辑
     */
    public function address_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $id = $request->param('address_id');
        $address = Address::where('id', $id)->where('user_id', $this->user_id)->find();
        $data = [
            'address' => $address
        ];
        return JsonSuccess($data);
    }

    /**
     * 地址保存
     */
    public function address_save(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $validate = new Validate([
            ['name', 'require', '请填写收货人'],
            ['mobile', 'require', '请填写手机号码'],
            ['province', 'require', '请填写省'],
            ['city', 'require', '请填写城市'],
            ['district', 'require', '请填写区域'],
            ['longitude', 'require', '请选择位置'],
            ['latitude', 'require', '请选择位置'],
            ['detail', 'require', '请填写详细地址'],
        ]);
        $post = $request->param();
        if (!$validate->check($post)) {
            return JsonError($validate->getError());
        }

        Db::startTrans();
        try {
            $id = $request->param('address_id');
            $address = Address::where('id', $id)->where('user_id', $this->user_id)->find();
            if ($address) {
                $address = new Address();
            }
            $address->name = $post['name'];
            $address->mobile = $post['mobile'];
            $address->province = $post['province'];
            $address->city = $post['city'];
            $address->district = $post['district'];
            $address->longitude = $post['longitude'];
            $address->latitude = $post['latitude'];
            $address->detail = $post['detail'];

            //如果是设置为默认，将其他的设置为普通
            $is_default = $post['is_default'];
            if ($is_default == 1) {
                $address->is_default = 1;
                Address::where('user_id', $this->user_id)->where('type', $request->param('type', 0))->update([
                    'is_default' => 0
                ]);
            } else {
                $res = Address::where('user_id', $this->user_id)->where('type', $request->param('type', 0))->select();
                if (!$res) {
                    $address->is_default = 1;
                }
            }
            $address->save();
            Db::commit();

            return JsonSuccess();
        } catch (Exception $exception) {

            Db::rollback();
            return JsonError();
        }

    }

    /**
     * 设置默认
     */
    public function address_default(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $address_id = $request->param('address_id');
        if (!$address_id) {
            return JsonError('参数获取失败');
        }

        Db::startTrans();
        try {
            $type = $request->param('type', 0);
            $address = Address::where('id', $address_id)->where('user_id', $this->user_id)->where('type', $type)->find();
            if (!$address) {
                return JsonError('数据获取失败');
            }
            Address::where('user_id', $this->user_id)->where('type', $type)->update([
                'is_default' => 0
            ]);

            $address->is_default = 1;
            $address->save();
            Db::commit();
            return JsonSuccess();
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('设置失败');
        }
    }


}