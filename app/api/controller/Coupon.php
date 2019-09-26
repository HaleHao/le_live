<?php

namespace app\api\controller;

use think\Db;
use think\Exception;
use think\helper\Time;
use think\Request;

class Coupon extends Base
{
    /**
     * 优惠券列表
     */
    public function lists(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $type = $request->param('type', 1);
        //未领取的卡券
        if ($type == 1) {
            $subQueryb = Db::name('users_coupon')
                ->field('coupon_id')
                ->where('user_id', $this->user_id)
                ->buildSql();
//
            $list = Db::name('coupon')
                ->where('number', '>', 0)
                ->where('end_time', '>', time())
                ->where('id Not IN ' . $subQueryb)
                ->field('id,title,price,conditions,start_date,start_time,end_date,end_time')
//                ->page(2,5)
                ->select();

        }

        //已领取的卡券
        if ($type == 2) {
            $list = Db::name('users_coupon')->alias('u')
                ->join('coupon c', 'u.coupon_id=c.id', 'left')
                ->where('u.status', 0)
                ->where('c.end_time', '<', time())
                ->where('u.user_id', $this->user_id)
                ->field(['c.id,c.title,c.price,c.conditions,c.start_date,c.start_time,c.end_date,c.end_time'])
                ->select();
        }

        //已使用的卡券
        if ($type == 3) {
            $list = Db::name('users_coupon')->alias('u')
                ->join('coupon c', 'u.coupon_id=c.id', 'left')
                ->where('u.status', 1)
                ->where('u.user_id', $this->user_id)
                ->field(['c.id,c.title,c.price,c.conditions,c.start_date,c.start_time,c.end_date,c.end_time'])
                ->select();
        }

        $data = [
            'list' => $list
        ];

        return JsonSuccess($data);
    }

    /**
     * 领取优惠券
     */
    public function draw(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $coupon_id = $request->param('coupon_id');
        if (!$coupon_id) {
            return JsonError('参数获取失败');
        }
        Db::startTrans();
        try {
            $user_coupon = Db::name('users_coupon')
                ->where('user_id', $this->user_id)
                ->where('coupon_id', $coupon_id)
                ->find();
            if ($user_coupon) {
                return JsonError('您已经领取过了');
            }
            $coupon = Db::name('coupon')
                ->where('end_time', '>', time())
                ->where('id', $coupon_id)
                ->find();

            if (!$coupon) {
                return JsonError('优惠券获取失败');
            }
            $data = [
                'user_id' => $this->user_id,
                'coupon_id' => $coupon_id,
                'status' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            Db::name('users_coupon')->insert($data);

            Db::name('coupon')->where('id', $coupon_id)->update([
                'number' => $coupon['number'] - 1
            ]);
            Db::commit();

            return JsonSuccess([], '领取成功');

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('领取失败');
        }

    }

    /**
     * 订单可使用的优惠券列表
     */
    public function workable(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $total_price = $request->param('total_price', 0);

        $list = Db::name('users_coupon')->alias('u')
            ->join('coupon c', 'c.id=u.coupon_id', 'left')
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
}