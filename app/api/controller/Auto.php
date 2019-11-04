<?php

namespace app\api\controller;


use app\admin\model\Admin;
use app\admin\model\CreditLog;
use app\admin\model\EarningsLog;
use app\admin\model\Users;
use app\admin\model\WalletLog;
use app\api\service\AliyunSmsService;
use app\api\service\WeChatPayService;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use think\Db;
use think\Exception;
use think\Route;
use app\admin\model\MenusOrder;

class Auto extends Base
{
    /**
     * 统计
     */
    public function statistics()
    {
        //is_partner合伙人 is_enter个体 is_head首个合伙人 partner_id上级合伙人ID total_turnover今天之前的总业绩
        $user = Db::name('users')->where('user_type', '>', 0)->field(['id', 'first_user_id', 'user_type', 'is_partner', 'is_enter', 'is_head', 'partner_id', 'total_turnover', 'total_people', 'enter_date'])->select();
        foreach ($user as $k => $v) {
            $user[$k]['money'] = Db::name('menus_order')->where('chef_id',$v['id'])->where('pay_date',date('Y-m-d'))->where('order_status',4)->sum('pay_price');
//            $user[$k]['money'] = 100;
            $user[$k]['total'] = 0;
            $user[$k]['count'] = 0;
        }
        $obj = ToTree($user, 'first_user_id');
//        print_r($obj);die;
        $res = $this->getGroup($user, $obj);
        $arr = ToTree($res, 'first_user_id');
        print_r($res);
    }

