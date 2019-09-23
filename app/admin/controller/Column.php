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

use app\admin\model\Column as ColumnModel;
use think\Validate;

class Column extends Permissions
{
    public function index()
    {
        $column = ColumnModel::order('sort','asc')->order('create_time','desc')->paginate(10);
        $this->assign('column',$column);
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
            ['title','require','请填写标题'],
            ['image','require','请上传图片'],
        ]);
        if (!$validate->check($data)){
            $this->error($validate->getError());
        }
        $column = new Column();
        if (isset($data['id'])){
            $column = ColumnModel::where('id',$data['id'])->find();
        }
        $column->title = $data['title'];
        $column->sort = $data['sort'];
        $column->image = $data['image'];
        if ($column->save()){
            $this->success('提交成功','admin/column/index');
        }
        $this->error('提交失败');
    }


    public function edit($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $column = ColumnModel::where('id',$id)->find();
        $this->assign('column',$column);
        return $this->fetch();
    }


    public function delete($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $banner = ColumnModel::where('id',$id)->find();
        if ($banner->delete()){
            $this->success('删除成功');
        }
        $this->error('删除失败');
    }

}
