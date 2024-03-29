<?php

namespace app\api\controller;


use app\admin\model\Address;
use app\admin\model\Admin;
use app\admin\model\CreditLog;
use app\admin\model\EarningsLog;
use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\UsersFollower;
use app\admin\model\WalletLog;
use app\api\service\AliyunSmsService;
use app\api\service\UUDeliveryService;
use app\api\service\WeChatPayService;
use phpDocumentor\Reflection\DocBlockFactory;
use think\Db;
use think\Exception;
use think\Request;
use app\admin\model\MenusOrder as Order;
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
        $box_price = 0;
        $menu_price = 0;
        foreach ($menu_ids as $key => $val) {
            $menu = Db::name('menus_reserve')->alias('r')
                ->join('menus m', 'r.menu_id=m.id', 'left')
                ->where('menu_id', $val['menu_id'])
                ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date,r.serving_date,r.explain,r.finish_time')
                ->find();
            $menus[] = $menu;
            $menus[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $menu['cover_image'];
            $menus[$key]['price'] = $this->menus_price($menu['price']);

            $price += $menu['price'] * $val['amount'];

            $menu_price +=  $this->menus_price($menu['price']) * $val['amount'];

            $box_price += GetConfig('box_price', 1) * $val['amount'];

            $finish_time[] = $menu['finish_time'];
        }


        $count = count(array_unique(array_column($menus, 'user_id')));

        if ($count !== 1) {
            return JsonError('菜品不是同一个微厨的');
        }


        //配送地址
        $delivery_address = Db::name('address')
            ->where('user_id', $this->user_id)
            ->where('type', 1)
            ->where('is_default', 1)
            ->find();

        $address_id = $request->param('address_id');
        if ($address_id) {
            $delivery_address = Db::name('address')
                ->where('user_id', $this->user_id)
                ->where('type', 1)
                ->where('id', $address_id)
                ->find();
        }

        //自提地址
        $pick_address = Db::name('address')
            ->where('user_id', $menu['user_id'])
            ->where('type', 2)
            ->find();
        if (!$pick_address) {
            $is_pick = 0;
        } else {
            $is_pick = $pick_address['is_pick'];
        }

        //判断是否自提
        $delivery_type = $request->param('delivery_type');
        $delivery_price = 0;
        if ($delivery_type == 1) {
//            if ($delivery_address && $pick_address) {
//                //计算配送费
//                $delivery = new UUDeliveryService();
//                $price_token = $delivery->GetDeliveryPrice($pick_address, $delivery_address);
//                if (!$price_token) {
//                    return JsonError('订单配送费计算失败');
//                }
//                if ($price_token['return_code'] != 'ok') {
//                    return JsonError($price_token['return_msg']);
//                }
//                $delivery_price = $price_token['need_paymoney'];
//            }
            $delivery_price = 5;
        }
        //计算总价 餐盒费+菜品费+配送费
        $total_price = $menu_price + $delivery_price;

        $coupon_id = $request->param('coupon_id');
        $coupon = '';
        if ($coupon_id) {
            $coupon = Db::name('users_coupon')->alias('u')
                ->join('coupon c', 'u.coupon_id=c.id', 'left')
                ->where('c.start_time', '<=', time())
                ->where('c.end_time', '>=', time())
                ->where('c.conditions', '<=', $total_price)
                ->where('u.coupon_id', $coupon_id)
                ->where('u.user_id', $this->user_id)
                ->field(['c.id,u.user_id,u.status,c.title,c.price,c.conditions'])
                ->find();
            $preferential_price = $total_price - $coupon['price'];
        } else {
            $preferential_price = $total_price;
        }


        $start_min = min(array_column($menus, 'start_date'));
        $end_min = min(array_column($menus, 'end_date'));
        if($end_min == 0){
            $end_min = '24:00';
        }
        $num = abs($end_min - $start_min) * 2 + 1;
        for ($i = 0; $i < $num; $i++) {
            $e = strtotime($start_min) + (($i + 1) * 1800);
//            if (date("H:i", $e) <= date('H:i', strtotime($end_min) + 1800)) {
                $arr1[$i] = date("H:i", $e);
//            }
        }

//        $num1 = ($end_min - $start_min) +

//        for ($i=0;$i < $num;$i++){
//            $e = strtotime($st)
//        }
        $max_time = max($finish_time);
//        var_dump($finish_time);
//        exit;
        $arr2 = [];
        $num1 = abs($end_min - $start_min) + $max_time + 1;

        for ($i = 0; $i < $num1; $i++) {
            $e = strtotime($start_min) + ($i * 3600);
//            if (date("H:i", $e) <= date('H:i', strtotime($end_min) + 3600)) {
                $arr2[$i] = date("H:i", $e);
//            }
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
            //菜品价格
            'menu_price' => $menu_price,
            //送达时间
            'serving_tme' => $arr1,

            'finish_time' => $arr2,

            'serving_date' => $menu['serving_date'],

            'start_date' => $start_min,

            'end_date' => $end_min,

            'is_pick' => $is_pick,

            'explain' => $menu['explain'],

            'max_time' => $max_time,

            'delivery_price' => $delivery_price,

            'box_price' => $box_price
        ];
        return JsonSuccess($data);
    }

    /**
     * 订单可使用的优惠券列表
     */
    public function coupon_workable(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $total_price = $request->param('total_price', 0);

        $list = Db::name('users_coupon')->alias('u')
            ->join('coupon c', 'c.id=u.coupon_id', 'left')
            ->where('u.user_id', $this->user_id)
            ->where('c.conditions', '<=', $total_price)
            ->where('c.end_time', '>', time())
            ->where('u.status', 0)
            ->field(['c.id,c.title,c.price,c.conditions,c.start_date,c.start_time,c.end_date,c.end_time'])
            ->select();

        $data = [
            'list' => $list
        ];

        return JsonSuccess($data);
    }

    /**
     * 提单提交
     */
    public function submit(Request $request)
    {
        if (!$this->user_id) {
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
            $menu_price = 0;
            $box_price = 0;
            foreach ($menu_ids as $val) {
                $menu = Db::name('menus_reserve')->alias('r')
                    ->join('menus m', 'r.menu_id=m.id', 'left')
                    ->where('menu_id', $val['menu_id'])
                    ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date,r.serving_date,r.finish_time')
                    ->find();
                if ($val['amount'] > $menu['total_amount']) {
                    return JsonError('菜品库存不足');
                }

                if ($menu['user_id'] == $this->user_id) {
                    return JsonError('不能预约自己的菜品');
                }
                $menus[] = $menu;
                $finish_time[] = $menu['finish_time'];
                $price += $menu['price'] * $val['amount'];
                $menu_price += $this->menus_price($menu['price']) * $val['amount'];
            }



            $count = count(array_unique(array_column($menus, 'user_id')));
            if ($count !== 1) {
                return JsonError('菜品不是同一个微厨的');
            }

            $delivery_type = $request->param('delivery_type', 1);

            $reci = Db::name('address')->where('user_id', $menu['user_id'])
                ->where('type', 2)->where('is_default', 1)->find();

            $send_address = [];
            //配送订单


            $delivery_price = 0;
            if ($delivery_type == 1) {

                $address_id = $request->param('address_id');
                if (!$address_id) {
                    return JsonError('请选择配送地址');
                }
                $send = Db::name('address')->where('user_id', $this->user_id)
                    ->where('type', 1)->where('id', $address_id)->find();
                if (!$send) {
                    return JsonError('配送地址出错');
                }

                if ($send && $reci) {
                    //计算配送费
//                    $delivery = new UUDeliveryService();
//                    $price_token = $delivery->GetDeliveryPrice($send, $reci);
//                    if (!$price_token) {
//                        return JsonError('订单配送费计算失败');
//                    }
//                    if ($price_token['return_code'] != 'ok') {
//                        return JsonError($price_token['return_msg']);
//                    }
                    $delivery_price = 5;
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
                    'longitude' => $send['longitude'],
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
                'longitude' => $reci['longitude'],
                'latitude' => $reci['latitude'],
            ];

            //送达时间
            $serving_time = $request->param('serving_time');
            if (!$serving_time) {
                return JsonError('请选择送达时间');
            }

            $start_min = min(array_column($menus, 'start_date'));
            $end_min = min(array_column($menus, 'end_date'));

            if ($end_min == 0){
                $end_min = "24:00";
            }

            $serving_date = date('H:i', strtotime($serving_time));
            $serving_month = date('Y-m-d', strtotime($serving_time));

            if ($delivery_type == 1) {
                $end_min1 = date('H:i', strtotime($end_min) + (30 * 60));
                if ($serving_date < $start_min || $serving_date > $end_min1) {
                    return JsonError('送达时间选择错误');
                }
            }

            if ($delivery_type == 2) {
                $max_time = max($finish_time);
                $end_min1 = date('H:i', strtotime($end_min) + (3600 * $max_time));
                if ($serving_date < $start_min || $serving_date > $end_min1) {
                    return JsonError('送达时间选择错误');
                }
            }

            $menu_time = date('Y-m-d', strtotime($menu['serving_date']));
            if ($menu_time < $serving_month) {
                return JsonError('送达日期选择错误');
            }

            //优惠券

            $total_price = $menu_price + $delivery_price;
            $coupon_id = $request->param('coupon_id', 0);
            if ($coupon_id) {
                $coupon = Db::name('users_coupon')->alias('u')
                    ->join('coupon c', 'u.coupon_id=c.id', 'left')
                    ->where('c.start_time', '<=', time())
                    ->where('c.end_time', '>=', time())
                    ->where('c.conditions', '<=', $total_price)
                    ->where('u.coupon_id', $coupon_id)
                    ->where('u.user_id', $this->user_id)
                    ->where('u.status', 0)
                    ->field(['c.id,u.user_id,u.status,c.title,c.price,c.conditions'])
                    ->find();
                if (!$coupon) {
                    return JsonError('不能使用优惠券');
                }
                $is_coupon = 1;
                $preferential_price = $total_price - $coupon['price'];
            } else {
                $preferential_price = $total_price;
                $is_coupon = 0;
            }
            $time = time();
            $order = [
                'order_no' => GetOrderNo(),
                'user_id' => $this->user_id,
                'chef_id' => $menu['user_id'],
                'order_status' => 0,
                'pay_type' => 1,
                'pay_price' => $preferential_price,
                'total_price' => $total_price,
                'preferential_price' => $preferential_price,
                'delivery_price' => $delivery_price,
                'box_price' => $box_price,
                'menu_price' => $menu_price,
                'is_coupon' => $is_coupon,
                'coupon_id' => $coupon_id,
                'serving_time' => strtotime($serving_time),
                'serving_date' => date('Y-m-d H:i', strtotime($serving_time)),
                'delivery_type' => $delivery_type,
                'reci_address' => json_encode($reci_address),
                'send_address' => json_encode($send_address),
                'remark' => $request->param('remark'),
                'submit_time' => $time,
                'create_time' => $time,
                'update_time' => $time,
            ];

            $order_id = Db::name('menus_order')->insertGetId($order);

            foreach ($menu_ids as $val) {
                $menu = Db::name('menus_reserve')->alias('r')
                    ->join('menus m', 'r.menu_id=m.id', 'left')
                    ->where('menu_id', $val['menu_id'])
                    ->field('m.*,r.price,r.total_amount,r.sell_amount')
                    ->find();
                Db::name('menus_order_detail')->insert([
                    'menu_id' => $val['menu_id'],
                    'order_id' => $order_id,
                    'title' => $menu['title'],
                    'cover_image' => $menu['cover_image'],
                    'amount' => $val['amount'],
                    'unit_price' => $menu['price'],
                    'total_price' => $menu['price'] * $val['amount']
                ]);
                Db::name('menus_reserve')->where('menu_id', $val['menu_id'])->update([
                    'total_amount' => $menu['total_amount'] - $val['amount'],
                    'sell_amount' => $menu['sell_amount'] + $val['amount']
                ]);

            }
            Db::name('users_coupon')->where('user_id', $this->user_id)->where('coupon_id', $coupon_id)->update([
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
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_no = $request->param('order_no');
        $order_id = $request->param('order_id');
        if (!$order_no || !$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Db::name('menus_order')
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
        $notifyUrl = GetConfig('app_url') . '/api/notify/wechat';
        $pay = new WeChatPayService();
        $result = $pay->Mini_Pay($order['order_no'], $order['pay_price'], $openid, $notifyUrl, '购买菜品');
        if ($result) {
            return JsonSuccess($result);
        }
        return JsonError('支付订单生成失败');

    }

    /**
     * 我卖出的
     */
    public function sell_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $type = $request->param('type', 1);
        $page = $request->param('page', 1);
        //待发货订单
        if ($type == 1) {
            $list = Order::with('menus')
                ->where('order_status', 1)
                ->where('chef_id', $this->user_id)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)
                ->select();
            $count = Order::where('order_status', 1)->where('chef_id', $this->user_id)->count();
        }

        //待收货订单
        if ($type == 2) {
            $list = Order::with('menus')
                ->where('order_status', 2)
                ->where('chef_id', $this->user_id)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)
                ->select();
            $count = Order::where('order_status', 1)->where('chef_id', $this->user_id)->count();
        }

        //已完成订单
        if ($type == 3) {
            $list = Order::with('menus')
                ->where('order_status', 4)
                ->where('chef_id', $this->user_id)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)
                ->select();
            $count = Order::where('order_status', 1)->where('chef_id', $this->user_id)->count();
        }

        //退款订单
        if ($type == 4) {
            $list = Order::with('menus')
                ->where('order_status', 5)
                ->where('chef_id', $this->user_id)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)
                ->select();
            $count = Order::where('order_status', 1)->where('chef_id', $this->user_id)->count();
        }

        //自提订单
        if ($type == 5) {
            $list = Order::with('menus')
                ->where('order_status', 3)
                ->where('chef_id', $this->user_id)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)
                ->select();
            $count = Order::where('order_status', 1)->where('chef_id', $this->user_id)->count();
        }

        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }

    /**
     * 我卖出的订单详情
     */
    public function sell_detail(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $order_id = $request->param('order_id');

        if (!$order_id) {
            return JsonError('参数获取失败');
        }

        $order = Order::with('menus')
            ->where('id', $order_id)
            ->where('chef_id', $this->user_id)
            ->find();
        $order['reci_address'] = json_decode($order['reci_address'], true);
        $order['send_address'] = json_decode($order['send_address'], true);
        if (!$order) {
            return JsonError('数据获取失败');
        }
        $data = [
            'detail' => $order
        ];
        return JsonSuccess($data);
    }

    /**
     * 确认发货 对接UU配送
     */
    public function sell_deliver(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Order::where('id', $order_id)->where('chef_id', $this->user_id)->find();
        if (!$order) {
            return JsonError('数据获取失败');
        }
        if ($order->order_status != 1) {
            return JsonError('当前订单不能发货');
        }
        //对接UU配送
        $delivery = new UUDeliveryService();
        //获取价格
        $price_token = $delivery->GetOrderPrice($order);
        if (!$price_token) {
            return JsonError('订单配送费计算失败');
        }
        if ($price_token['return_code'] != 'ok') {
            return JsonError($price_token['return_msg']);
        }

        $result = $delivery->AddOrder($order, $price_token);
        if (!$result) {
            return JsonError('发布配送订单失败');
        }
        if ($result['return_code'] != 'ok') {
            return JsonError($price_token['return_msg']);
        }
        $order->order_code = $result['ordercode'];
        $order->order_status = 2;
        if ($order->save()) {
            return JsonSuccess([], '发货成功');
        }
        return JsonError('发货失败');

    }

    /**
     * 我卖出的取消订单
     */
    public function sell_cancel(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Order::with('menus')->where('id', $order_id)->where('chef_id', $this->user_id)->find();
        if (!$order) {
            return JsonError('数据获取失败');
        }
        if ($order->order_status != 1) {
            return JsonError('当前订单不能取消');
        }
        Db::startTrans();
        try {


            //给用户退款
            $wechat = new WeChatPayService();

            //提前一天取消免责 否则扣除信誉度
            $res = $wechat->Refund($order->order_no, $order->pay_price, '取消订单');

            if (!$res) {
                return JsonError('退款失败');
            }




            if ($order->serving_date >= date("Y-m-d H:i", strtotime("+1 day"))) {
                //免责

            } elseif ($order->serving_date >= date('Y-m-d H:i', strtotime("+6 hour"))) {

                $log = new CreditLog();
                $log->user_id = $this->user_id;
                $log->content = '取消订单(' . $order->order_no . ')';
                $log->number = (int)$order->pay_price / 10;
                $log->type = 2;
                $log->save();

                $user->setDec('credit_line', (int)$order->pay_price / 10);
                $user->save();
            } else {
                $log = new CreditLog();
                $log->user_id = $this->user_id;
                $log->content = '取消订单(' . $order->order_no . ')';
                $log->number = (int)$order->pay_price / 20;
                $log->type = 2;
                $log->save();
                $user->setDec('credit_line', (int)$order->pay_price / 20);
                $user->save();
            }

            if ($order->coupon_id) {
                //归还优惠券
                Db::name('users_coupon')
                    ->where('user_id', $order->user_id)
                    ->where('coupon_id', $order->coupon_id)->update([
                        'status' => 0
                    ]);
                //归还商品库存

            }
            foreach ($order->menus as $val) {
                Db::name('menus_reserve')
                    ->where('menu_id', $val->menu_id)
                    ->where('user_id', $order->chef_id)
                    ->setInc('total_amount', $val->amount);
                Db::name('menus_reserve')
                    ->where('menu_id', $val->menu_id)
                    ->where('user_id', $order->chef_id)
                    ->setDec('sell_amount', $val->amount);
            }

            $order->order_status = 5;

            $order->save();



//            }else{
//                Db::rollback();
//                return JsonError('订单退款失败');
//            }
            Db::commit();

            //给用户发送退款短信
            $send_user = Users::where('id',$order->user_id)->find();
            if ($send_user){
                if ($send_user->mobile){
                    $sms = new AliyunSmsService();
                    $data = [
                        'name' => $order->order_no
                    ];
                    $sms->sendSms($send_user->mobile,'SMS_176375238',$data);
                }
            }

            return JsonSuccess([], '取消订单成功');

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('取消订单失败');
        }
    }

    /**
     * 我预约的-列表
     */
    public function buy_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $page = $request->param('page', 1);
        $type = $request->param('type', 1);
        $list = [];
        $count = 0;
        //待付款
        if ($type == 1) {
            $list = Order::with('menus')
                ->where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)->select();
            $count = Order::where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->count();
        }
        //待发货
        if ($type == 2) {
            $list = Order::with('menus')
                ->where('user_id', $this->user_id)
                ->where('order_status', 1)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)->select();
            $count = Order::where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->count();
        }
        //待收货
        if ($type == 3) {
            $list = Order::with('menus')
                ->where('user_id', $this->user_id)
                ->where('order_status', 2)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)->select();
            $count = Order::where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->count();
        }
        //退款
        if ($type == 4) {
            $list = Order::with('menus')
                ->where('user_id', $this->user_id)
                ->where('order_status', 5)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)->select();
            $count = Order::where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->count();
        }

        //自提
        if ($type == 5) {
            $list = Order::with('menus')
                ->where('user_id', $this->user_id)
                ->where('order_status', 3)
                ->order('create_time', 'desc')
                ->field(['id', 'order_no', 'order_status', 'pay_price', 'total_price', 'preferential_price'])
                ->page($page, 10)->select();
            $count = Order::where('user_id', $this->user_id)
                ->where('order_status', 0)
                ->count();
        }


        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }

    /**
     * 我预约的-详情
     */
    public function buy_detail(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $order_id = $request->param('order_id');

        if (!$order_id) {
            return JsonError('参数获取失败');
        }

        $order = Order::with('menus')
            ->where('id', $order_id)
            ->where('user_id', $this->user_id)
            ->find();
        if (!$order) {
            return JsonError('数据获取失败');
        }
        $order['send_address'] = json_decode($order['send_address'], true);
        $order['reci_address'] = json_decode($order['reci_address'], true);

        $data = [
            'detail' => $order
        ];
        return JsonSuccess($data);
    }

    /**
     * 我预约的-取消
     */
    public function buy_cancel(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('参数获取失败');
        }

        $order = Order::with('menus')->where('id', $order_id)->where('user_id', $this->user_id)->find();
        if (!$order) {
            return JsonError('数据获取失败');
        }

        if ($order->order_status == 0) {

            Db::startTrans();
            try {

//                $wechat = new WeChatPayService();
//

                if ($order->coupon_id) {
                    //归还优惠券
                    Db::name('users_coupon')->where('coupon_id', $order->coupon_id)->where('user_id', $this->user_id)->update([
                        'status' => 0
                    ]);
                }
                foreach ($order->menus as $val) {
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $order->chef_id)
                        ->setInc('total_amount', $val->amount);
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $order->chef_id)
                        ->setDec('sell_amount', $val->amount);
                }

                //删除订单
                $order->delete();
                Db::commit();
                return JsonSuccess([], '取消订单成功');
            } catch (Exception $exception) {
                Db::rollback();
                return JsonError('取消订单失败');
            }

        }
        if ($order->order_status == 1) {

            Db::startTrans();
            try {
                if ($order->serving_date >= date('Y-m-d H:i', strtotime("+3 hour"))) {
                    //TODO 给用户退款
                    $wechat = new WeChatPayService();
                    $res = $wechat->Refund($order->order_no, $order->pay_price, '取消订单');
                    if (!$res) {
                        return JsonError('退款失败');
                    }


                    $send_user = Users::where('id',$order->user_id)->find();
                    if ($send_user){
                        if ($send_user->mobile){
                            $sms = new AliyunSmsService();
                            $data = [
                                'name' => $order->order_no
                            ];
                            $sms->sendSms($send_user->mobile,'SMS_176375238',$data);
                        }
                    }

                    if ($order->coupon_id) {
                        Db::name('users_coupon')
                            ->where('coupon_id', $order->coupon_id)
                            ->where('user_id', $this->user_id)->update([
                                'status' => 0
                            ]);
                    }
                    foreach ($order->menus as $val) {
                        Db::name('menus_reserve')
                            ->where('menu_id', $val->menu_id)
                            ->where('user_id', $order->chef_id)
                            ->setInc('total_amount', $val->amount);
                        Db::name('menus_reserve')
                            ->where('menu_id', $val->menu_id)
                            ->where('user_id', $order->chef_id)
                            ->setDec('sell_amount', $val->amount);
                    }
                    $order->order_status = 5;
                    $order->save();



                    Db::commit();

                    //给用户发送退款短信



                    return JsonSuccess([], '取消订单成功');
                } else {
                    return JsonError('该订单超过时间不能取消');
                }

            } catch (Exception $exception) {
                Db::rollback();
                return JsonError('取消订单失败');
            }

        }
        return JsonError('当前订单不能取消');

    }

    /**
     * 我预约的-确认收货
     */
    public function buy_receipt(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');

        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        Db::startTrans();
        try {
            $order = Order::where('user_id', $this->user_id)->where('id', $order_id)->find();
            if ($order->order_status != 2) {
                return JsonError('当前订单不能确认收货');
            }

            $chef = Users::where('id', $order->chef_id)->find();

            if ($chef->first_user_id) {
                //一级分销奖励
                $first_user = Users::where('id', $chef->first_user_id)->find();
                if ($first_user) {
                    if ($first_user->is_enter == 1) {
                        $money = sprintf("%.2f", $order->menu_price * (GetConfig('first_order_ratio', 1) / 100));
                        $log = new EarningsLog();
                        $log->user_id = $chef->first_user_id;
                        $log->content = '订单（' . $order->order_no . '）奖励';
                        $log->type = 2;
                        $log->status = 1;
                        $log->money = $money;
                        $log->save();
                        $first_user->setInc('store_balance', $money);
                    }
                }
                //二级分销奖励
                $second_user = Users::where('id', $chef->second_user_id)->find();
                if ($second_user) {
                    if ($second_user->is_enter == 1) {
                        $money = sprintf("%.2f", $order->menu_price * (GetConfig('second_order_ratio', 0.5) / 100));
                        $log = new EarningsLog();
                        $log->user_id = $chef->second_user_id;
                        $log->content = '订单（' . $order->order_no . '）奖励';
                        $log->type = 2;
                        $log->status = 1;
                        $log->money = $money;
                        $log->save();
                        $second_user->setInc('store_balance', $money);
                    }
                }
            }

            $log = new CreditLog();
            $log->user_id = $chef->id;
            $log->content = '完成订单(' . $order->order_no . ')';
            $log->number = 1;
            $log->type = 1;
            $log->save();


            //微厨的余额明细
            $log = new WalletLog();
            $log->user_id = $order->chef_id;
            $log->content = '订单（' . $order->order_no . '）';
            $log->money = $order->menu_price;
            $log->type = 1;
            $log->save();

            $chef->setInc('credit_line', 1);
            $chef->setInc('balance', $order->menu_price);

            $order->order_status = 4;
            $order->save();
            Db::commit();
            return JsonSuccess([], '确认收货成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('确认收货失败');
        }
    }

    /**
     * 确认提货
     */
    public function buy_delivery(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');

        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        Db::startTrans();
        try {
            $order = Order::where('user_id', $this->user_id)->where('id', $order_id)->find();
            if ($order->order_status != 3) {
                return JsonError('当前订单不能确认提货');
            }
            $chef = Users::where('id', $order->chef_id)->find();
            //微厨的余额明细


            if ($chef->first_user_id) {
                //一级分销奖励
                $first_user = Users::where('id', $chef->first_user_id)->find();
                if ($first_user) {
                    if ($first_user->is_enter == 1) {
                        $money = sprintf("%.2f", $order->menu_price * (GetConfig('first_order_ratio', 1) / 100));
                        $log = new EarningsLog();
                        $log->user_id = $chef->first_user_id;
                        $log->content = '订单（' . $order->order_no . '）奖励';
                        $log->type = 2;
                        $log->status = 1;
                        $log->money = $money;
                        $log->save();
                        $first_user->setInc('store_balance', $money);
                    }
                }
                //二级分销奖励
                $second_user = Users::where('id', $chef->second_user_id)->find();
                if ($second_user) {
                    if ($second_user->is_enter == 1) {
                        $money = sprintf("%.2f", $order->menu_price * (GetConfig('second_order_ratio', 0.5) / 100));
                        $log = new EarningsLog();
                        $log->user_id = $chef->second_user_id;
                        $log->content = '订单（' . $order->order_no . '）奖励';
                        $log->type = 2;
                        $log->status = 1;
                        $log->money = $money;
                        $log->save();
                        $second_user->setInc('store_balance', $money);
                    }
                }
            }

            $log = new CreditLog();
            $log->user_id = $chef->id;
            $log->content = '完成订单(' . $order->order_no . ')';
            $log->number = 1;
            $log->type = 1;
            $log->save();

            $log = new WalletLog();
            $log->user_id = $order->chef_id;
            $log->content = '订单（' . $order->order_no . '）';
            $log->money = $order->menu_price;
            $log->type = 1;
            $log->save();

            $chef->setInc('credit_line', 1);
            $chef->setInc('balance', $order->menu_price);
            $order->order_status = 4;
            $order->save();
            Db::commit();
            return JsonSuccess([], '确认提货成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('确认提货失败');
        }
    }

    /**
     * 已品尝列表
     */
    public function taste_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Order::with('menus')->where('order_status', 4)->where('user_id', $this->user_id)->page($page, 10)->select();
        $count = Order::with('menus')->where('order_status', 4)->where('user_id', $this->user_id)->count();

        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);
    }

    /**
     * 已品尝常评价
     */
    public function taste_comment(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $data = $request->param();
        $validate = new Validate([
            ['content', 'require', '评论内容不能为空'],
            ['menu_id', 'require', '菜品不能为空'],
        ]);
        if (!$validate->check($data)) {
            return JsonError($validate->getError());
        }
        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('订单ID获取失败');
        }
        Db::startTrans();
        try {
            $order = Db::name('menus_order')->where('id', $order_id)->find();
            if (!$order) {
                return JsonError('订单获取失败');
            }
            $menu = MenusModel::where('id', $data['menu_id'])->find();
            if (!$menu) {
                return JsonError('该菜谱不存在');
            }

            $parent_id = $request->param('parent_id', 0);

            $comment = new MenusComment();
            $comment->content = $data['content'];
            $comment->menu_id = $data['menu_id'];
            $comment->user_id = $this->user_id;
            $comment->parent_id = $parent_id;
            $comment->to_user_id = $menu->user_id;
            $comment->order_id = $order_id;
            $comment->type = 2;

            if ($images = $request->param('images')) {
                $comment->images = json_encode(json_decode($images, true));
            }
            $comment->save();

            //修改详情状态
            Db::name('menus_order_detail')->where('order_id', $order_id)->where('menu_id', $data['menu_id'])->update([
                'is_comment' => 1
            ]);
            Db::name('menus_order')->where('id', $order_id)->update([
                'is_comment' => 1
            ]);
            Db::commit();
            return JsonSuccess([], '评论成功');

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('评论失败');
        }

    }

    /**
     * 已品尝评论
     */
    public function comment_detail(Request $request)
    {

        if (!$this->user_id) {
            return JsonLogin();
        }

        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('订单ID获取失败');
        }

        $order = Order::where('id', $order_id)->find();
        if (!$order) {
            return JsonError('订单获取失败');
        }

        $menu_id = $request->param('menu_id');
        if (!$menu_id) {
            return JsonError('菜品ID获取失败');
        }

        $menu = Db::name('menus')->alias('m')
            ->join('menus_reserve r', 'm.id=r.menu_id', 'left')
            ->field('m.id,m.title,m.cover_image,m.introduce,r.price')
            ->where('m.id', $menu_id)
            ->find();
        $menu['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $menu['cover_image'];

        $comment = Db::name('menus_comment')->alias('c')
            ->join('users u', 'c.user_id=u.id', 'left')
            ->field('c.id,c.content,c.user_id,c.images,u.nickname,u.avatar,c.create_time')
            ->where('c.order_id', $order_id)
            ->where('type', 2)
            ->find();


        $replay = [];

        if ($comment) {

            $comment['images'] = json_decode($comment['images'], true);
            if ($comment['images']) {
                foreach ($comment['images'] as &$image) {
                    $image = GetConfig('img_prefix', 'http://www.le-live.com') . $image;
                }
            } else {
                $comment['images'] = [];
            }

            if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $comment['avatar'])) {
                $comment['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $comment['avatar'];
            }
            $comment['create_time'] = date('m-d H:i', $comment['create_time']);
            $replay = Db::name('menus_comment')->alias('c')
                ->join('users u', 'c.user_id=u.id', 'left')
                ->field('c.id,c.content,c.user_id,c.images,u.nickname,u.avatar,c.create_time')
                ->where('c.menu_id', $menu_id)
                ->where('c.parent_id', $comment['id'])
                ->where('c.user_id', $order->chef_id)
                ->find();
            if (!$replay) {
                $replay = [];
            } else {
                $replay['create_time'] = date('m-d H:i', $replay['create_time']);
                if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $replay['avatar'])) {
                    $replay['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $replay['avatar'];
                }
            }
        } else {
            $comment = [];
        }

        $data = [
            'menu' => $menu,
            'comment' => $comment,
            'replay' => $replay
        ];

        return JsonSuccess($data);


    }
}