    public function getGroup($user, $obj)
    {
        if (!empty($obj)) {
            foreach ($obj as $k => $v) {
                if (!empty($v['son'])) {
                    $result = $this->searchMoney(0, $obj[$k]);
                    $count = $this->searchCount(0, $obj[$k]) - 1;
                    foreach ($user as $ko => $vo) {
                        if ($vo['id'] == $v['id']) {
                            $total = $result + $vo['total_turnover'];
                            $user[$ko]['total'] = $total;
                            $user[$ko]['count'] = $count;
                            Db::name('users')->where('id', $vo['id'])->update(['total_turnover' => $total, 'total_people' => $count]);
                            $data['user_id'] = $vo['id'];
                            $data['date'] = date('Y-m-d');
                            $data['total_turnover'] = $total;
                            $data['day_turnover'] = $result;
                            $data['user_type'] = $vo['user_type'];
                            $data['profit'] = 0;
                            $data['create_time'] = time();
                            $data['update_time'] = time();
                            $userProfit = Db::name('users_profit')->insertGetId($data);
                            if ($vo['is_partner'] == 1 && $vo['enter_date'] < date('Y-m-d')) {
                                $nextRatio = Db::name('range_level')->where('meet_people', '<=', $vo['total_people'])->where('meet_money', '<=', $vo['total_turnover'])->order('id asc')->value('ratio');
                                if ($nextRatio) {
                                    $ratio = Db::name('range_level')->where('meet_people', '<=', $count)->where('meet_money', '<=', $total)->order('id desc')->value('ratio');
                                    if ($ratio) {
                                        Db::name('users')->where('id', $vo['id'])->setInc('fenrun', round(($ratio / 100) * $result, 2));
                                        Db::name('users_profit')->where('id', $userProfit)->setInc('profit', round(($ratio / 100) * $result, 2));
                                        $vo['partner_id'] && Db::name('users')->where('id', $vo['partner_id'])->setDec('fenrun', round(($ratio / 100) * $result, 2));
                                        Db::name('users_profit')->where('user_id', $vo['partner_id'])->where('date', date('Y-m-d'))->setDec('profit', round(($ratio / 100) * $result, 2));
                                    }
                                }
                            }
                        }
                    }
                    $user = $this->getGroup($user, $v['son']);
                } else {
                    foreach ($user as $ko => $vo) {
                        if ($vo['id'] == $v['id']) {
                            $result = $v['money'];
                            $count = 0;
                            $total = $result + $vo['total_turnover'];
                            $user[$ko]['total'] = $total;
                            $user[$ko]['count'] = $count;
                            Db::name('users')->where('id', $vo['id'])->update(['total_turnover' => $total, 'total_people' => $count]);
                            $data['user_id'] = $vo['id'];
                            $data['date'] = date('Y-m-d');
                            $data['total_turnover'] = $total;
                            $data['day_turnover'] = $result;
                            $data['profit'] = 0;
                            $data['create_time'] = time();
                            $userProfit = Db::name('users_profit')->insertGetId($data);
                            if ($vo['is_partner'] == 1 && $vo['enter_date'] < date('Y-m-d')) {
                                $nextRatio = Db::name('range_level')->where('meet_people', '<=', $vo['total_people'])->where('meet_money', '<=', $vo['total_turnover'])->order('id asc')->value('ratio');
                                if ($nextRatio) {
                                    $ratio = Db::name('range_level')->where('meet_people', '<=', $count)->where('meet_money', '<=', $total)->order('id desc')->value('ratio');
                                    if ($ratio) {
                                        Db::name('users')->where('id', $vo['id'])->setInc('fenrun', round(($ratio / 100) * $result, 2));
                                        Db::name('users_profit')->where('id', $userProfit)->setInc('profit', round(($ratio / 100) * $result, 2));
                                        $vo['partner_id'] && Db::name('users')->where('id', $vo['partner_id'])->setDec('fenrun', round(($ratio / 100) * $result, 2));
                                        Db::name('users_profit')->where('user_id', $vo['partner_id'])->where('date', date('Y-m-d'))->setDec('profit', round(($ratio / 100) * $result, 2));
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $user;
        }
    }

    public function searchMoney($money, $arr)
    {
        if (!empty($arr)) {
            $money += $arr['money'];
            if (!empty($arr['son'])) {
                foreach ($arr['son'] as $k => $v) {
                    $money = $this->searchMoney($money, $arr['son'][$k]);
                }
            } else {
                return $money;
            }
        }
        return $money;
    }

    public function searchCount($count, $arr)
    {
        if (!empty($arr)) {
            $count += 1;
            if (!empty($arr['son'])) {
                foreach ($arr['son'] as $k => $v) {
                    $count = $this->searchCount($count, $arr['son'][$k]);
                }
            } else {
                return $count;
            }
        }
        return $count;
    }


    /**
     * 递归
     */
    public function recursion($id, $data)
    {
        $users = Db::name('users')
            ->where('first_user_id', $id)
            ->where('enter_date', '<>', date('Y-m-d'))
            ->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])
            ->select();
        if (count($users) > 0) {
            foreach ($users as $key => $val) {
                $data['son'] = $this->recursion($val['id'], $data);
                $tree[] = $val;
            }
        }
    }

    /**
     * 树状数据
     */
    public function getTree($data, $id)
    {
        $tree = '';
        foreach ($data as $k => $v) {
            if ($v['first_user_id'] == $id) {         //父亲找到儿子
                $v['son'] = $this->getTree($data, $v['id']);
                $tree[] = $v;
                //unset($data[$k]);
            }
        }
        return $tree;
    }


    /**
     * 方案二
     */
    public function ToTree($items, $pid = "first_user_id")
    {

        $map = [];
        $tree = [];

        foreach ($items as &$it) {
            $map[$it['id']] = &$it;
        }
        //数据的ID名生成新的引用索引树
        foreach ($items as &$it) {
            $parent = &$map[$it[$pid]];

            if ($parent) {
                $parent['son'][] = &$it;

            } else {
                $tree[] = &$it;
            }
        }
        return $tree;
    }


    public function order()
    {
        $this->unpaid_order();
        $this->overtime_order();
        $this->confirm_order();
        $this->delivery_order();
    }

    /**
     * 待支付订单取消
     */
    public function unpaid_order()
    {
        $time = strtotime("-15 minute");
        //待支付订单 15分钟后取消
        $menus_order = MenusOrder::with('menus')->where('order_status', 0)->where('submit_time', '<', $time)->select();
        foreach ($menus_order as $item) {
            Db::startTrans();
            try {
                if ($item->coupon_id) {
                    //归还优惠券
                    Db::name('users_coupon')->where('coupon_id', $item->coupon_id)->where('user_id', $this->user_id)->update([
                        'status' => 0
                    ]);
                }
                foreach ($item->menus as $val) {
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $item->chef_id)
                        ->setInc('total_amount', $val->amount);
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $item->chef_id)
                        ->setDec('sell_amount', $val->amount);
                }

                //删除订单
                $item->delete();
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
            }
        }
    }

    /**
     * 支付之后超时订单
     */
    public function overtime_order()
    {
        $time = time();
        $menus_order = MenusOrder::with('menus')->where('order_status', 1)->where('delivery_type', 1)->where('serving_time', '>', $time)->select();
        foreach ($menus_order as $item) {
            Db::startTrans();
            try {
                $wechat = new WeChatPayService();
                $res = $wechat->Refund($item->order_no, $item->pay_price, '微厨超时未发货');
                if (!$res) {
                    Db::rollback();
                }


                $send_user = Users::where('id', $item->user_id)->find();
                if ($send_user) {
                    if ($send_user->mobile) {
                        $sms = new AliyunSmsService();
                        $data = [
                            'name' => $item->order_no
                        ];
                        $sms->sendSms($send_user->mobile, 'SMS_176375238', $data);
                    }
                }
                //退还优惠券
                if ($item->coupon_id) {
                    Db::name('users_coupon')
                        ->where('coupon_id', $item->coupon_id)
                        ->where('user_id', $item->user_id)->update([
                            'status' => 0
                        ]);
                }
                foreach ($item->menus as $val) {
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $item->chef_id)
                        ->setInc('total_amount', $val->amount);
                    Db::name('menus_reserve')
                        ->where('menu_id', $val->menu_id)
                        ->where('user_id', $item->chef_id)
                        ->setDec('sell_amount', $val->amount);
                }
                //扣除微厨积分
                $chef = Users::where('id',$item->cehf_id)->find();
                if ($chef){
                    $log = new CreditLog();
                    $log->user_id = $item->cehf_id;
                    $log->content = '订单超时发货(' . $item->order_no . ')';
                    $log->number = intval($item->pay_price * 0.5);
                    $log->type = 2;
                    $log->save();
                    $chef->setDec('credit_line',intval($item->pay_price * 0.5));
                }
                $item->order_status = 5;
                $item->save();

                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
            }
        }
    }

    /**
     * 已发货的订单确认收货
     */
    public function confirm_order()
    {
        $time = strtotime("-1 days", time());
        $menus_order = MenusOrder::with('menus')->where('order_status',2)->where('serving_time','<',$time)->select();
        foreach ($menus_order as $item){
            Db::startTrans();
            try {
                $chef = Users::where('id', $item->chef_id)->find();

                if ($chef->first_user_id) {
                    //一级分销奖励
                    $first_user = Users::where('id', $chef->first_user_id)->find();
                    if ($first_user) {
                        if ($first_user->is_enter == 1) {
                            $money = sprintf("%.2f", $item->menu_price * (GetConfig('first_order_ratio', 1) / 100));
                            $log = new EarningsLog();
                            $log->user_id = $chef->first_user_id;
                            $log->content = '订单（' . $item->order_no . '）奖励';
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
                            $money = sprintf("%.2f", $item->menu_price * (GetConfig('second_order_ratio', 0.5) / 100));
                            $log = new EarningsLog();
                            $log->user_id = $chef->second_user_id;
                            $log->content = '订单（' . $item->order_no . '）奖励';
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
                $log->content = '完成订单(' . $item->order_no . ')';
                $log->number = 1;
                $log->type = 1;
                $log->save();


                //微厨的余额明细
                $log = new WalletLog();
                $log->user_id = $item->chef_id;
                $log->content = '订单（' . $item->order_no . '）';
                $log->money = $item->menu_price;
                $log->type = 1;
                $log->save();

                $chef->setInc('credit_line', 1);
                $chef->setInc('balance', $item->menu_price);

                $item->order_status = 4;
                $item->save();
                Db::commit();
                return JsonSuccess([], '确认收货成功');
            } catch (Exception $exception) {
                Db::rollback();
                return JsonError('确认收货失败');
            }
        }
    }

    /**
     * 待自提的订单确认自提
     */
    public function delivery_order()
    {
        $time = strtotime("-1 days", time());
        $menus_order = MenusOrder::where('order_status',3)->where('serving_time','<',$time)->select();
        foreach($menus_order as $item){
            Db::startTrans();
            try {
                $chef = Users::where('id', $item->chef_id)->find();

                if ($chef->first_user_id) {
                    //一级分销奖励
                    $first_user = Users::where('id', $chef->first_user_id)->find();
                    if ($first_user) {
                        if ($first_user->is_enter == 1) {
                            $money = sprintf("%.2f", $item->menu_price * (GetConfig('first_order_ratio', 1) / 100));
                            $log = new EarningsLog();
                            $log->user_id = $chef->first_user_id;
                            $log->content = '订单（' . $item->order_no . '）奖励';
                            $log->type = 2;
                            $log->status = 1;
                            $log->money = $money;
                            $log->save();
                            $first_user->setInc('store_balance', $money);
                        }
                    }
                    //二级分销奖励
                    $second_user = Users::where('id', $chef ->second_user_id)->find();
                    if ($second_user) {
                        if ($second_user->is_enter == 1) {
                            $money = sprintf("%.2f", $item->menu_price * (GetConfig('second_order_ratio', 0.5) / 100));
                            $log = new EarningsLog();
                            $log->user_id = $chef->second_user_id;
                            $log->content = '订单（' . $item->order_no . '）奖励';
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
                $log->content = '完成订单(' . $item->order_no . ')';
                $log->number = 1;
                $log->type = 1;
                $log->save();


                //微厨的余额明细
                $log = new WalletLog();
                $log->user_id = $item->chef_id;
                $log->content = '订单（' . $item->order_no . '）';
                $log->money = $item->menu_price;
                $log->type = 1;
                $log->save();

                $chef->setInc('credit_line', 1);
                $chef->setInc('balance', $item->menu_price);

                $item->order_status = 4;
                $item->save();
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
            }
        }
    }
}