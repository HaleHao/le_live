<?php

namespace app\api\controller;

use app\admin\model\MenusImage;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\WalletLog;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;
use app\admin\model\Menus;
use app\admin\model\Address;

class Mine extends Base
{
    /**
     * 个人中心
     */
    public function index()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->field(['avatar', 'gender', 'nickname', 'skill', 'is_auth', 'follower_num', 'fan_num', 'is_enter'])->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'detail' => $user
        ];
        return JsonSuccess($data);

    }

    /**
     * 会员权益
     */
    public function member_privilege()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if ($user->is_partner == 1) {
            $store = Db::name('store')->where('type', 2)->find();
        } else {
            $store = Db::name('store')->where('type', 1)->find();
        }
        $privilege = Db::name('store_privilege')->where('store_id', $store['id'])->select();
        foreach ($privilege as &$val) {
            $val['image'] = GetConfig('img_prefix') . $val['image'];
        }
        $data = [
            'avatar' => $user->avatar,
            'nickname' => $user->nickname,
            'list' => $privilege,
            'label' => $store['name'],
        ];
        return JsonSuccess($data);
    }

    /**
     * 个人信息
     */
    public function info()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Db::name('users')->where('id', $this->user_id)->field(['avatar', 'nickname', 'gender', 'mobile', 'province', 'city', 'district', 'signature', 'skill', 'image'])->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $user['avatar'])) {
            $user['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $user['avatar'];
        }

        if ($user['image']) {
            $user['show_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $user['image'];
        } else {
            $user['show_image'] = '';
        }

        if (!$user['province']) {
            $user['province'] = '';
        }

        if (!$user['city']) {
            $user['city'] = '';
        }

        if (!$user['district']) {
            $user['district'] = '';
        }

        $data = [
            'detail' => $user
        ];
        return JsonSuccess($data);
    }

    /**
     * 个人信息编辑
     */
    public function info_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $post = $request->param();
        $validate = new Validate([
            ['nickname', 'require', '昵称不能为空'],
            ['gender', 'require', '性别不能为空'],
            ['mobile', 'require', '手机号不能为空'],
//            ['profession', 'require', '专业不能为空'],
            ['province', 'require', '所在地区不能为空'],
            ['city', 'require', '所在地区不能为空'],
            ['district', 'require', '所在地区不能为空'],
            ['signature', 'require', '个人简介不能为空'],
            ['skill', 'require', '技能不能为空'],
        ]);

        if (!$validate->check($post)) {
            return JsonError($validate->getError());
        }
        if ($avatar = $request->param('avatar')) {
            $user->avatar = $avatar;
        }
        if ($image = $request->param('image')) {
            $user->image = $image;
        }
        $user->nickname = $post['nickname'];
        $user->gender = $post['gender'];
        $user->mobile = $post['mobile'];
        $user->city = $post['city'];
        $user->district = $post['district'];
        $user->province = $post['province'];
        $user->signature = $post['signature'];
//        $user->profession = $post['profession'];
        $user->skill = $post['skill'];
        $user->save();
        return JsonSuccess([], '保存成功');

    }


    /**
     * 入驻认证
     */
    public function auth_submit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if ($user->is_enter != 1) {
            return JsonError('你未入驻');
        }
        $card_front = $request->param('card_front');
        if (!$card_front) {
            return JsonError('请上传身份证正面');
        }
        $card_back = $request->param('card_back');
        if (!$card_back) {
            return JsonError('请上传身份证背面');
        }
        $user->card_front = $card_front;
        $user->card_back = $card_back;
        $user->is_auth = 0;
        if ($user->save()) {
            return JsonSuccess([], '提交成功，请等待审核');
        }
        return JsonError('提交失败');
    }

    /**
     * 入驻详情
     */
    public function auth_detail()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $data = [
            'card_front' => $user->card_front,
            'card_back' => $user->card_back
        ];
        return JsonSuccess($data);
    }

    /**
     * 我发布的菜谱
     */
    public function menus_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $page = $request->param('page');
        $list = Menus::alias('m')
            ->join('menus_reserve r', 'm.id=r.menu_id', 'left')
            ->where('m.user_id', $this->user_id)
            ->order('create_time', 'desc')
            ->field(['m.create_time', 'm.cover_image', 'm.id', 'm.title', 'm.introduce', 'm.like_num', 'r.price', 'm.collect_num'])->page($page, 10)->select();
        $count = Menus::where('user_id', $this->user_id)
            ->order('create_time', 'desc')
            ->count();
        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 菜谱详情
     */
    public function menus_edit(Request $request)
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
        $menu = Db::name('menus')->alias('m')
            ->join('column c', 'm.column_id=c.id', 'left')
            ->where('m.id', $menu_id)
            ->where('m.user_id', $this->user_id)
            ->field(['m.id', 'm.title', 'm.introduce', 'm.column_id', 'c.title as column_title'])
            ->find();
        if (!$menu) {
            return JsonError('数据获取失败');
        }
        $images = Db::name('menus_image')->where('menu_id', $menu_id)->field(['image'])->select();
        foreach ($images as $key => $image) {
            $images[$key]['show_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $image['image'];
        }
        $menu['images'] = $images;
        $data = [
            'detail' => $menu
        ];
        return JsonSuccess($data);

    }

    /**
     * 菜品保存
     */
    public function menus_save(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $menu_id = $request->param('menu_id');

        if (!$menu_id) {
            return JsonError('参数获取失败');
        }
        $post = $request->param();
        $validate = new Validate([
            ['title', 'require', '菜品标题不能为空'],
            ['introduce', 'require', '菜品介绍不能为空'],
            ['column_id', 'require', '请选择栏目'],
        ]);
        if (!$validate->check($post)) {
            return JsonError($validate->getError());
        }

        $images = json_decode($request->param('images'), true);
        if (!$images) {
            return JsonError('请上传图片');
        }

        Db::startTrans();
        try {
            $menu = Menus::where('id', $menu_id)->where('user_id', $this->user_id)->find();
            if (!$menu) {
                return JsonError('数据获取失败');
            }

            $menu->title = $post['title'];
            $menu->introduce = $post['introduce'];
            $menu->cover_image = $images[0];
            $menu->column_id = $post['column_id'];
            $menu->user_id = $this->user_id;

            $menu->save();
            //将之前的图片删除了
            Db::name('menus_image')->where('menu_id', $menu_id)->delete();
            //保存图片
            foreach ($images as $image) {
                $image_model = new MenusImage();
                $image_model->image = $image;
                $image_model->menu_id = $menu_id;
                $image_model->type = 'image';
                $image_model->save();
            }
            Db::commit();
            return JsonSuccess(['id' => $menu_id], '修改成功');
        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('修改失败');
        }
    }

    /**
     * 菜谱删除
     */
    public function menus_delete(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $menu_id = $request->param('menu_id');

        if (!$menu_id) {
            return JsonError('参数获取失败');
        }

        Db::startTrans();
        try {
            $menu = Menus::where('id', $menu_id)->where('user_id', $this->user_id)->find();
            if (!$menu) {
                return JsonError('参数获取失败');
            }
            $reserve = Db::name('menus_reserve')->where('menu_id', $menu_id)->find();
            if ($reserve) {
                return JsonError('该菜品为预约菜品，不能删除');
            }
            $menu->delete();
            //查找出图片进行删除
            Db::name('menus_image')->where('menu_id', $menu_id)->delete();
            Db::commit();
            return JsonSuccess([], '删除成功');
        } catch (Exception $exception) {
            Db::rollback();;
            return JsonError('删除失败');

        }
    }

    /**
     * 我发布的可预约
     */
    public function reserve_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }
        $page = $request->param('page');
        $list = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'r.menu_id=m.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->field(['m.create_time', 'm.cover_image', 'r.id', 'm.id as menu_id', 'm.title', 'm.introduce', 'm.like_num', 'r.price', 'm.collect_num'])
            ->page($page, 10)
            ->select();

        $count = Db::name('menus_reserve')->alias('r')
            ->join('menus m', 'r.menu_id=m.id', 'left')
            ->where('m.user_id', $this->user_id)
            ->count();
        foreach ($list as $key => $val) {
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
            $list[$key]['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count,
        ];
        return JsonSuccess($data);
    }

    /**
     * 预约菜单编辑
     */
    public function reserve_edit(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }

        $reserve_id = $request->param('reserve_id');
        if (!$reserve_id) {
            return JsonError('参数获取失败');
        }
        $reserve = Db::name('menus_reserve')->alias('r')
            ->join('address a', 'a.id=r.address_id', 'left')
            ->join('menus m', 'r.menu_id=m.id', 'left')
            ->field(['r.id', 'r.menu_id', 'm.title', 'r.serving_date', 'r.start_date', 'r.end_date', 'r.price', 'r.total_amount', 'r.explain', 'r.is_pick', 'r.finish_time', 'a.id as address_id', 'a.province', 'a.city', 'a.district', 'a.detail', 'a.name', 'a.mobile'])
            ->where('r.id', $reserve_id)->where('r.user_id', $this->user_id)->find();
        if (!$reserve) {
            return JsonError('数据获取失败');
        }
        $data = [
            'detail' => $reserve
        ];
        return JsonSuccess($data);

    }

    /**
     * 预约菜单保存
     */
    public function reserve_save(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if ($user->is_auth == 0) {
            return JsonAuth();
        }

        $reserve_id = $request->param('reserve_id');

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
            $reserve = MenusReserve::where('id', $reserve_id)->where('user_id', $this->user_id)->find();

            if (!$reserve) {
                return JsonError('数据获取失败');
            }
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

            Db::commit();

            return JsonSuccess();

        } catch (Exception $exception) {

            Db::rollback();
            return JsonError('提交失败');
        }
    }

    /**
     * 预约菜单删除
     */
    public function reserve_delete(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        if (!$user->is_auth) {
            return JsonAuth();
        }

        $reserve_id = $request->param('reserve_id');

        if (!$reserve_id) {
            return JsonError('参数获取失败');
        }

        Db::startTrans();
        try {
            $menu = MenusReserve::where('id', $reserve_id)->where('user_id', $this->user_id)->find();
            if (!$menu) {
                return JsonError('参数获取失败');
            }
            $menu->delete();
            //查找出图片进行删除
            Db::commit();
            return JsonSuccess([], '删除成功');
        } catch (Exception $exception) {
            Db::rollback();;
            return JsonError('删除失败');

        }
    }


    /**
     * 收藏列表
     */
    public function collect_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('menus_collect')->alias('c')
            ->join('menus m', 'c.menu_id=m.id', 'left')
            ->where('c.user_id', $this->user_id)
            ->order('c.create_time', 'desc')
            ->field(['m.id', 'm.cover_image', 'm.title', 'm.collect_num'])
            ->page($page, 10)
            ->select();
        $count = Db::name('menus_collect')->where('user_id', $this->user_id)->count();

        foreach ($list as $key => $val) {
            $list[$key]['cover_image'] = GetConfig('img_prefix', 'http://www.le-live.com') . $val['cover_image'];
        }

        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);
    }

    /**
     * 优惠券列表
     */
    public function coupon_list(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $subQueryb = Db::name('users_coupon')
            ->field('coupon_id')
            ->where('user_id', $this->user_id)
            ->buildSql();
        $type = $request->param('type', 1);
        //未领取的卡券
        $list = [];
        if ($type == 1) {
//
            $list = Db::name('coupon')
                ->where('number', '>', 0)
                ->where('end_time', '>', time())
                ->where('id Not IN ' . $subQueryb)
                ->field('id,title,price,conditions,start_date,start_time,end_date,end_time')
//                ->page(2,5)
                ->select();
        }


        //已领取的卡券
        if ($type == 2) {
            $list = Db::name('users_coupon')->alias('u')
                ->join('coupon c', 'c.id=u.coupon_id', 'left')
                ->where('c.end_time', '>', time())
                ->where('u.status', 0)
                ->where('u.user_id', $this->user_id)
                ->field('c.id,c.title,c.price,c.conditions,c.start_date,c.start_time,c.end_date,c.end_time')
                ->select();
//            var_dump($list);
//            exit;
        }

        //已使用的卡券
        if ($type == 3) {
            $list = Db::name('users_coupon')->alias('u')
                ->join('coupon c', 'u.coupon_id=c.id', 'left')
                ->where('u.status', 1)
                ->where('u.user_id', $this->user_id)
                ->field(['c.id,c.title,c.price,c.conditions,c.start_date,c.start_time,c.end_date,c.end_time'])
                ->select();
        }

        $unclaimed = Db::name('coupon')
            ->where('number', '>', 0)
            ->where('end_time', '>', time())
            ->where('id Not IN ' . $subQueryb)->count();
        $already = Db::name('users_coupon')->alias('u')
            ->join('coupon c', 'c.id=u.coupon_id', 'left')
            ->where('c.end_time', '>', time())
            ->where('u.status', 0)
            ->where('u.user_id', $this->user_id)->count();
        $use = Db::name('users_coupon')->alias('u')
            ->join('coupon c', 'u.coupon_id=c.id', 'left')
            ->where('u.status', 1)
            ->where('u.user_id', $this->user_id)->count();

        $data = [
            'list' => $list,
            'unclaimed' => $unclaimed,
            'already' => $already,
            'use' => $use
        ];

        return JsonSuccess($data);
    }

    /**
     * 领取优惠券
     */
    public function coupon_draw(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $coupon_id = $request->param('coupon_id');
        if (!$coupon_id) {
            return JsonError('参数获取失败');
        }
        Db::startTrans();
        try {
            $user_coupon = Db::name('users_coupon')
                ->where('user_id', $this->user_id)
                ->where('coupon_id', $coupon_id)
                ->find();
            if ($user_coupon) {
                return JsonError('您已经领取过了');
            }
            $coupon = Db::name('coupon')
                ->where('end_time', '>', time())
                ->where('id', $coupon_id)
                ->find();

            if (!$coupon) {
                return JsonError('优惠券获取失败');
            }
            $data = [
                'user_id' => $this->user_id,
                'coupon_id' => $coupon_id,
                'status' => 0,
                'create_time' => time(),
                'update_time' => time()
            ];
            Db::name('users_coupon')->insert($data);

            Db::name('coupon')->where('id', $coupon_id)->update([
                'number' => $coupon['number'] - 1
            ]);
            Db::commit();

            return JsonSuccess([], '领取成功');

        } catch (Exception $exception) {
            Db::rollback();
            return JsonError('领取失败');
        }

    }

    /**
     * 我的钱包余额
     */
    public function wallet_balance(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }

        $user = Users::where('id', $this->user_id)->find();

        $data = [
            'detail' => [
                'balance' => $user->balance
            ]
        ];
        return JsonSuccess($data);
    }

    /**
     * 我余额明细
     */
    public function wallet_log(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('wallet_log')
            ->where('user_id', $this->user_id)
            ->page($page, 10)
            ->select();
        $count = Db::name('wallet_log')->where('user_id', $this->user_id)->count();
        foreach ($list as $key => $val) {
            $list[$key]['create_time'] = date('Y-m-d H:i', $val['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);
    }

    /**
     * 钱包提现
     */
    public function wallet_withdraw(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }
        $money = $request->param('money');
        if ($money < 1) {
            return JsonError('金额大于1');
        }

        //TODO 用户提现接口

        $log = new WalletLog();
        $log->user_id = $this->user_id;
        $log->content = '提现';
        $log->money = $money;
        $log->type = 2;
        $log->save();

        return JsonSuccess();

    }

    /**
     * 信用度
     */
    public function credit_line(Request $request)
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$this->user_id) {
            return JsonLogin();
        }
        $page = $request->param('page', 1);
        $list = Db::name('credit_log')->where('user_id', $this->user_id)->page($page, 10)->select();
        $count = Db::name('credit_log')->where('user_id', $this->user_id)->count();
        foreach ($list as $key => $val) {
            $list[$key]['create_time'] = date('Y-m-d H:i', $val['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count,
            'credit_line' => $user->credit_line
        ];
        return JsonSuccess($data);
    }


    /**
     * 粉丝列表
     */
    public function fan_list(Request $request)
    {

        $chef_id = $request->param('chef_id');
        $page = $request->param('page', 1);
        if ($chef_id) {
            $list = Db::name('users_follower')->alias('f')
                ->join('users u', 'f.user_id=u.id', 'left')
                ->join('users_follower f2', 'f2.user_id=' . $chef_id . ' and f2.chef_id=f.user_id', 'left')
                ->where('f.chef_id', $chef_id)
                ->field(['u.id', 'u.nickname', 'u.gender', 'u.avatar', 'u.fan_num', 'u.credit_line', 'u.is_enter', 'f2.id as is_follower'])
                ->order('f.create_time', 'desc')
                ->page($page, 10)
                ->select();
            foreach ($list as $key => $item) {
                if ($item['id']) {
                    if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $item['avatar'])) {
                        $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $item['avatar'];
                    }
                    $list[$key]['menu_num'] = Db::name('menus')->where('user_id', $item['id'])->count();
                    $is_follower = 0;
                    if ($item['is_follower']) {
                        $is_follower = 1;
                    }
                    $list[$key]['is_follower'] = $is_follower;
//                $arr[]['id'] = $item['id'];
//                $arr[]['nickname'] = $item['nickname'];
//                $arr[]['gender'] = $item['gender'];
//                $arr[]['fan_num'] = $item['fan_num'];
//                $arr[]['credit_line'] = $item['credit_line'];
//                $arr[]['is_enter'] = $item['is_enter'];
                }
            }
            $count = Db::name('users_follower')->where('chef_id', $chef_id)->count();

        } else {
            $page = $request->param('page', 1);
            $list = Db::name('users_follower')->alias('f')
                ->join('users u', 'f.user_id=u.id', 'left')
                ->join('users_follower f2', 'f2.user_id=' . $this->user_id . ' and f2.chef_id=f.user_id', 'left')
                ->where('f.chef_id', $this->user_id)
                ->field(['u.id', 'u.nickname', 'u.gender', 'u.avatar', 'u.fan_num', 'u.credit_line', 'u.is_enter', 'f2.id as is_follower'])
                ->order('f.create_time', 'desc')
                ->page($page, 10)
                ->select();
            foreach ($list as $key => $item) {
                if ($item['id']) {
                    if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $item['avatar'])) {
                        $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $item['avatar'];
                    }
                    $list[$key]['menu_num'] = Db::name('menus')->where('user_id', $item['id'])->count();
                    $is_follower = 0;
                    if ($item['is_follower']) {
                        $is_follower = 1;
                    }
                    $list[$key]['is_follower'] = $is_follower;
//                $arr[]['id'] = $item['id'];
//                $arr[]['nickname'] = $item['nickname'];
//                $arr[]['gender'] = $item['gender'];
//                $arr[]['fan_num'] = $item['fan_num'];
//                $arr[]['credit_line'] = $item['credit_line'];
//                $arr[]['is_enter'] = $item['is_enter'];
                }
            }
            $count = Db::name('users_follower')->where('chef_id', $this->user_id)->count();
        }


        $data = [
            'list' => $list,
            'count' => $count
        ];
        return JsonSuccess($data);

    }


    /**
     * 关注人的列表
     */
    public function follower_list(Request $request)
    {
        $chef_id = $request->param('chef_id');
        $page = $request->param('page', 1);

        if ($chef_id) {
            $list = Db::name('users_follower')->alias('f')
                ->join('users u', 'f.chef_id=u.id', 'left')
                ->where('f.user_id',$chef_id)
                ->field(['u.id', 'u.nickname', 'u.gender', 'u.avatar', 'u.fan_num', 'u.credit_line', 'u.is_enter'])
                ->order('f.create_time', 'desc')
                ->page($page, 10)
                ->select();

            foreach ($list as $key => $item) {
                if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $item['avatar'])) {
                    $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $item['avatar'];
                }
                $list[$key]['menu_num'] = Db::name('menus')->where('user_id', $item['id'])->count();
            }
            $count = Db::name('users_follower')->where('user_id', $chef_id)->count();
        } else {

            $list = Db::name('users_follower')->alias('f')
                ->join('users u', 'f.chef_id=u.id', 'left')
                ->where('f.user_id', $this->user_id)
                ->field(['u.id', 'u.nickname', 'u.gender', 'u.avatar', 'u.fan_num', 'u.credit_line', 'u.is_enter'])
                ->order('f.create_time', 'desc')
                ->page($page, 10)
                ->select();

            foreach ($list as $key => $item) {
                if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $item['avatar'])) {
                    $list[$key]['avatar'] = GetConfig('img_prefix', 'http://www.le-live.com') . $item['avatar'];
                }
                $list[$key]['menu_num'] = Db::name('menus')->where('user_id', $item['id'])->count();
            }
            $count = Db::name('users_follower')->where('user_id', $this->user_id)->count();
        }
        $data = [
            'list' => $list,
            'count' => $count
        ];

        return JsonSuccess($data);
    }


    /**
     * 红包海报
     */
    public function red_poster()
    {
        if (!$this->user_id) {
            return JsonLogin();
        }
        $user = Users::where('id', $this->user_id)->find();
        if (!$user) {
            return JsonLogin();
        }

        return JsonSuccess([
            'red_poster' => GetConfig('img_prefix', 'http://www.le-live.com') . $user->red_poster,
            'user_id' => $user->id,
        ]);

    }
}