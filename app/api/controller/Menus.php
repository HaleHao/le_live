<?php

namespace app\api\controller;


use app\admin\model\Column;
use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\Address;
use app\admin\model\UsersFollower;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
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
        $query = Db::name('menus')->alias('m')
            ->join('address a', ['m.user_id=a.user_id', 'a.is_default=1'], 'left')
            ->join('column c', ['m.column_id=c.id'], 'left')
            ->join('menus_like l', ['m.id=l.menu_id', 'l.user_id=' . $this->user_id . ''], 'left');

        if ($column_id) {
            $query = $query->where('m.column_id', $column_id);
        }
        $query1 = clone $query;
        $data = $query->field(['m.id,m.title,m.introduce,m.cover_image,m.like_num,a.longitude,a.latitude,c.title as label', 'l.id as is_like'])->page($page, 10)->select();
        $count = $query1->count();

        if ($data) {
            $longitude = $request->param('longitude');
            $latitude = $request->param('latitude');
            $to = [$longitude, $latitude];
            foreach ($data as $key => &$val) {
                $form = [$val['longitude'], $val['latitude']];
                $val['distance'] = GetDistance($form, $to);
                $distance[] = $data[$key]['distance'];
                $val['is_like'] = $val['is_like'] ? 1 : 0;
            }

            array_multisort($distance, SORT_ASC, $data);
        } else {
            $data = [];
        }
        $return = [
            'list' => $data,
            'count' => $count,
        ];
        return JsonSuccess($return);
    }

    /**
     * 菜谱详情
     * Date: 2019/9/17 0017
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
        $chef = Users::where('id', $menu->user_id)
            ->field(['id,nickname,avatar,city,signature'])
            ->find();

        $comment = Db::name('menus_comment')->alias('c')
            ->join('users u', 'c.user_id =u.id', 'left')
            ->where('menu_id', $id)
            ->where('parent_id', 0)
            ->field(['c.id,c.content,c.parent_id,u.avatar,u.nickname'])
            ->limit(3)
            ->select();

        $reserve = [];
        if ($menu->is_reserve) {
            $reserve = Db::name('menus_reserve')->alias('r')
                ->join('menus m', 'm.id = r.menu_id', 'left')
                ->where('serving_date', $menu->reserve->serving_date)
                ->field(['m.id,m.title,m.cover_image'])
                ->select();
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
            'reserve' => $reserve
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
            if ($collect->delete()) {
                return JsonSuccess([], '取消成功');
            }
            return JsonError('取消失败');
        } else {
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
                return JsonSuccess([], '取消成功');
            }
            return JsonError('取消失败');
        } else {
            $like = new MenusLike();
            $like->user_id = $this->user_id;
            $like->menu_id = $id;
            if ($like->save()) {
                return JsonSuccess([], '点赞成功');
            }
            return JsonError('点赞失败');
        }
    }


    /**
     * 评论
     */
    public function comment(Request $request)
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

        if ($images = $request->param('images')) {
            $comment->images = json_decode($images);
        }
        if ($comment->save()) {
            return JsonSuccess([], '评论成功');
        }
        return JsonError('评论失败');

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
     * 发布菜品
     */
    public function publish(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
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
                $image_model->save();
            }
            Db::commit();
            return JsonSuccess(['id' => $id], '发布成功');
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
        if ($user->is_auth == 0) {
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
            ['explain', 'require', '请填写微厨想说'],
            ['finish_time', 'require', '请选择菜谱完成后时间'],
        ]);
        if (!$validate->check($data)) {
            return JsonError($validate->getError());
        }

        Db::startTrans();
        try {
            $reserve = new MenusReserve();

            $reserve->menu_id = $data['menu_id'];

            $reserve->serving_time = strtotime($data['serving_time']);

            $reserve->serving_date = date('Y-m-d H:i', strtotime($data['serving_time']));

            $reserve->end_time = strtotime($data['serving_time'] . $data['end_time']);

            $reserve->end_date = $data['end_time'];

            $reserve->start_time = strtotime($data['serving_time'] . $data['start_time']);

            $reserve->end_date = $data['end_time'];

            $reserve->price = $data['price'];

            $reserve->total_amount = $data['total_amount'];

            $reserve->explain = $data['explain'];

            $reserve->finish_time = $data['finish_time'];

            if ($is_pick = $request->param('is_pick', 0)) {
                if ($address_id = $request->param('address_id')) {
                    $reserve->is_pick = $is_pick;
                    $reserve->address_id = $address_id;
                } else {
                    return JsonError('请选择自提地址');
                }
            }

            $reserve->save();
            $id = $reserve->getLastInsID();

            $menu = MenusModel::where('id', $data['menu_id'])->where('user_id', $this->user_id)->find();
            $menu->is_reserve = 1;
            $menu->save();
            Db::commit();
            return JsonSuccess(['id' => $id]);

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('提交失败');
        }
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


        $arr = Db::name('users_follower')->where('from_id', $this->user_id)->field('id')->select();
        $arr = array_column($arr, 'id');
        $query = MenusModel::with(['images', 'user'])->whereIn('user_id', $arr);
        $list = $query->order('create_time', 'desc')
            ->order('update_time', 'desc')
            ->order('like_num', 'desc')
            ->order('collect_num', 'desc')
//            ->field(['id,title,introduce,like_num,collect_num,create_time','user*'])
            ->page($page, 10)
            ->select();
        $count = $query->count();
//        $list = UsersFollower::with(['menus' => function($query){
//            return $query->with('images');
//        }])->select();

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }


    /**
     * 提交订单-预览页
     */
    public function order_preview(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
//        GetConfig('delivery_type');
        $menu_id = $request->param('menu_id');
        $menu = MenusModel::where('id', $menu_id)->find();
        if (!$menu) {
            return JsonError('菜品获取失败');
        }
        if ($menu->user_id == $this->user_id) {
            return JsonError('不能预约自己的菜品');
        }

        $address = Address::where('user_id', $this->user_id)->find();
        $pick_address = Address::where('user_id', $menu->user_id)->find();
//        $serving_time =
//        $coupon =
//
    }

    /**
     * 提交订单
     */
    public function order_submit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

    }

}