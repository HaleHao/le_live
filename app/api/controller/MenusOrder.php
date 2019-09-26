<?php

namespace app\api\controller;


use app\admin\model\Address;
use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\UsersFollower;
use app\api\service\WeChatPayService;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class MenusOrder extends Base
{
    //订单提交预览页面
    public function preview(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $menu_ids = json_decode($request->param('menu_ids'), true);
        if (!$menu_ids) {
            return JsonError('请选择菜品');
        }


        $price = 0;
        foreach ($menu_ids as $key => $val) {
            $menu = Db::name('menus_reserve')->alias('r')
                ->join('menus m','r.menu_id=m.id','left')
                ->where('menu_id',$val['menu_id'])
                ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date')
                ->find();
            $menus[] = $menu;
            $menus[$key]['cover_image'] = GetConfig('img_prefix','http://www.le-live.com').$menu['cover_image'];
            $price += $menu['price']*$val['amount'];
        }

        $count = count(array_unique(array_column($menus,'user_id')));
        if ($count !== 1){
            return JsonError('菜品不是同一个微厨的');
        }
        $start_min = min(array_column($menus,'start_date'));
        $end_min = min(array_column($menus,'end_date'));
        $total_price = $price;

        //配送地址
        $delivery_address = Db::name('address')
            ->where('user_id', $this->user_id)
            ->where('type', 1)
            ->where('is_default', 1)
            ->find();

        //自提地址
        $pick_address = Db::name('address')
            ->where('user_id', $menu['user_id'])
            ->where('type', 2)
            ->where('is_default', 1)
            ->find();

        $coupon_id = $request->param('coupon_id');
        $coupon = '';
        $preferential_price = 0;
        if ($coupon_id) {
            $coupon = Db::name('users_coupon')->alias('u')
                ->join('coupon c','u.coupon_id=c.id','left')
                ->where('c.start_time','<=',time())
                ->where('c.end_time','>=',time())
                ->where('c.conditions','<=',$total_price)
                ->where('u.coupon_id', $coupon_id)
                ->where('u.user_id', $this->user_id)
                ->field(['c.id,u.user_id,u.status,c.title,c.price,c.conditions'])
                ->find();
            $preferential_price = $total_price - $coupon['price'];
        }

        $num = ($end_min-$start_min)*2+1;

        for ($i = 0; $i < $num; $i++) {
            $e = strtotime($start_min) + (($i + 1) * 1800);
            if (date("H:i", $e) <= date('H:i',strtotime($end_min)+1800)) {
                $arr1[$i]['serving_time'] = date("H:i", $e);
            }
        }

        $data = [
            //用户的默认配送地址
            'delivery_address' => $delivery_address,
            //客户的自提地址自提地址
            'pick_address' => $pick_address,
            //菜品列表
            'menus' => $menus,
            //优惠券
            'coupon' => $coupon,
            //总价
            'total_price' => $total_price,
            //优惠价格
            'preferential_price' => $preferential_price,
            //送达时间
            'serving_tme' => $arr1,
        ];

        return JsonSuccess($data);

    }


    /**
     * 代金券列表
     */
    public function coupon_list(Request $request)
    {

    }


    /**
     * TODO 提单提交
     */
    public function submit(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $menu_ids = json_decode($request->param('menu_ids'), true);
        if (!$menu_ids) {
            return JsonError('参数获取失败');
        }
        //提交订单

        Db::startTrans();
        try {

            $price = 0;
            foreach ($menu_ids as $val) {
                $menu = Db::name('menus_reserve')->alias('r')
                    ->join('menus m','r.menu_id=m.id','left')
                    ->where('menu_id',$val['menu_id'])
                    ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date,r.serving_date')
                    ->find();
                if ($val['amount'] > $menu['total_amount']){
                    return JsonError('菜品库存不足');
                }
                $menus[] = $menu;
                $price += $menu['price']*$val['amount'];

            }
            $total_price = $price;

            $start_min = min(array_column($menus,'start_date'));
            $end_min = min(array_column($menus,'end_date'));

            $count = count(array_unique(array_column($menus,'user_id')));
            if ($count !== 1){
                return JsonError('菜品不是同一个微厨的');
            }

            $delivery_type = $request->param('delivery_type',1);

            $reci = Db::name('address')->where('user_id', $menu['user_id'])
                ->where('type', 2)->where('is_default', 1)->find();

            $send_address = [];
            //配送订单
            if ($delivery_type == 1){

                $address_id = $request->param('address_id');
                if (!$address_id){
                    return JsonError('请选择配送地址');
                }
                $send = Db::name('address')->where('user_id',$this->user_id)
                    ->where('type',1)->where('id',$address_id)->find();
                if (!$send){
                    return JsonError('配送地址出错');
                }
                //用户的收货地址
                $send_address = [
                    'name' => $send['name'],
                    'mobile' => $send['mobile'],
                    'address_id' => $send['id'],
                    'province' => $send['province'],
                    'city' => $send['city'],
                    'district' => $send['district'],
                    'detail' => $send['detail'],
                    'longitude'=> $send['longitude'],
                    'latitude' => $send['latitude'],
                ];
            }

            //商户的取货地址
            $reci_address = [
                'name' => $reci['name'],
                'mobile' => $reci['mobile'],
                'address_id' => $reci['id'],
                'province' => $reci['province'],
                'city' => $reci['city'],
                'district' => $reci['district'],
                'detail' => $reci['detail'],
                'longitude'=> $reci['longitude'],
                'latitude' => $reci['latitude'],
            ];

            //送达时间
            $serving_time = $request->param('serving_time');
            if (!$serving_time){
                return JsonError('请选择送达时间');
            }

            $serving_date = date('H:i',strtotime($serving_time));
            $serving_month = date('Y-m-d',strtotime($serving_time));

            if ($serving_date < $start_min || $serving_date > $end_min){
                return JsonError('送达时间选择错误');
            }

            $menu_time = date('Y-m-d',strtotime($menu['serving_date']));
            if($menu_time < $serving_month){
                return JsonError('送达日期选择错误');
            }

            //优惠券
            $coupon_id = $request->param('coupon_id',0);
            if ($coupon_id) {
                $coupon = Db::name('users_coupon')->alias('u')
                    ->join('coupon c','u.coupon_id=c.id','left')
                    ->where('c.start_time','<=',time())
                    ->where('c.end_time','>=',time())
                    ->where('c.conditions','<=',$total_price)
                    ->where('u.coupon_id', $coupon_id)
                    ->where('u.user_id', $this->user_id)
                    ->where('u.status',0)
                    ->field(['c.id,u.user_id,u.status,c.title,c.price,c.conditions'])
                    ->find();
                if (!$coupon){
                    return JsonError('不能使用优惠券');
                }
                $is_coupon = 1;
                $preferential_price = $total_price - $coupon['price'];
            }else{
                $preferential_price = $total_price;
                $is_coupon = 0;
            }
            $time = time();
            $order = [
                'order_no' => GetOrderNo(),
                'user_id' => $this->user_id,
                'menu_user_id' => $menu['user_id'],
                'order_status' => 0,
                'pay_type' => 1,
                'pay_price' => $preferential_price,
                'total_price' => $total_price,
                'preferential_price' => $preferential_price,
                'is_coupon' => $is_coupon,
                'coupon_id' => $coupon_id,
                'serving_time' => strtotime($serving_time),
                'serving_date' => date('Y-m-d H:i',strtotime($serving_time)),
                'delivery_type' => $delivery_type,
                'reci_address' => json_encode($reci_address),
                'send_address' => json_encode($send_address),
                'submit_time' => $time,
                'create_time' => $time,
                'update_time' => $time,
            ];

            $order_id = Db::name('menus_order')->insertGetId($order);

            foreach($menu_ids as $val){
                $menu = Db::name('menus_reserve')->alias('r')
                    ->join('menus m','r.menu_id=m.id','left')
                    ->where('menu_id',$val['menu_id'])
                    ->field('m.*,r.price,r.total_amount,r.sell_amount')
                    ->find();
                Db::name('menus_order_detail')->insert([
                    'menu_id' => $val['menu_id'],
                    'order_id' => $order_id,
                    'amount' => $val['amount'],
                    'unit_price' => $menu['price'],
                    'total_price' => $menu['price'] * $val['amount']
                ]);
                Db::name('menus_reserve')->where('menu_id',$val['menu_id'])->update([
                    'total_amount' => $menu['total_amount']-$val['amount'],
                    'sell_amount' => $menu['sell_amount'] + $val['amount']
                ]);

            }
            Db::name('users_coupon')->where('user_id',$this->user_id)->where('coupon_id',$coupon_id)->update([
                'status' => 1
            ]);

            $data = [
                'order_id' => $order_id,
                'order_no' => $order['order_no']
            ];

            Db::commit();

            return JsonSuccess($data);
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('订单生成失败');
        }
    }


    /**
     * 订单支付
     */
    public function pay(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $order_no = $request->param('order_no');
        $order_id = $request->param('order_id');
        if (!$order_no || !$order_id){
            return JsonError('参数获取失败');
        }
        $order = Db::name('menus_order')
            ->where('id',$order_id)
            ->where('order_no',$order_no)
            ->where('user_id',$this->user_id)
            ->where('order_status',0)
            ->where('pay_status',0)
            ->find();
        if (!$order){
            return JsonError('订单获取失败');
        }

        $user = Users::where('id',$this->user_id)->find();
        $openid = $user['openid'];

        $notifyUrl = '';

        $pay = new WeChatPayService();
        $result = $pay->Mini_Pay($order['order_no'],$order['pay_price'],$openid,$notifyUrl,'购买菜品');
        if ($result){
            return JsonSuccess($result);
        }
        return JsonError('支付订单生成失败');


    }

    /**
     * 订单列表
     */
    public function lists(Request $request)
    {

    }
}