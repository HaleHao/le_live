<?php

namespace app\api\controller;


use app\admin\model\Users;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use think\Db;
use think\Route;

class Auto2 extends Base
{
    public $allusers = [];
    /**
     * 统计
     */
    public function statistics()
    {
        $user = Db::name('users')->where('is_enter', 1)->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])->select();

        $data[] = $user[0];

        $arr = $this->recursion(1, $data);

        foreach ($arr as $key => $val) {
//            $arr[$key]['money'] = Db::name('menus_order')->where('chef_id', $val['id'])->where('pay_date', date('Y-m-d'))->sum('total_price');
            $arr[$key]['money'] = 100;
        }
        print_r($arr);
//        exit;
        $obj = $this->getTree($arr, 'first_user_id');
        $searchArr = $obj;
        print_r($obj);
        $obj_money_arr = $this->searchMoney(0,$obj);
//        foreach()
    }

    public function searchMoney($money,$arr){
        foreach ($arr as $k=>$v){
            $money = '';
        }
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
                $data[] = $val;
                $data = $this->recursion($val['id'], $data);
            }
        }
        return $data;
    }


//    走完

    public function allus(){
        $new = [];
        $ids = ['0' =>1];
        $res = $this ->allu($ids);
//        foreach ($res as $k =>$v){
//            $new = array_merge($new,$v);
//        }
//        echo "<pre>";
//        print_r($res);
        print_r($res);
        foreach ($res as $k ->$v){

        }
    }

    public function allu($ids=''){
        $aaa = $this->allusers;
//        $res = db('hhuser') ->where('first_user_id','in',['0'=>1]) ->field('id,nickname')->select();
        $res = db('users') ->where('first_user_id','in',$ids) ->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])->select();
        $total = count($res);
        if($total > 0){
            array_push($aaa,$res);
            $ids = array_column($res,'id');
            $this ->allusers = $aaa;
            $this ->allu($ids);
        }
        return $this->allusers;
    }

//走完



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
    public function test($user_id)
    {
//        $a++;
//        if ($a<10) {
//            $result[]=$a;
//            $this->test($a,$result);
//        }
//        echo $a;
//        return $result;
    }
}