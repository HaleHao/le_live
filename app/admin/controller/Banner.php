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

use app\admin\model\Banner as BannerModel;
use \think\Db;
use think\Validate;

class Banner extends Permissions
{
    public function index()
    {
        $banner = BannerModel::order('sort','asc')->order('create_time','desc')->paginate(10,false,['query'=>request()->param()])->each(function ($item,$key){
            $item['type']==1?$item['type']='首页':$item['type']='业务介绍';
            return $item;
        });
        $this->assign('banner',$banner);
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
        $banner = new Banner();
        if (isset($data['id'])){
            $banner = BannerModel::where('id',$data['id'])->find();
        }
        $banner->title = $data['title'];
        $banner->sort = $data['sort'];
        $banner->image = $data['image'];
        $banner->pages = $data['pages'];
        if ($banner->save()){
            $this->success('提交成功','admin/banner/index');
        }
        $this->error('提交失败');
    }


    public function edit($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $banner = BannerModel::where('id',$id)->find();
        $this->assign('banner',$banner);
        return $this->fetch();
    }


    public function delete($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $banner = BannerModel::where('id',$id)->find();
        if ($banner->delete()){
            $this->success('删除成功');
        }
        $this->error('删除失败');
    }

}
