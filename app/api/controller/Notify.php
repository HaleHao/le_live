<?php

namespace app\api\controller;


use app\admin\model\EarningsLog;
use app\admin\model\StoreOrder;
use app\admin\model\Users;
use think\Exception;
use app\admin\model\MenusOrder;
use app\admin\model\GoodsOrder;
use think\Request;

class Notify extends Base
{
    //TODO 微信支付回调
    public function wechat()
    {
        $data = file_get_contents("php://input");
        $data = $this->XmlToArr($data);

//        $data = [
//            'return_code' => 'SUCCESS',
//            'result_code' => 'SUCCESS',
//            'out_trade_no' => '2019101415273960514',
//            'total_fee' => 9900,
//        ];

        if ($data['return_code'] == 'SUCCESS' && $data['result_code'] == 'SUCCESS') {
//            $out_trade_no = $request->param('out_trade_no');
            $out_trade_no = $data['out_trade_no']; //订单编号
            $total_fee = $data['total_fee'] / 100; //价格

            //菜谱订单
            $menus_order = MenusOrder::where('order_no', $out_trade_no)->where('pay_price',$total_fee)->find();
            if ($menus_order) {

                $menus_order->pay_type = 1;
                $menus_order->pay_status = 1;
                if ($menus_order->delivery_type == 2){
                    $menus_order->order_status = 3;
                }else{
                    $menus_order->order_status = 1;
                }

                $menus_order->pay_date = date('Y-m-d');
                $menus_order->pay_time = time();

                if ($menus_order->save()) {
                    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    return;
                }
                return;
            }
            //商品订单
            $goods_order = GoodsOrder::where('order_no', $out_trade_no)->where('pay_price', $total_fee)->find();
            if ($goods_order) {
                $goods_order->order_status = 1;
                $goods_order->pay_type = 1;
                $goods_order->pay_status = 1;
                $goods_order->pay_time = time();
                if ($goods_order->save()) {
                    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    return;
                }
                return;
            }

            //店铺订单
            try {
                $store_order = StoreOrder::where('order_no', $out_trade_no)->where('pay_price', $total_fee)->find();
                if ($store_order) {

                    $user = Users::where('id', $store_order->user_id)->find();

                    //个体商户
                    if ($store_order->store_type == 1) {
                        //如果入驻的话，直接增加店铺数量
                        if ($user->is_enter == 1) {

                            $first_user = Users::where('id',$user->first_user_id)->find();
                            if ($first_user){
                                if ($first_user->is_enter==1){
                                    $log = new EarningsLog();
                                    $log->user_id = $user->first_user_id;
                                    $log->content = '[' . $user['nickname'] . ']' . '商户招募收益';
                                    $log->type = 1;
                                    $log->status = 1;
                                    $log->money = $store_order->pay_price * 0.2;
                                    $log->save();
                                    $first_user->setInc('store_balance',$store_order->pay_price * 0.2);
                                }
                            }
                            $second_user = Users::where('id',$user->second_user_id)->find();
                            if ($second_user){
                                if ($second_user->is_enter==1){

                                    $log = new EarningsLog();
                                    $log->user_id = $user->second_user_id;
                                    $log->content = '[' . $user['nickname'] . ']' . '商户招募收益';
                                    $log->type = 1;
                                    $log->status = 1;
                                    $log->money = $store_order->pay_price * 0.1;
                                    $log->save();

                                    $second_user->setInc('store_balance',$store_order->pay_price * 0.1);
                                }
                            }

                            $user->setInc('store_total_num', $store_order->amount);
                            $user->setInc('store_residue_num', $store_order->amount);
                        } else {

                            $first_user = Users::where('id',$user->first_user_id)->find();
                            if ($first_user){
                                if ($first_user->is_enter==1){
                                    $log = new EarningsLog();
                                    $log->user_id = $user->first_user_id;
                                    $log->content = '[' . $user['nickname'] . ']' . '商户招募收益';
                                    $log->type = 1;
                                    $log->status = 1;
                                    $log->money = $store_order->pay_price * 0.2;
                                    $log->save();
                                    $first_user->setInc('store_balance',$store_order->pay_price * 0.2);
                                }
                            }
                            $second_user = Users::where('id',$user->second_user_id)->find();
                            if ($second_user){
                                if ($second_user->is_enter==1){
                                    $log = new EarningsLog();
                                    $log->user_id = $user->second_user_id;
                                    $log->content = '[' . $user['nickname'] . ']' . '商户招募收益';
                                    $log->type = 1;
                                    $log->status = 1;
                                    $log->money = $store_order->pay_price * 0.1;
                                    $log->save();
                                    $second_user->setInc('store_balance',$store_order->pay_price * 0.1);
                                }
                            }

                            $user->is_enter = 1;
                            $user->setInc('store_total_num', $store_order->amount - 1);
                            $user->setInc('store_residue_num', $store_order->amount - 1);
                            $user->save();
                        }
                        //合伙人
                    } else {
                        //判断上下级关系
                        if ($user->is_partner == 1) {
                            $user->setInc('store_total_num', 100 * $store_order->amount);
                            $user->setInc('store_residue_num', 100 * $store_order->amount);
                        } else {
                            if ($user->first_user_id == 0) {
                                $user->is_head = 1;
                            } else {
                                //给予一级招募奖励
                                $first_user = Users::where('id', $user->first_user_id)->find();
                                if ($first_user) {
                                    if ($first_user->is_partner == 1) {
                                        $log = new EarningsLog();
                                        $log->user_id = $user->first_user_id;
                                        $log->content = '[' . $user['nickname'] . ']' . '招募收益';
                                        $log->type = 1;
                                        $log->status = 1;
                                        $log->money = GetConfig('first_partner_award', 4000);
                                        $log->save();
                                        $first_user->setInc('store_balance', GetConfig('first_partner_bonus', 4000));
                                    }
                                }
                                //给予二级招募奖励
                                $second_user = Users::where('id', $user->second_user_id)->find();
                                if ($second_user) {
                                    if ($second_user->is_partner == 1) {
                                        $log = new EarningsLog();
                                        $log->user_id = $user->second_user_id;
                                        $log->content = '[' . $user['nickname'] . ']' . '招募收益';
                                        $log->type = 1;
                                        $log->status = 1;
                                        $log->money = GetConfig('second_partner_award', 2000);
                                        $log->save();
                                        $second_user->setInc('store_balance', GetConfig('second_partner_bonus', 2000));
                                    }
                                }
                                //TODO 查询上级的partner_id(合伙人ID)将数据存进用户表里
                                $partner_id = $this->GetUpPartnerID($user->id);
                                if ($partner_id){
                                    $user->partner_id = $partner_id;
                                }

                                //TODO 查询下级的合伙人将ID修改成我的ID
                                $this->GetDownPartnerID($user->id,$user->id);
//                                exit;

                            }
                            $user->enter_date = date('Y-m-d');
                            $user->is_enter = 1;
                            $user->is_partner = 1;
                            $user->save();
                            $user->setInc('store_total_num', 100 * $store_order->amount);
                            $user->setInc('store_residue_num', 100 * $store_order->amount);
                        }
                    }

                    $store_order->order_status = 1;
                    $store_order->pay_status = 1;
                    $store_order->save();
                    echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                    return;
                }
            } catch (Exception $exception) {


                return;
            }
        }
    }


    //TODO 查询上一级的partner_id
    public function GetUpPartnerID($id)
    {
        $user = Users::where('id',$id)->find();
        if ($user->is_partner == 1){
            return $user->id;
        }else{
            $partner_id = $this->GetUpPartnerID($user->first_user_id);
            return $partner_id;
        }
    }

    //TODO 查询下一级的拥有partner_id的用户并修改用户对应的partner_id
    public function GetDownPartnerID($id,$first_user_id)
    {
        $users = Users::where('first_user_id',$first_user_id)->select();
        foreach($users as $val){
            //如果下级有partner_id
            if($val->partner_id || $val->is_partner){
                //直接修改为我成我的
                $val->partner_id = $id;
                $val->save();
            }
            $this->GetDownPartnerID($id,$val->id);
        }
    }





    //转换xml
    public function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml = $xml . '<' . $key . '>' . $this->arrayToXml($val) . '</' . $key . '>';
            } else {
                $xml = $xml . '<' . $key . '>' . $val . '</' . $key . '>';
            }

        }
        $xml .= '</xml>';
        return $xml;
    }

    //Xml转数组
    public function XmlToArr($xml)
    {
        if ($xml == '') return '';
        libxml_disable_entity_loader(true);
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $arr;
    }
}