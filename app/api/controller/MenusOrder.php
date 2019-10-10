<?php

namespace app\api\controller;


use app\admin\model\Address;
use app\admin\model\Admin;
use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\UsersFollower;
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
        foreach ($menu_ids as $key => $val) {
            $menu = Db::name('menus_reserve')->alias('r')
                ->join('menus m', 'r.menu_id=m.id', 'left')
                ->where('menu_id', $val['menu_id'])
                ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date,r.serving_date')
                ->find();
            $menus[] = $menu;
            $menus[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $menu['cover_image'];
            $price += $menu['price'] * $val['amount'];
        }

        $count = count(array_unique(array_column($menus, 'user_id')));
        if ($count !== 1) {
            return JsonError('菜品不是同一个微厨的');
        }
        $start_min = min(array_column($menus, 'start_date'));
        $end_min = min(array_column($menus, 'end_date'));
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
            ->find();

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

        $num = ($end_min - $start_min) * 2 + 1;

        for ($i = 0; $i < $num; $i++) {
            $e = strtotime($start_min) + (($i + 1) * 1800);
            if (date("H:i", $e) <= date('H:i', strtotime($end_min) + 1800)) {
                $arr1[$i] = date("H:i", $e);
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

            'serving_date' => $menu['serving_date'],
            'start_date' => $start_min,
            'end_date' => $end_min,
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
     * TODO 提单提交
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
            foreach ($menu_ids as $val) {
                $menu = Db::name('menus_reserve')->alias('r')
                    ->join('menus m', 'r.menu_id=m.id', 'left')
                    ->where('menu_id', $val['menu_id'])
                    ->field('m.*,r.price,r.total_amount,r.start_date,r.end_date,r.serving_date')
                    ->find();
                if ($val['amount'] > $menu['total_amount']) {
                    return JsonError('菜品库存不足');
                }
                $menus[] = $menu;
                $price += $menu['price'] * $val['amount'];

            }
            $total_price = $price;

            $start_min = min(array_column($menus, 'start_date'));
            $end_min = min(array_column($menus, 'end_date'));

            $count = count(array_unique(array_column($menus, 'user_id')));
            if ($count !== 1) {
                return JsonError('菜品不是同一个微厨的');
            }

            $delivery_type = $request->param('delivery_type', 1);

            $reci = Db::name('address')->where('user_id', $menu['user_id'])
                ->where('type', 2)->where('is_default', 1)->find();

            $send_address = [];
            //配送订单
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

            $serving_date = date('H:i', strtotime($serving_time));
            $serving_month = date('Y-m-d', strtotime($serving_time));

            if ($serving_date < $start_min || $serving_date > $end_min) {
                return JsonError('送达时间选择错误');
            }

            $menu_time = date('Y-m-d', strtotime($menu['serving_date']));
            if ($menu_time < $serving_month) {
                return JsonError('送达日期选择错误');
            }

            //优惠券
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

        $notifyUrl = '';

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
     * TODO 确认发货 对接UU配送
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
        $order->order_status = 2;
        if ($order->save()) {
            return JsonSuccess([], '发货成功');
        }
        return JsonError('发货失败');

    }


    /**
     * 我卖出的取消订单
     *  TODO 给用户退款
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


            //TODO 给用户退款
            $wechat = new WeChatPayService();


            //提前一天取消免责 否则扣除信誉度


            if ($order->serving_date >= date("Y-m-d H:i", strtotime("+1 day"))) {
                //免责

            } elseif ($order->serving_date >= date('Y-m-d H:i', strtotime("+6 hour"))) {
                $user->setDec('credit_line', (int)$order->pay_price / 10);
                $user->save();
            } else {
                return JsonError('该订单超过时间不能取消');
            }

//            $result = $wechat->Refund($order->pay_no,$order->order_no,$order->pay_price,'预约订单取消','');
//            if ($result){
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
     * TODO 给用户退款
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
            $chef->setInc('balance', $order->total_price);
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
            $chef->setInc('balance', $order->total_price);
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
        $page = $request->param('page', 10);
        $list = Order::where('order_id', 4)->where('user_id', $this->user_id)->page($page, 10)->select();
        $count = Order::where('order_id', 4)->where('user_id', $this->user_id)->count();

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
        try {
            $order = Db::name('menus_order')->where('id',$order_id)->find();
            if (!$order){
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
            $comment->type = 2;

            if ($images = $request->param('images')) {
                $comment->images = json_decode($images);
            }
            $comment->save();

            //修改详情状态
            Db::name('menus_order_detail')->where('order_id',$order_id)->where('menu_id',$data['menu_id'])->update([
                'is_comment' => 1
            ]);
            Db::name('menus_order')->where('id',$order_id)->update([
                'is_comment' => 1
            ]);
            return JsonSuccess([], '评论成功');

        } catch (Exception $exception) {
            return JsonError('评论失败');
        }


    }
    

}