<?php

namespace app\api\controller;

use app\admin\model\Admin;
use app\admin\model\Banner;
use app\admin\model\MenusComment;
use app\admin\model\StoreCode;
use app\admin\model\Users;
use app\api\service\WeChatPayService;
use think\Db;
use think\Request;
use app\admin\model\MenusOrder;

class Share extends Base
{
    /**
     * 业务介绍
     */
    public function company_profile()
    {
        $banner = Banner::where('type', 2)
            ->order('sort', 'asc')
            ->order('create_time', 'desc')
            ->field(['image'])
            ->select();
        $introduce = Db::name('company_profile')->find();

        $data = [
            'banner' => $banner,
            'introduce' => $introduce['introduce']
        ];
        return JsonSuccess($data);
    }

    /**
     * 电子协议
     */
    public function electronic_agreement()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $first_user = Users::where('id',$user->first_user_id)->find();
        if ($first_user){
            if ($first_user->is_partner == 1){
                $list = Db::name('store')->select();
            }else{
                $list = Db::name('store')->where('type',1)->select();
            }
        }else{
        //判断是否有上下级
            if ($user->is_partner == 1) {
                $list = Db::name('store')->select();
            } elseif ($user->is_enter == 1) {
                $list = Db::name('store')->where('type', 1)->select();
            } else {
                $list = Db::name('store')->select();
            }
        }

        foreach ($list as &$val) {
            $val['agreement'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['agreement'];
        }

        $data = [
            'list' => $list,
            'is_enter' => $user->is_enter
        ];
        return JsonSuccess($data);
    }

