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
class MenusOrderDetail extends BaseModel
{
    public function menu()
    {
        return $this->hasOne('Menus','id','menu_id');
    }

    public function getCoverImageAttr($value)
    {
        return $this->prefixImgUrl($value);
    }
}
