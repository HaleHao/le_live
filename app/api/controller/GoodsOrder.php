<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/9/28 0028
 * Time: 14:45
 */

namespace app\api\controller;

use app\admin\model\GoodsOrder as Order;
use app\admin\model\GoodsSpec;
use app\admin\model\Users;
use app\admin\model\WalletLog;
use app\api\service\WeChatPayService;
use think\Db;
use think\Exception;
use think\Request;

class GoodsOrder extends Base
{
    /**
     * 订单预览
     */
    public function preview(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $goods_id = $request->param('goods_id');
        if (!$goods_id) {
            return JsonError('参数获取失败');
        }
        $goods = \app\admin\model\Goods::where('id', $goods_id)->find();
        if (!$goods) {
            return JsonError('数据获取失败');
        }
        $amount = $request->param('amount');
        if (!$amount) {
            return JsonError('请选择正确的数量');
        }

        $spec_id = $request->param('spec_id');

        $spec = GoodsSpec::where('id', $spec_id)->where('goods_id', $goods_id)->find();
        if (!$spec) {
            return JsonError('规格获取失败');
        }
        if ($spec->inventory < $amount) {
            return JsonError('库存不足');
        }
        $total_price = $spec->price * $amount;

        $coupon_id = $request->param('coupon_id', 0);
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
        $address_id = $request->param('address_id');
        if (!$address_id) {
            $default_address = \app\admin\model\Address::where('user_id', $this->user_id)->where('is_default', 1)->where('type', 1)->find();
        } else {
            $default_address = \app\admin\model\Address::where('id', $address_id)->find();
        }
        $freight = '0.00';

        $goods['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com'). $goods['cover_image'];

        $data = [
            'goods' => $goods,
            'spec' => $spec,
            'coupon' => $coupon,
            'default_address' => $default_address,
            'amount' => $amount,
            'freight' => $freight,
            'balance' => $user->balance,
            'preferential_price' => $preferential_price,
            'total_price' => $total_price,
        ];

        return JsonSuccess($data);
    }

