<?php

namespace app\api\controller;



use app\admin\model\Users;

class Auto extends Base
{
    /**
     * 统计
     */
    public function statistics()
    {
        $users = Users::where('is_head',1)->select();

    }
}