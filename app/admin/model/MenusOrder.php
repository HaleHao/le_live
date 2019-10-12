<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------


namespace app\admin\model;

use \think\Model;
class MenusOrder extends BaseModel
{
    public function menus()
    {
        return $this->hasMany('MenusOrderDetail','order_id','id')->field(['menu_id','order_id','title','cover_image','amount','unit_price','total_price','is_comment']);
    }
}
