<?php

namespace app\api\controller;


use app\admin\model\Banner;
use app\admin\model\Column;
use app\admin\model\Menus;
use app\admin\model\Users;
use app\api\tools\Coordinate;
use app\api\tools\CoordinateTool;
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
        $column = Column::order('sort', 'asc')->order('create_time', 'desc')->field(['id', 'image', 'title'])->select();
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
            'app_name' => GetConfig('app_name', '叮咛哒呤')
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


        $longitude = $request->param('longitude', 114.06031) ? $request->param('longitude', 114.06031) : 0;
        $latitude = $request->param('latitude', 22.72174) ? $request->param('latitude', 22.72174) : 0;

        $list = Db::query("select ROUND(6378.138*2*ASIN(SQRT(POW(SIN((" . $latitude . "*PI()/180-a.latitude*PI()/180)/2),2)+COS(" . $latitude . "*PI()/180)*COS(a.latitude*PI()/180)*POW(SIN((" . $longitude . "*PI()/180-a.longitude*PI()/180)/2),2)))*1000) AS distance,m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname,u.id as user_id,l.id as is_like FROM le_menus AS m LEFT JOIN le_address AS a ON a.type = 2 AND a.user_id=m.user_id LEFT JOIN le_users AS u ON u.id=m.user_id LEFT JOIN le_menus_like AS l ON l.user_id=" . $this->user_id . " AND l.menu_id=m.id having distance >=0 order by distance asc limit " . ($page - 1) * 10 . ", 10;");


        $keyword = $request->param('keyword');
        if ($keyword) {
            $list = $list = Db::query("select ROUND(6378.138*2*ASIN(SQRT(POW(SIN((" . $latitude . "*PI()/180-a.latitude*PI()/180)/2),2)+COS(" . $latitude . "*PI()/180)*COS(a.latitude*PI()/180)*POW(SIN((" . $longitude . "*PI()/180-a.longitude*PI()/180)/2),2)))*1000) AS distance,m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname,u.id as user_id,l.id as is_like FROM le_menus AS m LEFT JOIN le_address AS a ON a.type = 2 AND a.user_id=m.user_id LEFT JOIN le_users AS u ON u.id=m.user_id LEFT JOIN le_menus_like AS l ON l.user_id=" . $this->user_id . " AND l.menu_id=m.id WHERE m.title LIKE '%" . $keyword . "%' having distance >=0 order by distance asc limit " . ($page - 1) * 10 . ", 10;");
        }
        if ($list) {

            foreach ($list as $key => $val) {
//                if ($val['user_id']){
                $list[$key]['is_like'] = $val['is_like'] ? 1 : 0;
                $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
                //                $list[$key]['avatar'] =
                if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['avatar'])) {
                    $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['avatar'];
                } else {
                    $list[$key]['avatar'] = $val['avatar'];
                }
