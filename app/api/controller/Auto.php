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
        $user = Db::name('users')->where('is_enter', 1)->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])->select();

//        $data[] = $user[0];
//        foreach ($user as $val) {
//            $arr[] = $this->recursion($val);
//        }
//
////        print_r($arr);die;
        //计算个人的金额
        foreach ($user as $key => $val) {
            $user[$key]['money'] = 100;
        }
//        print_r($arr);
//        die;
//        foreach ()
        $obj = $this->getTree($user, 'first_user_id');
//        print_r($obj);die;

        $res = $this->getGroup($user,$obj);
//        print_r($res);die;
//        foreach ($arr as $key => $val) {
//            if ($val) {
//                foreach ($obj as $ko => $vo) {
////                    $reObj = $this->searchGroup(2,$obj[$ko]);
////                    print_r($reObj);die;
//                    $reObj = $this->searchGroup(7,$obj[$ko]);
//                    print_r($reObj);
//                    exit;
//                    $searchMoney = $this->searchMoney(0,$reObj);
//
//                }
//
//                $arr[$key]['turnover'] = $searchMoney;
//            }
//        }
        $obj = $this->getTree($res, 'first_user_id');
        print_r($obj);
        exit;

    }
    public function getGroup($user,$obj){
        if(!empty($obj)){
            foreach ($obj as $k=>$v){
                if(!empty($v['son'])){
                    $result = $this->searchMoney(0,$obj[$k]);
                    $num = $this->searchNum(0,$obj[$k]);
                    foreach ($user as $ko=>$vo){
                        if($vo['id'] == $v['id']){
                            $user[$ko]['total'] = $result;
                            $user[$ko]['num'] = $num;
                        }
                    }
                    $user = $this->getGroup($user,$v['son']);
                }else{
                    foreach ($user as $ko=>$vo){
                        if($vo['id'] == $v['id']){
                            $user[$ko]['total'] = $v['money'];
                        }
                    }
                }
            }
            return $user;
        }
    }

    public function recursion($data)
    {
        $users = Db::name('users')
            ->where('first_user_id', $data['id'])
            ->where('enter_date', '<>', date('Y-m-d'))
            ->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])
            ->select();
        $data['son'] = $users;
//        foreach ($users as $key => $val) {
//            $tree['son'] = $this->recursion($data);
////            $data['']
//        }
        return $data;
    }




    public function searchMoney($money, $arr)
    {
        if (!empty($arr)) {
            $money += $arr['money'];
            if (!empty($arr['son'])) {
                foreach ($arr['son'] as $k => $v) {
                    $money = $this->searchMoney($money,$arr['son'][$k]);
                }
            } else {
                return $money;
            }
        }
        return $money;
    }

    public function searchNum($money, $arr)
    {
        if (!empty($arr)) {
            $money += 1;
            if (!empty($arr['son'])) {
                foreach ($arr['son'] as $k => $v) {
                    $money = $this->searchNum($money,$arr['son'][$k]);
                }
            } else {
                return $money;
            }
        }
        return $money;
    }

    public function searchGroup($id, $obj)
    {
        if ($obj['id'] == $id) {
            return $obj;
        } else {
            if (!empty($obj['son'])) {
                foreach ($obj['son'] as $ko => $vo) {
                    $res = $this->searchTeam($id, $vo);
                    if ($res == 1) {
                        $obj = $this->searchGroup($id, $obj['son'][$ko]);
                        return $obj;
                    }
                }
            } else {
                return $obj;
            }
        }
    }

    public function searchTeam($id, $obj)
    {
        if ($obj['id'] == $id) {
            return 1;
        } else {
            if (!empty($obj['son'])) {
                foreach ($obj['son'] as $k => $v) {
//                    unset($obj['son'][$k][0]);
//                    print_r($obj['son'][$k][0]);
//                    exit;
                    $res = $this->searchTeam($id, $obj['son'][$k]);
                    return $res;
                }
            } else {
                return 0;
            }
        }

    }


    /**
     * 递归
     */
//    public function recursion($id, $data)
//    {
//        $users = Db::name('users')
//            ->where('first_user_id', $id)
//            ->where('enter_date', '<>', date('Y-m-d'))
//            ->field(['id', 'first_user_id', 'nickname', 'is_partner', 'is_enter', 'is_head'])
//            ->select();
//        if (count($users) > 0) {
//            foreach ($users as $key => $val) {
//                $data['son'] = $this->recursion($val['id'], $data);
//
//            }
//        }
//    }

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