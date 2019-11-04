<?php

namespace app\api\controller;


use app\admin\model\Column;
use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusLog;
use app\admin\model\MenusNotice;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\Address;
use app\admin\model\UsersFollower;
use app\api\service\AliyunSmsService;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use think\console\command\make\Model;
use think\Db;
use think\Exception;
use think\Log;
use think\Request;
use think\Validate;

class Menus extends Base
{
    /**
     * 获取菜品列表
     */
    public function lists(Request $request)
    {
        $column_id = $request->param('column_id');
//        if ($column_id)
//        $menus = Menus::where('column_id',$column_id)->select();

        $page = $request->param('page', 1);
//        $list = Db::name('menus')->alias('m')
//            ->join('address a', ['m.user_id=a.user_id', 'a.type=2'], 'left')
//            ->join('column c', ['m.column_id=c.id'], 'left')
//            ->join('menus_like l', ['m.id=l.menu_id', 'l.user_id=' . $this->user_id . ''], 'left')
//            ->where('m.column_id', $column_id)
//            ->where('m.is_pick', 1)
//            ->order('m.like_num', 'desc')
//            ->order('m.collect_num', 'desc')
//            ->field(['m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,c.title as label', 'l.id as is_like'])
//            ->page($page, 10)
//            ->select();
//        $count = Db::name('menus')->alias('m')
//            ->join('address a', ['m.user_id=a.user_id', 'a.is_default=1'], 'left')
//            ->join('column c', ['m.column_id=c.id'], 'left')
//            ->join('menus_like l', ['m.id=l.menu_id', 'l.user_id=' . $this->user_id . ''], 'left')
//            ->where('m.column_id', $column_id)
//            ->where('m.is_pick', 1)
//            ->count();
        $type = $request->param('type');

        $longitude = $request->param('longitude')?$request->param('longitude'):0;
        $latitude = $request->param('latitude')?$request->param('latitude'):0;

        if ($type == 1){
            $list = Db::query("select ROUND(6378.138*2*ASIN(SQRT(POW(SIN((". $latitude ."*PI()/180-a.latitude*PI()/180)/2),2)+COS(". $latitude ."*PI()/180)*COS(a.latitude*PI()/180)*POW(SIN((". $longitude ."*PI()/180-a.longitude*PI()/180)/2),2)))*1000) AS distance,m.id,m.title,m.column_id,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,c.title as label,l.id as is_like FROM le_menus AS m LEFT JOIN le_address AS a ON a.type = 2 AND a.user_id=m.user_id LEFT JOIN le_column AS c ON m.column_id=c.id LEFT JOIN le_menus_like AS l ON l.user_id=". $this->user_id ." AND l.menu_id=m.id WHERE m.column_id=". $column_id ." having distance >=0 order by distance asc limit ". ($page-1)*10 .", 10;");
        }else{
            $list = Db::query("select ROUND(6378.138*2*ASIN(SQRT(POW(SIN((". $latitude ."*PI()/180-a.latitude*PI()/180)/2),2)+COS(". $latitude ."*PI()/180)*COS(a.latitude*PI()/180)*POW(SIN((". $longitude ."*PI()/180-a.longitude*PI()/180)/2),2)))*1000) AS distance,m.id,m.title,m.column_id,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,c.title as label,l.id as is_like FROM le_menus AS m LEFT JOIN le_address AS a ON a.type = 2 AND a.user_id=m.user_id LEFT JOIN le_column AS c ON m.column_id=c.id LEFT JOIN le_menus_like AS l ON l.user_id=". $this->user_id ." AND l.menu_id=m.id WHERE m.column_id=". $column_id ." having distance >=0 order by m.like_num desc limit ". ($page-1)*10 .", 10;");
        }

        if ($list) {
            foreach ($list as $key => &$val) {
                $val['is_like'] = $val['is_like'] ? 1 : 0;
                $val['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
                $val['distance'] = round($val['distance']/1000,2);
            }
        }
        $return = [
            'list' => $list,
//            'count' => $count,
        ];
        return JsonSuccess($return);
    }

    /**
     * 菜谱详情
     */
    public function detail(Request $request)
    {
        $id = $request->param('menu_id');
        if (!$id) {
            return JsonError('参数获取失败');
        }

        $menu = MenusModel::with(['images', 'reserve'])
            ->where('id', $id)
            ->field(['id', 'title', 'introduce', 'user_id', 'is_reserve', 'like_num', 'collect_num', 'comment_num', 'create_time'])
            ->find();

        $notice = MenusNotice::where('user_id', $this->user_id)->where('menu_id', $id)->where('status', 0)->find();

        $is_notice = 0;
        if ($notice) {
            $is_notice = 1;
        }

        $is_me = 0;
        if ($menu->user_id == $this->user_id) {
            $is_me = 1;
        }
        if (!$menu) {
            return JsonError('数据获取失败');
        }

        if ($menu->reserve) {
            if ($menu->reserve['serving_date'] > date('Y-m-d')) {
                $menu['is_reserve'] = 1;

            } else {
                $menu['is_reserve'] = 0;
            }
            $menu['price'] = $this->menus_price($menu->reserve->price);

        } else {
            $menu['is_reserve'] = 0;
            $menu['price'] = 0;
        }
        $chef = Users::where('u.id', $menu->user_id)->alias('u')
            ->join('users_follower f', 'u.id=f.chef_id and f.user_id=' . $this->user_id, 'left')
            ->field(['u.id,u.nickname,u.avatar,u.city,u.signature,u.reg_time as create_time, f.id as is_follower,u.credit_line'])
            ->find();

        $chef->is_follower = $chef->is_follower ? 1 : 0;

//        $chef->create_time = $menu->create_time;

        $comment = Db::name('menus_comment')->alias('c')
            ->join('users u', 'c.user_id =u.id', 'left')
            ->where('menu_id', $id)
            ->where('parent_id', 0)
            ->field(['c.id,c.content,c.parent_id,u.avatar,u.nickname'])
            ->limit(3)
            ->select();

        $comment_num = Db::name('menus_comment')
            ->where('menu_id', $id)->count();
        $menu->comment_num = $comment_num;

        $reserve = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'm.id = r.menu_id', 'left')
            ->where('r.serving_date', $menu->reserve['serving_date'])
            ->where('r.user_id', $menu->user_id)
            ->where('r.serving_date', '>', date('Y-m-d'))
//            ->where('r.menu_id','<>',$id)
            ->field(['m.id,m.title,m.cover_image,r.price'])
            ->select();

        foreach ($reserve as $key => $val) {
            $reserve[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            $reserve[$key]['price'] = $this->menus_price($val['price']);
        }

        $comment = ToTree($comment);

        $like = MenusLike::where('user_id', $this->user_id)->where('menu_id', $menu->id)->find();

        $is_like = 0;
        if ($like) {
            $is_like = 1;
        }

        $collect = MenusCollect::where('user_id', $this->user_id)->where('menu_id', $menu->id)->find();

        $is_collect = 0;
        if ($collect) {
            $is_collect = 1;
        }

        $menus = MenusModel::where('user_id', $menu->user_id)
            ->order('like_num', 'desc')
            ->order('collect_num', 'desc')
            ->field(['id', 'title', 'cover_image'])
            ->select();

        $data = [
            'menu' => $menu,
            'is_like' => $is_like,
            'is_collect' => $is_collect,
            'chef' => $chef,
            'comment' => $comment,
            'menus' => $menus,
            'reserve' => $reserve,
            'is_me' => $is_me,
            'is_notice' => $is_notice,
        ];

        return JsonSuccess($data);
    }

    /**
     * 收藏
     */
    public function collect(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $id = $request->param('menu_id');
        if (!$id) {
            return JsonError('参数获取失败');
        }

        $menu = MenusModel::where('id', $id)->find();
        if (!$menu) {
            return JsonError('数据获取失败');
        }
        if ($this->user_id == $menu->user_id) {
            return JsonError('不能收藏自己的菜品');
        }

        $collect = MenusCollect::where('user_id', $this->user_id)->where('menu_id', $id)->find();
        if ($collect) {
            //删除收藏信息
            $menu->setDec('collect_num', 1);
            if ($collect->delete()) {
                return JsonSuccess([], '取消成功');
            }
            return JsonError('取消失败');
        } else {
            $menu->setInc('collect_num', 1);
            $collect = new MenusCollect();
            $collect->user_id = $this->user_id;
            $collect->menu_id = $id;
            if ($collect->save()) {
                return JsonSuccess([], '收藏成功');
            }
            return JsonError('收藏失败');
        }
    }

    /**
     * 喜欢
     */
    public function like(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $id = $request->param('menu_id');
        if (!$id) {
            return JsonError('参数获取失败');
        }

        $menu = MenusModel::where('id', $id)->find();
        if (!$menu) {
            return JsonError('数据获取失败');
        }
        if ($this->user_id == $menu->user_id) {
            return JsonError('不能给自己的菜品点赞');
        }

        $like = MenusLike::where('user_id', $this->user_id)->where('menu_id', $id)->find();

        if ($like) {
            //删除收藏信息
            if ($like->delete()) {
                $menu->setDec('like_num', 1);
                Users::where('id', $menu->user_id)->setDec('like_num', 1);
                return JsonSuccess([], '取消成功');
            }
            return JsonError('取消失败');
        } else {
            $like = new MenusLike();
            $like->user_id = $this->user_id;
            $like->menu_id = $id;

            if ($like->save()) {
                $menu->setInc('like_num', 1);
                Users::where('id', $menu->user_id)->setInc('like_num', 1);
                return JsonSuccess([], '点赞成功');
            }
            return JsonError('点赞失败');
        }
    }

    /**
     * 评论
     */
    public function comment_submit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $data = $request->param();
        $validate = new Validate([
            ['content', 'require', '评论内容不能为空'],
            ['menu_id', 'require', '菜品不能为空'],
        ]);
        if (!$validate->check($data)) {
            return JsonError($validate->getError());
        }

//        $order =
        $menu = MenusModel::where('id', $data['menu_id'])->find();
        if (!$menu) {
            return JsonError('该菜谱不存在');
        }


        $parent_id = $request->param('parent_id', 0);

        $comment = new MenusComment();
        $comment->content = $data['content'];
        $comment->menu_id = $data['menu_id'];
        $comment->user_id = $this->user_id;
        $comment->parent_id = $parent_id;
        $comment->to_user_id = $menu->user_id;
        $comment->type = 1;

        if ($images = $request->param('images')) {
            $comment->images = json_encode(json_decode($images, true));
        }
        if ($comment->save()) {
            return JsonSuccess([], '评论成功');
        }
        return JsonError('评论失败');

    }