    /**
     * 订单提交
     */
    public function submit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $goods_id = $request->param('goods_id');
        if (!$goods_id) {
            return JsonError('参数获取失败');
        }
        Db::startTrans();
        try {
            $goods = Db::name('goods')->where('id', $goods_id)->find();
            if (!$goods) {
                return JsonError('数据获取失败');
            }
            $amount = $request->param('amount');
            if (!$amount) {
                return JsonError('请选择正确的数量');
            }

            $spec_id = $request->param('spec_id');

            $spec = Db::name('goods_spec')->where('id', $spec_id)->find();
            if (!$spec) {
                return JsonError('规格获取失败');
            }
            if ($amount > $spec['inventory']) {
                return JsonError('库存不足');
            }

            $address_id = $request->param('address_id');
            if (!$address_id) {
                return JsonError('请选择地址');
            }
            $address = \app\admin\model\Address::where('id', $address_id)->where('user_id', $this->user_id)->find();
            if (!$address) {
                return JsonError('地址获取失败');
            }
//        $order = new GoodsOrder();
//
//        $order->order_no = GetOrderNo();
//        $order->goods_id = $goods_id;
//        $order->user_id = $this->user_id;
//        $order->spec_id = $spec_id;
//        $order->pay_price = $spec->pay_price;
            $total_price = $spec['price'] * $amount;

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
                'goods_id' => $goods_id,
                'user_id' => $this->user_id,
                'spec_id' => $spec_id,
                'is_coupon' => $is_coupon,
                'coupon_id' => $coupon_id,
                'order_status' => 0,
                'pay_type' => 1,
                'pay_price' => $preferential_price,
                'total_price' => $total_price,
                'preferential_price' => $preferential_price,
                'spec_name' => $spec['name'],
                'goods_name' => $goods['title'],
                'cover_image' => $goods['cover_image'],
                'unit' => $goods['unit'],
                'unit_price' => $goods['price'],
                'amount' => $amount,
                'spec_image' => $spec['image'],
                'address_id' => $address_id,
                'province' => $address->province,
                'city' => $address->city,
                'district' => $address->district,
                'detail' => $address->detail,
                'name' => $address->name,
                'mobile' => $address->mobile,
                'remark' => $request->param('remark'),
                'submit_time' => $time,
                'create_time' => $time,
                'update_time' => $time,
            ];
            $order_id = Db::name('goods_order')->insertGetId($order);
            Db::name('users_coupon')->where('user_id', $this->user_id)->where('coupon_id', $coupon_id)->update([
                'status' => 1
            ]);
            Db::name('goods_spec')->where('id', $spec_id)->setDec('inventory', $amount);
            Db::commit();
            $data = [
                'order_id' => $order_id,
                'order_no' => $order['order_no']
            ];

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
        $pay_type = $request->param('pay_type');
        if (!$pay_type) {
            return JsonError('请选择支付方式');
        }
        $order = Order::where('id', $order_id)
            ->where('order_no', $order_no)
            ->where('user_id', $this->user_id)
            ->where('order_status', 0)
            ->where('pay_status', 0)
            ->find();
        if (!$order) {
            return JsonError('订单获取失败');
        }
        $user = Users::where('id', $this->user_id)->find();
        //微信支付
        if ($pay_type == 1) {

            $openid = $user['openid'];

            $notifyUrl = GetConfig('app_url', 'http://www.le-live.com') . '/api/notify/wechat';

            $pay = new WeChatPayService();
            $result = $pay->Mini_Pay($order->order_no, $order->pay_price, $openid, $notifyUrl, '购买菜品');
            if ($result) {
                return JsonSuccess($result);
            }
            return JsonError('支付订单生成失败');
        } else {
            //余额支付
            Db::startTrans();
            try {
                if ($user->balance < $order->pay_price) {
                    return JsonError('您的余额不足');
                }


                $log = new WalletLog();
                $log->user_id = $this->user_id;
                $log->content = '购买配套';
                $log->money = $order->pay_price;
                $log->type = 2;
                $log->save();

                $user->setDec('balance', $order->pay_price);
                $order->order_status = 1;
                $order->pay_status = 1;
                $order->pay_type = 2;
                $order->pay_time = time();
                $order->save();

                Db::commit();
                return JsonSuccess([], '支付成功');
            } catch (Exception $exception) {
                Db::rollback();
                return JsonError('支付失败');
            }
        }
    }

    /**
     * 订单列表
     */
    public function lists(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Order::where('user_id', $this->user_id)->order('create_time','desc')->page($page, 10)->select();
        $count = Order::where('user_id', $this->user_id)->count();
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 订单详情
     */
    public function detail(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Order::where('id', $order_id)->where('user_id', $this->user_id)->find();
        if (!$order) {
            return JsonError('订单获取失败');
        }
        if ($order->is_coupon) {
            $order['coupon_price'] = $order->total_price - $order->pay_price;
        }
        $data = [
            'detail' => $order
        ];
        return JsonSuccess($data);
    }

    /**
     * 确认收货
     */
    public function receipt(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $order_id = $request->param('order_id');
        if (!$order_id) {
            return JsonError('参数获取失败');
        }
        $order = Order::where('id', $order_id)->where('user_id', $this->user_id)->find();
        if (!$order) {
            return JsonError('订单获取失败');
        }
        if ($order->order_status != 2) {
            return JsonError('该订单不能确认收货');
        }

        $order->order_status = 3;
        if ($order->save()) {
            return JsonSuccess([], '收货成功');
        }
        return JsonError('收货失败');
    }

    /**
     * 取消订单
     */
    public function cancel(Request $request)
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
            $order = Order::where('id', $order_id)->where('user_id', $this->user_id)->find();
            if (!$order) {
                return JsonError('订单获取失败');
            }
            if (!in_array($order->order_status, [0, 1])) {
                return JsonError('该订单不能取消');
            }
            if ($order->order_status == 1) {
                //给用户退款
                if ($order->pay_type == 1) {

                    $wechat = new WeChatPayService();
                    $result = $wechat->Refund($order->order_no, $order->pay_price, '取消订单');
                    if (!$result) {
                        return JsonError('退款失败');
                    }
                }
                if ($order->pay_type == 2) {

                    $user = Users::where('id', $this->user_id)->setInc('balance', $order->pay_price);

                    $log = new WalletLog();
                    $log->user_id = $this->user_id;
                    $log->content = '配套订单（' . $order->order_no . '）取消';
                    $log->money = $order->pay_price;
                    $log->type = 1;
                    $log->save();

                    if (!$user) {
                        return JsonError('退款失败');
                    }
                }
            }
            //退还库存
            GoodsSpec::where('id', $order->spec_id)->setInc('inventory', $order->amount);
            //归还优惠券
            Db::name('users_coupon')
                ->where('coupon_id', $order->coupon_id)
                ->where('user_id', $this->user_id)->update([
                    'status' => 0
                ]);
            $order->order_status = 4;
            $order->save();

            Db::commit();
            return JsonSuccess([], '取消成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('取消失败');
        }
    }

}