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
        $district = $request->param('district');
        //轮播图
        $banner = Banner::where('type', 1)->order('sort', 'asc')->order('create_time', 'desc')->field(['image', 'pages'])->select();
        foreach ($banner as &$val) {
            $val['image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['image'];
        }
        //栏目
        $column = Column::order('sort', 'asc')->order('Create_time', 'desc')->field(['id', 'image', 'title'])->select();
        foreach ($column as &$val) {
            $val['image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['image'];
        }
        //人气微厨
        $chef = Users::where('is_enter', 1)->order('like_num', 'desc')->order('fan_num', 'desc')->field(['id', 'nickname', 'image', 'avatar', 'skill', 'credit_line'])->limit(10)->select();

        if ($district) {
            $chef = Users::where('district', 'like', '%' . $district . '%')->order('like_num', 'desc')->order('fan_num', 'desc')->field(['id', 'nickname', 'image', 'avatar'])->limit(10)->select();
        }

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
     * 经纬度逆解析
     */
    public function analyze(Request $request)
    {
        $longitude = $request->param('longitude');
        $latitude = $request->param('latitude');
        if (!$longitude || !$latitude) {
            return JsonError('参数获取失败');
        }
        $result = $this->location($latitude, $longitude);
        return JsonSuccess($result);
    }

    /**
     * 附近美食
     */
    public function nearby_menus(Request $request)
    {
        $page = $request->param('page', 1);
        $list = Db::name('menus')->alias('m')
            ->join('address a', 'm.user_id=a.user_id and a.type=2', 'left')
            ->join('users u', 'm.user_id=u.id', 'left')
            ->join('menus_like l', 'm.id=l.menu_id and l.user_id='.$this->user_id, 'left')
            ->field(['m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname,l.id as is_like'])
            ->page($page, 10)->select();
        $count = Db::name('menus')->alias('m')
            ->join('address a', 'm.user_id=a.user_id and a.is_default=1', 'left')
            ->join('users u', 'm.user_id=u.id', 'left')
            ->join('menus_like l', 'm.id=l.menu_id and l.user_id=' . $this->user_id, 'left')
            ->count();

        $keyword = $request->param('keyword');
        if ($keyword) {
            $list = Db::name('menus')->alias('m')
                ->join('address a', 'm.user_id=a.user_id and a.is_default=1', 'left')
                ->join('users u', 'm.user_id=u.id', 'left')
                ->join('menus_like l', 'm.id=l.menu_id and l.user_id=' . $this->user_id, 'left')
                ->where('title', 'like', '%' . $keyword . '%')
                ->field(['m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname,l.id as is_like'])->page($page, 10)->select();
            $count = Db::name('menus')->alias('m')
                ->join('address a', 'm.user_id=a.user_id and a.is_default=1', 'left')
                ->join('users u', 'm.user_id=u.id', 'left')
                ->join('menus_like l', 'm.id=l.menu_id and l.user_id=' . $this->user_id, 'left')
                ->where('title', 'like', '%' . $keyword . '%')->count();
        }
        $longitude = $request->param('longitude');
        $latitude = $request->param('latitude');
        $to = [$longitude, $latitude];
        if ($list) {
            foreach ($list as $key => $val) {
                $form = [$val['longitude'], $val['latitude']];
                $list[$key]['distance'] = GetDistance($form, $to);
                $distance[] = $list[$key]['distance'];
                $list[$key]['is_like'] = $val['is_like'] ? 1 : 0;
                $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            }
            array_multisort($distance, SORT_ASC, $list);
        } else {
            $list = [];
        }
        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }

    /**
     *
     */
    public function is_show()
    {
        $data = [
            'is_show' => GetConfig('is_show_bar',0)
        ];
        return JsonSuccess($data);
    }

}