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

use app\admin\model\Goods as GoodsModel;
use app\admin\model\GoodsImage;
use think\Config;
use \think\Db;
use think\Exception;
use think\Validate;

class Goods extends Permissions
{
    public function index()
    {
        $goods = GoodsModel::order('sort','asc')->order('create_time','desc')->paginate(10);
//        exit(1);
        $this->assign('goods',$goods);
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
            ['unit','require','请填写单位'],
            ['sort','require','请填写排序'],
//            ['description','require','请填写描述'],
        ]);
        if (!$validate->check($data)){
            $this->error($validate->getError());
        }
        if(!empty($data['spec_val'])){
            foreach ($data['spec_val'] as $k=>$v){
                $act['spec'] = $v;
                $data['inventory'][$k]?$act['inventory']=$data['inventory'][$k]:$act['inventory']=0;
                $data['growth'][$k]?$act['growth']=$data['growth'][$k]:$act['growth']=0;
                $arr[] = $act;
            }
            $data['spec'] = json_encode($arr);
        }


        try{
            Db::startTrans();
            $goods = new GoodsModel();
            if (isset($data['id'])){
                $goods = GoodsModel::where('id',$data['id'])->find();

            }
            $goods->title = $data['title'];
            $goods->unit = $data['unit'];
            $goods->price = $data['price'];
            $goods->description = $data['description'];
            $goods->sort = $data['sort'];
            $goods->cover_url = $data['images'][0];
            $goods->status = 0;
            $goods->spec = $data['spec'];
            $goods->edit_value = $data['edit_value'];
            $goods->save();
            $id = $goods->getLastInsID();
            GoodsImage::where('id',$id)->delete();//先删除图片
            foreach($data['images'] as $val){
                $image = new GoodsImage();
                $image->goods_id = $id;
                $image->image = $val;
                $image->save();
            }
            Db::commit();
            $this->success('提交成功','admin/goods/index');
        }catch (Exception $e){
            Db::rollback();
            $this->error('提交失败');
        }

    }


    public function edit($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $goods = GoodsModel::with('image')->where('id',$id)->find();
        $json_spec = json_decode($goods['edit_value'],true);
        if(!empty($json_spec)) {
            foreach ($json_spec as $k => $v) {
                $spec_str = \db('spec')->where('id', $v['str'])->find();
                if (!empty($v['arr'])) {
                    foreach ($v['arr'] as $ko => $vo) {
                        $spec_str['arr'][] = \db('spec')->where('id', $vo)->find();
                    }
                    $spec[] = $spec_str;
                }
                $spec_i[] = $spec_str;
            }
            foreach ($spec as $k => $v) {
                $spec[$k]['str'] = $k . 'str';
            }
            if ($goods['spec']) {
                $spec_val = json_decode($goods['spec'], true);
                $specVal = array();
                foreach ($spec_val as $k => $v) {
                    $string = explode(':', $v['spec']);
                    $act['inventory'] = $v['inventory'];
                    $act['growth'] = $v['growth'];
                    $act['spec'] = $v['spec'];
                    foreach ($string as $ko => $vo) {
                        $act[$ko . 'str'] = $vo;
                    }
                    $specVal[] = $act;
                }
                $this->assign('spec_val', $specVal);
            }
            $this->assign('spec', $spec);
            $this->assign('spec_i', $spec_i);
        }
        $this->assign('goods',$goods);
        return $this->fetch();
    }


    public function delete($id)
    {
        if (!$id){
            $this->error('参数获取失败');
        }
        $banner = GoodsModel::where('id',$id)->find();
        if ($banner->delete()){
            GoodsImage::where('id',$id)->delete();
            $this->success('删除成功');
        }
        $this->error('删除失败');
    }

}
