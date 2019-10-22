<?php

namespace app\api\controller;


use app\admin\model\Users;
use app\admin\model\Address as AddressModel;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class Address extends Base
{
    /**
     * 地址列表
     */
    public function lists()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        $address = Db::name('address')
            ->where('user_id', $this->user_id)
            ->where('type', 1)
            ->field(['id,name,mobile,province,city,district,detail,is_default'])
            ->order('is_default','desc')
            ->order('update_time','desc')
            ->select();

        $data = [
            'list' => $address,
            'is_auth' => $user['is_auth'],
            'is_enter' => $user['is_enter'],
        ];
        return JsonSuccess($data);
    }

    /**
     * 地址详情
     */
    public function detail(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $address_id = $request->param('address_id');
        if (!$address_id) {
            return JsonError('参数获取失败');
        }
        $address = Db::name('address')->where('id', $address_id)->where('user_id', $this->user_id)->find();
        if (!$address) {
            return JsonError('数据获取失败');
        }
        $data = [
            'detail' => $address
        ];
        return JsonSuccess($data);
    }

    /**
     * 自提地址详情
     */
    public function pick(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        if (!$user->is_enter){
            return JsonError('您不是微厨用户');
        }
        $address = Db::name('address')->where('user_id',$this->user_id)->where('type',2)->find();
        $data = [
            'detail' => $address
        ];
        return JsonSuccess($data);
    }

    /**
     * 修改or添加地址
     * 修改or添加地址
     */
    public function save(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $validate = new Validate([
            ['name', 'require', '请填写收货人'],
            ['mobile', 'require', '请填写手机号码'],
            ['province', 'require', '请选择省'],
            ['city', 'require', '请选择城市'],
            ['district', 'require', '请选择区域'],
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
            $address = AddressModel::where('id', $id)->where('user_id', $this->user_id)->find();
            if (!$address) {
                $address = new AddressModel();
            }
//            $location = $this->location($post['latitude'],$post['longitude']);
//            if (!$location){
//                return JsonError('地区获取失败');
//            }
//            if (!$location['district']){
//                return JsonError('区域获取失败');
//            }
            $address->user_id = $this->user_id;
            $address->name = $post['name'];
            $address->mobile = $post['mobile'];
            $address->province = $post['province'];
            $address->city = $post['city'];
            $address->district = $post['district'];
            $address->longitude = $post['longitude'];
            $address->latitude = $post['latitude'];
            $address->detail = $post['detail'];
            $address->is_pick = $request->param('is_pick',0);
            $address->type = $request->param('type',1);
            //如果是设置为默认，将其他的设置为普通
            $is_default = $post['is_default'];

            if ($is_default == 1) {
//                $address->is_default = 1;
                AddressModel::where('user_id', $this->user_id)->where('type', $request->param('type', 1))->update([
                    'is_default' => 0
                ]);
            } else {
                $res = AddressModel::where('user_id', $this->user_id)->where('type', $request->param('type', 1))->select();
                if (!$res) {
                    $address->is_default = 1;
                }
            }

            $address->is_default = $is_default;
            $address->save();
            Db::commit();
            return JsonSuccess();
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('保存失败');
        }

    }

    /**
     * 设置默认地址
     */
    public function set_default(Request $request)
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
            $address = AddressModel::where('id', $address_id)->where('user_id', $this->user_id)->where('type', 1)->find();
            if (!$address) {
                return JsonError('数据获取失败');
            }
            AddressModel::where('user_id', $this->user_id)->where('type', 1)->update([
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

    /**
     * 删除地址
     */
    public function delete(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }

        $address_id = $request->param('address_id');
        if (!$address_id){
            return JsonError('参数获取失败');
        }

        $address = \app\admin\model\Address::where('id',$address_id)->where('user_id',$this->user_id)->where('type',1)->find();
        if (!$address){
            return JsonError('数据获取失败');
        }
        if ($address->delete()){
            return JsonSuccess([],'删除成功');
        }
        return JsonError('删除失败');
    }
}