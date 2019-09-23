<?php

namespace app\api\controller;


use app\admin\model\Menus as MenusModel;
use app\admin\model\MenusCollect;
use app\admin\model\MenusComment;
use app\admin\model\MenusImage;
use app\admin\model\MenusLike;
use app\admin\model\MenusReserve;
use app\admin\model\Users;
use app\admin\model\UsersFollower;
use think\Db;
use think\Exception;
use think\Request;
use think\Validate;

class MenusOrder extends Base
{
    //订单提交预览页面
    public function preview(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

        $menu_ids = $request->param('menu_ids');

        

    }
}