    /**
     * 全部评论
     */
    public function comment_list(Request $request)
    {
//        var_dump(ROOT_PATH.'app/api/service/cert/apiclient_key.pem');
//        exit;
        $menu_id = $request->param('menu_id');
        if (!$menu_id) {
            return JsonError('参数获取失败');
        }
        $menu = Db::name('menus')->where('id', $menu_id)->find();
        $list = Db::name('menus_comment')->alias('c')
            ->join('users u', 'c.user_id=u.id')
            ->where('menu_id', $menu_id)
            ->field(['c.id', 'c.content', 'c.images', 'c.user_id', 'c.parent_id', 'c.create_time', 'c.type', 'u.avatar', 'u.nickname'])
            ->order('create_time', 'desc')
            ->select();
        foreach ($list as &$val) {
            $val['images'] = json_decode($val['images'], true);
            if ($val['images']) {
                foreach ($val['images'] as &$image) {
                    $image = GetConfig('img_prefix', 'http://www.le-live.com') . $image;
                }
            } else {
                $val['images'] = [];
            }
            if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $val['avatar'])) {
                $val['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['avatar'];
            }
            $val['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
            $val['is_author'] = $val['user_id'] == $menu['user_id'] ? 1 : 0;
        }
        $list = ToTree($list, 'parent_id');
        $data = [
            'list' => $list,
        ];
        return JsonSuccess($data);
    }

