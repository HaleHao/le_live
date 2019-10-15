<?php

namespace app\admin\controller;

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Distribution extends Permissions
{

    /**
     * 分销列表
     */
    public function index()
    {
        return $this->fetch();
    }
    public function index_all(){
        $info = Db::name('range_level')->order('id desc')->select();
        foreach ($info as $k=>$v){
            $info[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
        }
        $count = Db::name('range_level')->count();
        return $data=[
            'code'=>0,
            'msg'=>'',
            'count'=>$count,
            'data'=>$info
        ];
    }

    /**
     * 编辑分销
     */
    public function edit(){
        $id = input('id');
        if($this->request->isPost()) {
            $post = $this->request->post();
            //验证  唯一规则： 表名，字段名，排除主键值，主键名
            $validate = new \think\Validate([
                ['level', 'require', '等级不能为空'],
                ['ratio', 'require', '分销比例不能为空'],
                ['meet_people', 'require', '满足人数不能为空'],
                ['meet_money', 'require', '满足金额不能为空'],
            ]);
            //验证部分数据合法性
            if (!$validate->check($post)) {
                $this->error('提交失败：' . $validate->getError());
            }
            $id = $post['id'];
            $data['level'] = $post['level'];
            $data['ratio'] = $post['ratio'];
            $data['meet_people'] = $post['meet_people'];
            $data['meet_money'] = $post['meet_money'];
            if($id){
                $ret = Db::name('range_level')->where('id',$id)->update($data);
            }else{
                $data['create_time'] = time();
                $ret = Db::name('range_level')->insertGetId($data);
            }
            if ($ret) {
                $this->success('编辑成功', 'distribution/index');
            } else {
                $this->error('编辑失败', 'distribution/index');
            }
        } else {
            if($id){
                $res = Db::name('range_level')->where('id',$id)->find();
            }else{
                $res = array();
            }
            $this->assign('info',$res);
            return $this->fetch();
        }
    }

}