//                }
                $list[$key]['distance'] = round($val['distance'] / 1000, 2);
            }
        }

        $recommend = Db::query("select ROUND(6378.138*2*ASIN(SQRT(POW(SIN((" . $latitude . "*PI()/180-a.latitude*PI()/180)/2),2)+COS(" . $latitude . "*PI()/180)*COS(a.latitude*PI()/180)*POW(SIN((" . $longitude . "*PI()/180-a.longitude*PI()/180)/2),2)))*1000) AS distance,m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,u.avatar,u.nickname,u.id as user_id,l.id as is_like FROM le_menus AS m LEFT JOIN le_address AS a ON a.type = 2 AND a.user_id=m.user_id LEFT JOIN le_users AS u ON u.id=m.user_id LEFT JOIN le_menus_like AS l ON l.user_id=" . $this->user_id . " AND l.menu_id=m.id WHERE m.is_recommend=1 having distance >=0 order by distance asc limit " . ($page - 1) * 10 . ", 10;");

        if ($recommend) {

            foreach ($recommend as $key => $val) {
//                if ($val['user_id']){
                $recommend[$key]['is_like'] = $val['is_like'] ? 1 : 0;
                $recommend[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
                //                $list[$key]['avatar'] =
                if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['avatar'])) {
                    $recommend[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['avatar'];
                } else {
                    $recommend[$key]['avatar'] = $val['avatar'];
                }
//                }
                $recommend[$key]['distance'] = round($val['distance'] / 1000, 2);
            }
        }

        $data = [
            'list' => $list,
//            'count' => $count,
            'recommend' => $recommend,
        ];
        return JsonSuccess($data);
    }

    /**
     * 是否显示
     */
    public function is_show()
    {
        return JsonSuccess([
            'is_show' => 1
        ]);
    }

    /**
     * 底部导航栏
     */
    public function show_bar()
    {
//
//        $like_num = Db::name('menus_like')->alias('l')
//            ->join('menus m', 'l.menu_id=m.id', 'left')
//            ->join('users u', 'l.user_id=u.id', 'left')
//            ->where('m.user_id', $this->user_id)
//            ->where('l.is_read',0)
//            ->count();
//        $order_num = \app\admin\model\MenusOrder::where('user_id', $this->user_id)
//            ->whereOr('chef_id', $this->user_id)
//            ->where('is_read',0)
//            ->count();
//
//        $comment_num = Db::name('menus_comment')->alias('c')
//            ->join('menus m', 'c.menu_id=m.id', 'left')
//            ->join('users u', 'c.user_id=u.id', 'left')
//            ->where('m.user_id', $this->user_id)
//            ->where('c.to_user_id', $this->user_id)
//            ->where('c.is_read',0)
//            ->order('c.create_time', 'desc')->count();
//
//        $replay_num = Db::name('menus_comment')->alias('c')
//            ->join('menus m', 'c.menu_id=m.id', 'left')
//            ->join('menus_comment c2', 'c.parent_id=c2.id', 'left')
//            ->join('users u', 'u.id=c.to_user_id', 'left')
//            ->join('users r', 'r.id=c.user_id', 'left')
//            ->where('c.user_id', $this->user_id)
//            ->where('c.is_read', 0)
//            ->where('c.parent_id', '>', 0)
//            ->order('c.create_time', 'desc')
//            ->count();
//
//        $tip_num = $like_num + $order_num + $comment_num + $replay_num;

        $tip_num = 0;

        $data = [
            'tabbar' => [
                'color' => "#979797",
                'selectedColor' => "#000000",
                'backgroundColor' => "#ffffff",
                'borderStyle' => "#d7d7d7",
                'list' => [
                    [
                        'pagePath' => "/pages/index/index",
                        'text' => "首页",
                        'iconPath' => "/images/nav/index.png",
                        'selectedIconPath' => "/images/nav/indexs.png",
                        'selected' => true
                    ],
                    [
                        'pagePath' => "/pages/quan/quan",
                        'text' => "动态圈",
                        'iconPath' => "/images/nav/quan.png",
                        'selectedIconPath' => "/images/nav/quans.png",
                        'selected' => false
                    ],
                    [
                        'pagePath' => "/pages/fabu/fabu",
                        'text' => "",
                        'iconPath' => "/images/nav/fabu.png",
                        'selectedIconPath' => "/images/nav/fabu.png",
                        'selected' => false
                    ],
                    [
                        'pagePath' => "/pages/msg/msg",
                        'text' => "消息",
                        'tip_num' => $tip_num,
                        'iconPath' => "/images/nav/msg.png",
                        'selectedIconPath' => "/images/nav/msgs.png",
                        'selected' => false
                    ],
                    [
                        'pagePath' => "/pages/me/me",
                        'text' => "我的",
                        'iconPath' => "/images/nav/me.png",
                        'selectedIconPath' => "/images/nav/mes.png",
                        'selected' => false
                    ]
                ],
                'position' => "bottom"
            ]
        ];


        $data1 = [
            'tabbar' => [
                'color' => "#979797",
                'selectedColor' => "#000000",
                'backgroundColor' => "#ffffff",
                'borderStyle' => "#d7d7d7",
                'list' => [
                    [
                        'pagePath' => "/pages/index/index",
                        'text' => "首页",
                        'iconPath' => "/images/nav/index.png",
                        'selectedIconPath' => "/images/nav/indexs.png",
                        'selected' => true
                    ],
                    [
                        'pagePath' => "/pages/msg/msg",
                        'text' => "消息",
                        'iconPath' => "/images/nav/msg.png",
                        'selectedIconPath' => "/images/nav/msgs.png",
                        'selected' => false
                    ],
                    [
                        'pagePath' => "/pages/me/me",
                        'text' => "我的",
                        'iconPath' => "/images/nav/me.png",
                        'selectedIconPath' => "/images/nav/mes.png",
                        'selected' => false
                    ]
                ],
                'position' => "bottom"
            ]
        ];
        return JsonSuccess($data);
    }

    /**
     * 数据迁移专用
     */
    public function change()
    {
        $list = Users::where('store_id', -1)->select();
        foreach ($list as $key => $val) {
            $val->user_type = 0;
            $val->store_id = 0;
            $val->save();
        }


    }


    /**
     * 数据迁移专用
     */
    public function partner()
    {
        $list = Users::where('store_id', 2)->select();
        foreach ($list as $key => $val) {
            $val->user_type = 3;
            $val->store_id = 3;
            $val->is_partner = 1;
            $val->is_enter = 1;
            $val->save();
        }
    }

    /**
     * 事业部
     */
    public function bu()
    {
        $list = Users::where('store_id', 4)->select();
        foreach ($list as $key => $val) {
            $val->user_type = 4;
            $val->store_id = 4;
            $val->is_partner = 1;
            $val->is_enter = 1;
            $val->is_bu = 1;
            $val->save();
        }
    }

    /**
     * 微厨
     */
    public function chef()
    {
        $list = Users::where('store_id', 1)->select();
        foreach ($list as $key => $val) {
            $val->user_type = 1;
            $val->store_id = 1;
//            $val->is_partner = 1;
            $val->is_enter = 1;
//            $val->is_bu = 1;
            $val->save();
        }
    }

    /**
     *
     */
    public function second_user()
    {
        $list = Users::where('first_user_id', '>', 0)->select();
        foreach ($list as $key => $val) {
            $first_user = Users::where('id', $val->first_user_id)->find();
            if ($first_user) {
                $val->second_user_id = $first_user->first_user_id;
                $val->save();
            }
        }
    }


//    public function

    public function partner_id()
    {
        $list = Users::where('user_type','>',1)->where('first_user_id','>',0)->select();
        foreach ($list as $key => $val){
            $partner_id = $this->GetUpPartnerID($val->first_user_id);
            $val->partner_id = $partner_id;

            $val->save();
        }
    }

    public function GetUpPartnerID($id)
    {
//        var_dump($id . '/');
//        exit;
        $user = Users::where('id', $id)->find();
        if ($user->is_partner == 1) {
            $partner_id = $user->id;
        } else if ($user->first_user_id) {
            $first_user = Users::where('id', $user->first_user_id)->find();
            if ($first_user) {
                $partner_id = $this->GetUpPartnerID($user->first_user_id);
            } else {
                $partner_id = 0;
            }
        } else {
            $partner_id = 0;
        }
        return $partner_id;
    }


    public function store_num()
    {
        $list = Users::where('store_total_num','>',0)->select();
        foreach($list as $val){
            $val->store_residu_num = $val->store_total_num;
            $val->save();
        }
    }
}