    /**
     * 栏目列表
     */
    public function column_list()
    {
        $list = Column::field(['id', 'title'])->select();
        $data = [
            'list' => $list
        ];
        return JsonSuccess($data);
    }

    /**
     * 是否入驻
     */
    public function is_enter()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'is_enter' => $user->is_enter
        ];
        return JsonSuccess($data);
    }

    /**
     * 是否认证
     */
    public function is_auth()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'is_auth' => $user->is_auth
        ];
        return JsonSuccess($data);
    }

    /**
     * 是否设置了自提地址
     */
    public function is_address()
    {
        if (!$this->user_id){
            return JsonLogin();
        }
        $user = Users::where('id',$this->user_id)->find();
        if (!$user){
            return JsonLogin();
        }

        $address = Address::where('user_id',$this->user_id)->where('type',2)->find();
        $is_address = 0;
        if ($address){
            $is_address = 1;
        }

        $data = [
            'is_address' => $is_address
        ];
        return JsonSuccess($data);
    }

    /**
     * 发布菜品
     */
    public function publish(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if ($user->is_enter != 1) {
            return JsonError('请先入驻微厨');
        }
        $data = $request->param();
        $validate = new Validate([
            ['title', 'require', '菜品标题不能为空'],
            ['introduce', 'require', '菜品介绍不能为空'],
            ['column_id', 'require', '请选择栏目'],
        ]);
        if (!$validate->check($data)) {
            return JsonError($validate->getError());
        }
        $images = json_decode($request->param('images'), true);

        if (!$images) {
            return JsonError('请上传图片');
        }
        Db::startTrans();
        try {
            $menu = new MenusModel();
//        if ($id = $request->param('id',0)){
//            $menu = MenusModel::where('user_id',$this->user_id)->where('id',$id)->find();
//        }
            $menu->title = $data['title'];
            $menu->introduce = $data['introduce'];
            $menu->cover_image = $images[0];
            $menu->column_id = $data['column_id'];
            $menu->user_id = $this->user_id;
//        $menu->like_num = 0;
//        $menu->collect_num = 0;
//        $menu->comment_num = 0;
//        $menu->is_reserve = 0;


            $menu->save();
            $id = $menu->getLastInsID();
            foreach ($images as $image) {
                $image_model = new MenusImage();
                $image_model->image = $image;
                $image_model->menu_id = $id;
                $image_model->type = 'image';
                $image_model->save();
            }

            //TODO 保存到menus_log
            $log = new MenusLog();
            $log->type = 1;
            $log->content = '发布了菜谱';
            $log->menu_id = $id;
            $log->user_id = $this->user_id;
            $log->save();




            Db::commit();
            return JsonSuccess(['id' => $id, 'chef_id' => $this->user_id], '发布成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('发布失败');
        }
    }

    /**
     * 发布可预约的菜品
     */
    public function reserve(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if ($user->is_enter != 1) {
            return JsonError('请先入驻微厨');
        }
        if ($user->is_auth != 1) {
            return JsonAuth();
        }

        $data = $request->param();
        $validate = new Validate([
            ['menu_id', 'require', '请选择菜谱'],
            ['serving_time', 'require', '上菜时间'],
            ['start_time', 'require', '开始时间'],
            ['end_time', 'require', '结束时间'],
            ['price', 'require', '请填写价格'],
            ['total_amount', 'require', '请填写总份数'],
        ]);
        if (!$validate->check($data)) {
            return JsonError($validate->getError());
        }

        if (date('Y-m-d', strtotime($data['serving_time'])) <= date('Y-m-d')) {
            return JsonError('不能发布今天和今天之前的预约');
        }

        if (date('Y-m-d', strtotime($data['serving_time'])) > date('Y-m-d', strtotime('+7 day'))) {
            return JsonError('不能发布七天后的预约');
        }

        if (date('H:i', strtotime($data['end_time'])) <= date('H:i', strtotime($data['start_time']))) {
            return JsonError('结束时间要大于开始时间');
        }


        Db::startTrans();
        try {
            $reserve = new MenusReserve();

            $reserve->user_id = $this->user_id;

            $reserve->menu_id = $data['menu_id'];

            $reserve->serving_time = strtotime($data['serving_time']);

            $reserve->serving_date = date('Y-m-d', strtotime($data['serving_time']));

            $reserve->end_time = strtotime($data['serving_time'] . $data['end_time']);

            $reserve->end_date = date('H:i', strtotime($data['end_time']));

            $reserve->start_time = strtotime($data['serving_time'] . $data['start_time']);

            $reserve->start_date = date('H:i', strtotime($data['start_time']));

            $reserve->price = $data['price'];

            $reserve->total_amount = $data['total_amount'];

            $reserve->explain = $data['explain'];

            $reserve->finish_time = $data['finish_time'] ? $data['finish_time'] : 0;

//            if ($is_pick = $request->param('is_pick', 0)) {
////                if ($address_id = $request->param('address_id')) {
//                $address = Address::where('user_id',$this->user_id)->where('type',2)->find();
//                if (!$address){
//                    return JsonError('你没有设置自提地址',20002);
//                }
            $is_pick = $request->param('is_pick', 0);
            $reserve->is_pick = $is_pick;
            if ($is_pick == 1) {
                $address = Address::where('user_id', $this->user_id)->where('type', 2)->find();
                if (!$address) {
                    return JsonError('你没有设置自提地址', 20002);
                }
                $address->is_pick = $is_pick;
                $address->save();
                $reserve->address_id = $address->id;
            } else {
                $address = Address::where('user_id', $this->user_id)->where('type', 2)->find();
                if ($address) {
                    $address->is_pick = $is_pick;
                    $address->save();
                }
            }
//                } else {

//                }
//            }

            $reserve->save();
            $id = $reserve->getLastInsID();

            $log = new MenusLog();
            $log->type = 2;
            $log->content = '发布了可预约菜谱';
            $log->menu_id = $data['menu_id'];
            $log->user_id = $this->user_id;
            $log->save();

//            $menu = MenusModel::where('id', $data['menu_id'])->where('user_id', $this->user_id)->find();
//            $menu->is_reserve = 1;
//            $menu->save();

            //TODO 发送通知
            $notice = Db::name('menus_notice')->alias('n')
                ->join('users u','n.user_id=u.id','left')
                ->where('n.menu_id',$data['menu_id'])
                ->where('n.status',0)
                ->select();
            foreach($notice as $item){
                $data = [
                    'name' => $this->filterEmoji($item['nickname']),
                ];
                $mobile = $item['mobile'];
                if ($mobile){
                    $sms = new AliyunSmsService();
                    $sms->sendSms($mobile,'SMS_176450119',$data);
                }
            }
            Db::commit();
            return JsonSuccess(['id' => $id, 'chef_id' => $this->user_id]);

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('提交失败');
        }
    }

    /**
     * 价格说明
     */
    public function explain()
    {
        $explain = Db::name('price_explain')->order('id','desc')->find();
        $data = [
            'name' => $explain['name'],
            'content' => $explain['content'],
        ];
        return JsonSuccess($data);
    }

    /**
     * 动态圈
     */
    public function posts(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page');
//        $list = Db::name('users_follower')->alias('f')
//            ->join('menus m','f.to_id = m.user_id','left')
//            ->join('menus_image i','i.menu_id=m.id','right')
//            ->where('from_id',$this->user_id)
//            ->field('m.*,i.*')
//            ->page($page,10)->select();


        $arr = Db::name('users_follower')->where('user_id', $this->user_id)->field('chef_id')->select();
        $arr = array_column($arr, 'chef_id');

        $list = MenusModel::with(['images', 'user'])->whereIn('user_id', $arr)
            ->order('create_time', 'desc')
            ->order('update_time', 'desc')
            ->order('like_num', 'desc')
            ->order('collect_num', 'desc')
//            ->field(['id,title,introduce,like_num,collect_num,create_time','user*'])
            ->page($page, 10)
            ->select();

        $count = MenusModel::with(['images', 'user'])->whereIn('user_id', $arr)->count();
//        $list = UsersFollower::with(['menus' => function($query){
//            return $query->with('images');
//        }])->select();
        foreach ($list as &$val) {
            $like = Db::name('menus_like')->where('user_id', $this->user_id)->where('menu_id', $val['id'])->find();
            $val['is_like'] = $like ? 1 : 0;
            $collect = Db::name('menus_collect')->where('user_id', $this->user_id)->where('menu_id', $val['id'])->find();
            $val['is_collect'] = $collect ? 1 : 0;
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 可预约的菜谱
     */
    public function workable()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $reserve = Db::name('menus_reserve')->where('user_id', $this->user_id)->field('menu_id')->select();
        $arr = array_column($reserve, 'menu_id');
        $list = Db::name('menus')
            ->where('user_id', $this->user_id)
            ->whereNotIn('id', $arr)
            ->field(['id', 'title'])
            ->select();
        $data = [
            'list' => $list,
        ];
        return JsonSuccess($data);
    }

    /**
     * 上架通知
     */
    public function on_notice(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }

        $menu_id = $request->param('menu_id');
        if (!$menu_id) {
            return JsonError('参数获取失败');
        }

        $menu = \app\admin\model\Menus::where('id', $menu_id)->find();
        if (!$menu) {
            return JsonError('数据获取失败');
        }

        $notice = new MenusNotice();
        $notice->user_id = $this->user_id;
        $notice->menu_id = $menu_id;
        $notice->status = 0;
        $notice->add_time = date('Y-m-d H:i:s');
        if ($notice->save()) {
            return JsonSuccess();
        }
        return JsonError();

    }

}