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
//            ['spec_team','require','请添加规格组'],
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
            $data['spec'] = $arr;
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
            $goods->cover_image = $data['images'][0];
            $goods->status = 0;
            $goods->spec_team = $data['spec_team'];
            $goods->edit_value = $data['edit_value'];
            $goods->save();
            $id = $goods->getLastInsID();
            GoodsImage::where('goods_id',$id)->delete();//先删除图片
            foreach($data['images'] as $val){
                $image = new GoodsImage();
                $image->goods_id = $id;
                $image->image = $val;
                $image->save();
            }
            //先删除规格组
            Db::name('goods_spec')->where('goods_id',$id)->delete();
            foreach($data['spec'] as $val){
                Db::name('goods_spec')->insert([
                    'name' => $val['spec'],
                    'inventory' => $val['inventory'],
                    'price' => $val['growth'],
                    'goods_id' => $id,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
            }
            Db::commit();
            $this->success('提交成功','admin/goods/index');
        }catch (Exception $e){
            var_dump($e);
            exit;
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
        $spec = Db::name('goods_spec')->where('goods_id',$id)->select();
        $this->assign('spec',$spec);
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
