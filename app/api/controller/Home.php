<?php

namespace app\api\controller;


use app\admin\model\Banner;
use app\admin\model\Column;
use app\admin\model\Menus;
use app\admin\model\Users;
use think\Db;
use think\Request;

class Home extends Base
{
    /**
     * 首页
     */
    public function index(Request $request)
    {

        //轮播图
        $banner = Banner::order('sort','asc')->order('create_time','desc')->field(['image','pages'])->select();

        //栏目
        $column = Column::order('sort','asc')->order('Create_time','desc')->field(['id','image','title'])->select();

        //人气微厨
        $chef = Users::order('like_num','desc')->order('fan_num','desc')->field(['id','nickname','image','avatar'])->limit(10)->select();

        //附近美味
        $data = [
            'banner' => $banner,
            'column' => $column,
            'chef' => $chef,
//            'menus' => $menus,
        ];

        return JsonSuccess($data);

    }


    /**
     * 附近美食
     */
    public function nearby_menus(Request $request)
    {
        $page = $request->param('page', 1);
        $query = Db::name('menus')->alias('m')
            ->join('address a', ['m.user_id=a.user_id', 'a.is_default=1'],'left')
            ->join('users u', ['m.user_id=a.id'],'left')
            ->join('menus_like l',['m.id=l.menu_id','l.user_id='.$this->user_id.''],'left')
            ->field(['m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname']);

        $keyword = $request->param('keyword');
        if($keyword){
            $query = $query->where('title','like','%'.$keyword.'%');
        }

        $data = $query->page($page, 10)->select();
        $clone = clone $query;
        $count = $clone->count();

        $longitude = $request->param('longitude');
        $latitude = $request->param('latitude');

        $to = [$longitude,  $latitude];
        foreach ($data as $key => &$val) {
            $form = [$val['longitude'], $val['latitude']];
            $val['distance'] = GetDistance($form, $to);
            $distance[] = $data[$key]['distance'];
            $val['is_like'] = $val['is_like'] ? 1:0;
        }
        array_multisort($distance, SORT_ASC, $data);

        $data = [
            'list' => $data,
            'count' => $count,
        ];

        return JsonSuccess($data);
    }

    



}