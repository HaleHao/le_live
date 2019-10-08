<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/9/28 0028
 * Time: 14:45
 */
namespace app\api\controller;

use think\Db;
use think\Request;

class Goods extends Base
{
    /**
     * 商品列表
     */
    public function lists(Request $request)
    {
        $page = $request->param('page',1);
        $list = Db::name('goods')
            ->order('sort','asc')
            ->order('create_time','desc')
            ->field(['id','title','price','cover_image'])
            ->page($page,10)
            ->select();
        $count = Db::name('goods')->count();
        foreach($list as $key => $val){
            $list[$key]['cover_image'] = GetConfig('img_prefix','http://www.le-live.com') . $val['cover_image'];
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);
    }

    /**
     * 商品详情
     */
    public function detail(Request $request)
    {
        $goods_id = $request->param('goods_id');
        if (!$goods_id){
            return JsonError('参数获取失败');
        }
        $goods = Db::name('goods')->where('id',$goods_id)->find();
        $goods['spec'] = json_decode($goods['spec'],true);
        $goods['edit_value'] = json_decode($goods['edit_value'],true);
        $goods['cover_image'] = GetConfig('img_prefix','http://www.le-live.com') . $goods['cover_image'];
        $images = Db::name('goods_image')->where('goods_id',$goods_id)->select();
        foreach ($images as $key => $val){
            $images[$key]['image'] = GetConfig('img_prefix','http://www.le-live.com') . $val['image'];
        }
        $goods['images'] = $images;
        $data = [
            'detail' => $goods
        ];

        return JsonSuccess($data);
    }

    /**
     * 订单预览
     */
    public function preview(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $goods_id = $request->param('goods_id');
    }
}