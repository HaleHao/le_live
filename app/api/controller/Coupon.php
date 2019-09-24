<?php
namespace app\api\controller;

use think\Request;

class Coupon extends Base
{
    public function lists(Request $request)
    {
        if (!$this->user_id){
            return JsonLogin();
        }

    }
}