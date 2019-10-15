<?php

namespace app\api\controller;


use app\admin\model\Admin;
use app\admin\model\Users;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use think\Db;
use think\Route;

class Auto extends Base
{
    /**
     * 统计
     */
    public function statistics()
    {

        //is_partner合伙人 is_enter个体 is_head首个合伙人 partner_id上级合伙人ID total_turnover今天之前的总业绩
        $user = Db::name('users')->field(['id', 'first_user_id',  'is_partner', 'is_enter', 'is_head','partner_id','total_turnover'])->select();
        foreach ($user as $k=>$v){
//            $user[$k]['money'] = Db::name('menus_order')->where('chef_id',$v['id'])->where('pay_date',date('Y-m-d'))->where('order_status',4)->sum('pay_price');
            $user[$k]['money'] = 100;
            $user[$k]['total'] = 0;
            $user[$k]['count'] = 0;
        }
        $obj = ToTree($user,'first_user_id');
        $res = $this->getGroup($user,$obj);
        $arr = ToTree($res,'first_user_id');
        print_r($arr);die;
    }

    public function getGroup($user,$obj){
        if(!empty($obj)){
            foreach ($obj as $k=>$v){
                if(!empty($v['son'])){
                    $result = $this->searchMoney(0,$obj[$k]);
                    $count = $this->searchCount(0,$obj[$k]);
                    foreach ($user as $ko=>$vo){
                        if($vo['id'] == $v['id']){
                            $total = $result+$vo['total_turnover'];
                            $user[$ko]['total'] = $total;
                            $user[$ko]['count'] = $count;
                            if($vo['is_partner'] == 1){
                                $ratio = Db::name('range_level')->where('meet_people','<=',$count)->where('meet_money','<=',$total)->order('id desc')->value('ratio');
                                $ratio&&Db::name('users')->where('id',$vo['id'])->setInc('fenrun',round($ratio*$vo['money'],2));
                                $vo['partner_id']&&Db::name('users')->where('id',$vo['partner_id'])->setDec('fenrun',round($ratio*$vo['money'],2));
                            }
                        }
                    }
                    $user = $this->getGroup($user,$v['son']);
                }else{
                    foreach ($user as $ko=>$vo){
                        if($vo['id'] == $v['id']){
                            $total = $v['money']+$vo['total_turnover'];
                            $user[$ko]['total'] = $total;
                            $user[$ko]['count'] = 1;
                            if($vo['is_partner'] == 1){
                                $ratio = Db::name('range_level')->where('meet_people','<=',1)->where('meet_money','<=',$total)->order('id desc')->value('ratio');
                                $ratio&&Db::name('users')->where('id',$vo['id'])->setInc('fenrun',round($ratio*$vo['money'],2));
                                $vo['partner_id']&&Db::name('users')->where('id',$vo['partner_id'])->setDec('fenrun',round($ratio*$vo['money'],2));
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
                    $count = $this->searchMoney($count, $arr['son'][$k]);
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

    function getTree($data, $id)
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

    /**
     * 递归
     */

}