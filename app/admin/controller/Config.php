<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------


namespace app\admin\controller;

use app\admin\model\Config as ConfigModel;
use think\Validate;

class Config extends Permissions
{
    public function index()
    {
        $config = ConfigModel::order('id','asc')->order('create_time','desc')->paginate(10);
        $this->assign('config',$config);
        return $this->fetch();
    }


    public function create()
    {
        return $this->fetch();
    }


    public function save()
    {
        $data = $this->request->param();
        $validate = new Validate([
            ['name','require','请填写键'],
            ['value','require','请填写值'],
        ]);
        if (!$validate->check($data)){
            $this->error($validate->getError());
        }
        $config = new ConfigModel();
        if (isset($data['id'])){
            $config = ConfigModel::where('id',$data['id'])->find();
        }
        $config->name = $data['name'];
        $config->value = $data['value'];
        $config->description = $data['description'];
        if ($config->save()){
            $this->success('提交成功','admin/config/index');
        }
        $this->error('提交失败');
    }


    public function edit($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $config = ConfigModel::where('id',$id)->find();
        $this->assign('config',$config);
        return $this->fetch();
    }


    public function delete($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $config = ConfigModel::where('id',$id)->find();
        if ($config->delete()){
            $this->success('删除成功');
        }
        $this->error('删除失败');
    }

}