    /**
     * 提交订单
     */
    public function store_submit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }
        $store_id = $request->param('store_id');
        if (!$store_id) {
            return JsonError('参数获取失败');
        }
        $store = Db::name('store')->where('id', $store_id)->find();
        if (!$store) {
            return JsonError('数据获取失败');
        }

        $amount = $request->param('amount');
        if (!$amount) {
            return JsonError('请填写正确的数量');
        }
        $type = $request->param('type');
        //微信支付
        if ($type == 1){
            $order = [
                'order_no' => GetOrderNo(),
                'user_id' => $this->user_id,
                'order_status' => 0,
                'pay_status' => 0,
                'store_id' => $store_id,
                'store_name' => $store['name'],
                'amount' => $amount,
                'unit_price' => $store['price'],
                'total_price' => $store['price'] * $amount,
                'pay_price' => $store['price'] * $amount,
                'create_time' => time(),
                'update_time' => time()
            ];
            $order_id = Db::name('store_order')->insertGetId($order);
            if (!$order_id) {
                return JsonError('订单生成失败');
            }
            $data = [
                'order_id' => $order_id,
                'order_no' => $order['order_no']
            ];
            return JsonSuccess($data);
        }
        //激活码激活
        if ($type == 2){
            Db::startTrans();
            $code = $request->param('code');
            if (!$code){
                return JsonError('请填写激活码');
            }
            $store_code = StoreCode::where('code',$code)->find();
            if (!$store_code){
                return JsonError('激活码错误');
            }
            if ($store_code['status'] == 1){
                return JsonError('激活码已被使用');
            }
            if ($user->is_enter == 1){
                return JsonError('你已经入驻了');
            }
            $store_code->status = 1;
            $user->is_enter = 1;

            if ($store_code->save() && $user->save()){
                Db::commit();
                return JsonSuccess('','激活成功');
            }
            Db::rollback();
            return JsonError('激活失败');

        }
        return JsonError('请选择支付方式');
    }

    /**
     * 订单支付
     */
    public function store_pay(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_no = $request->param('order_no');
        $order_id = $request->param('order_id');
        if (!$order_no || !$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Db::name('store_order')
            ->where('id', $order_id)
            ->where('order_no', $order_no)
            ->where('user_id', $this->user_id)
            ->where('order_status', 0)
            ->where('pay_status', 0)
            ->find();
        if (!$order) {
            return JsonError('订单获取失败');
        }

        $user = Users::where('id', $this->user_id)->find();
        $openid = $user['openid'];

        $notifyUrl = GetConfig('');

        $pay = new WeChatPayService();
        $result = $pay->Mini_Pay($order['order_no'], $order['pay_price'], $openid, $notifyUrl, '购买菜品');
        if ($result) {
            return JsonSuccess($result);
        }
        return JsonError('支付订单生成失败');
    }

    /**
     * 个人中心
     */
    public function user_center()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $avatar = $user->avatar;
        if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $user->avatar)) {
            $avatar = GetConfig('img_prefix', 'http://www.le-live.com') . $user->avatar;
        }

        $data = [
            'avatar' => $avatar,
            'nickname' => $user->nickname,
            'mobile' => $user->mobile,
//            'promote_qrcode' => GetConfig('img_prefix', 'http://www.le-live.com') . $user->promote_qrcode,
            'store_total_num' => $user->store_total_num,
            'store_residue_num' => $user->store_residue_num,
            'store_use_num' => $user->store_use_num,
            'store_first_num' => $user->store_first_num,
            'store_second_num' => $user->store_second_num,
        ];
        return JsonSuccess($data);
    }

    /**
     * 推广二维码
     */
    public function promote_qrcode()
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }
        if ($user->is_enter != 1){
            return JsonError('您没有入驻没有推广二维码');
        }
        $data = [
            'promote_qrcode' => GetConfig('img_prefix', 'http://www.le-live.com') . $user->promote_qrcode,
        ];
        return JsonSuccess($data);
    }

    /**
     * 账户余额
     */
    public function account_balance()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }

        $data = [
            'balance' => $user->store_balance
        ];

        return JsonSuccess($data);
    }

    /**
     * 收益明细
     */
    public function earnings_log(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $type = $request->param('type');

        $list = [];
        $count = 0;
        $page = $request->param('page');
        //全部收益
        if ($type == 0) {
            $list = Db::name('earnings_log')
                ->where('user_id', $this->user_id)
                ->order('create_time','desc')
                ->page($page, 10)
                ->select();
            $count = Db::name('earnings_log')->where('user_id', $this->user_id)->count();
        }
        //招募收益
        if ($type == 1) {
            $list = Db::name('earnings_log')
                ->where('user_id', $this->user_id)
                ->where('type', 1)
                ->order('create_time','desc')
                ->page($page, 10)
                ->select();
            $count = Db::name('earnings_log')->where('user_id', $this->user_id)->where('type', 1)->count();
        }

        if ($type == 2) {
            $list = Db::name('earnings_log')
                ->where('user_id', $this->user_id)
                ->where('type', 2)
                ->order('create_time','desc')
                ->page($page, 10)->select();
            $count = Db::name('earnings_log')->where('user_id', $this->user_id)->where('type', 2)->count();
        }

        if ($type == 3) {
            $list = Db::name('earnings_log')
                ->where('user_id', $this->user_id)
                ->where('type', 3)
                ->order('create_time','desc')
                ->page($page, 10)->select();
            $count = Db::name('earnings_log')->where('user_id', $this->user_id)->where('type', 3)->count();
        }

        foreach ($list as &$val) {
            $val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
        }

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 提现明细
     */
    public function withdraw_log(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $page = $request->param('page');
        $list = Db::name('withdraw_log')
            ->where('user_id',$this->user_id)
            ->order('create_time','desc')
            ->page($page,10)
            ->select();
        $count = Db::name('withdraw_log')
            ->where('user_id',$this->user_id)
            ->count();

        foreach ($list as &$val){
            $val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }


    /**
     * 提现提交
     */
    public function withdraw_submit(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $money = $request->param('money');
        if ($money <= 1){
            return JsonError('金额不能少于1');
        }

        $type = $request->param('type');
        if (!$type){
            return JsonError('请选择提现类型');
        }
        //TODO 微信提现
        if ($type == 1){

        }

        //支付宝
        if ($type == 2){
            $name = $request->param('name');
            if (!$name){
                return JsonError('请填写姓名');
            }
            $account = $request->param('account');
            if (!$account){
                return JsonError('请填写账号');
            }
            $json = [
                'name' => $name,
                'account' => $account
            ];
        }

        //银行卡
        if ($type == 3){
            $name = $request->param('name');
            if (!$name){
                return JsonError('请填写姓名');
            }
            $account = $request->param('account');
            if (!$account){
                return JsonError('请填写账号');
            }
            $bank_name = $request->param('bank_name');
            if (!$bank_name){
                return JsonError('请填写开户行');
            }
            $json = [
                'name' => $name,
                'account' => $account,
                'bank_name' => $bank_name
            ];
        }

        $data = [
            'user_id' => $this->user_id,
            'money' => $money,
            'type' => $type,
            'info' => json_encode($json),
            'status' => 0
        ];
        $id = Db::name('withdraw')->insertGetId($data);
        if ($id){
            return JsonSuccess();
        }
        return JsonError('提交失败');
    }


    /**
     * 招募合伙人
     */
    public function recruit_partner()
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }

        $data = [
            'promote_qrcode' => $user->promote_qrcode,
//            'rule' =>
        ];
        return JsonSuccess($data);
    }

    /**
     * 合伙人列表
     */
    public function partner_list(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $type = $request->param('type');
        $page = $request->param('page',1);
        $list = [];
        $count = 0;
        //一级合伙人
        if ($type == 1){
            $list = Users::where('first_user_id',$this->user_id)->where('is_partner',1)->page($page,10)->field(['nickname','avatar','create_time'])->select();
            $count = Users::where('first_user_id',$this->user_id)->where('is_partner',1)->count();
        }

        if ($type == 2){
            $list = Users::where('second_user_id',$this->user_id)->where('is_partner',1)->page($page,10)->field(['nickname','avatar','create_time'])->select();
            $count = Users::where('second_user_id',$this->user_id)->where('is_partner',1)->count();
        }


        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);
    }


    /**
     * 代办营业执照
     */
    public function business_license(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }
        if ($user->is_auth != 1){
            return JsonAuth('你未进行身份认证');
        }

        $name = $request->param('name');
        $mobile = $request->param('mobile');
        if (!$name){
            return JsonError('请填写姓名');
        }
        if (!$mobile){
            return JsonError('请填写电话号码');
        }

        $user->name = $name;
        $user->mobile = $mobile;
        $user->is_license = 1;
        if ($user->save()){
            return JsonSuccess();
        }
        return JsonError('提交失败');

    }

    /**
     * 一级店铺
     */
    public function first_store(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $page = $request->param('page',1);
        $list = Users::where('is_enter',1)->where('first_user_id',$this->user_id)
            ->page($page,10)->field(['avatar','nickname','create_time'])->select();
        $count = Users::where('is_enter',1)->where('first_user_id',$this->user_id)->count();

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);

    }

    /**
     * 二级店铺
     */
    public function second_store(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $page = $request->param('page',1);
        $list = Users::where('is_enter',1)->where('second_user_id',$this->user_id)
            ->page($page,10)->field(['avatar','nickname','create_time'])->select();
        $count = Users::where('is_enter',1)->where('second_user_id',$this->user_id)->count();

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 我的店铺列表
     */
    public function mine_store()
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        $list = [];
        $activation = Db::name('store_code')->where('status',1)->where('user_id',$this->user_id)->field('number')->select();
        $arr = array_column($activation,'number');
        for ($i = 0 ;$i < $user->store_total_num;$i++){
            $list[$i]['number'] = $i+1;

            if (in_array($i+1,$arr)){
                $list[$i]['status'] = 1;
            }else{
                $list[$i]['status'] = 0;
            }
        }
        $data = [
            'list' => $list,
        ];
        return JsonSuccess($data);
    }

    /**
     * 店铺激活码
     */
    public function store_code(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $number = $request->param('number');
        if (!$number){
            return JsonError('参数获取失败');
        }
        $result = Db::name('store_code')->where('user_id',$this->user_id)->where('number',$number)->find();
        //如果没有code 则新增code
        if (!$result){
            $code = $this->random(8,5);
            $id = Db::name('store_code')->insertGetId([
                'user_id' => $this->user_id,
                'number' => $number,
                'code' => $code,
                'status' => 0
            ]);
            if (!$id){
                return JsonError('激活码生成失败');
            }

        }else{
            $code = $result['code'];
        }

        $data = [
            'code' => $code
        ];
        return JsonSuccess($data);
    }



}