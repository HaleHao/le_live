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

class Menus extends BaseModel
{
    protected $table = 'le_menus';

    public function images()
    {
        return $this->hasMany('MenusImage','menu_id','id')->field(['id','menu_id','image']);
    }

    public function user()
    {
        return $this->hasOne('Users','id','user_id')->field(['id','avatar','nickname']);
    }

    public function comments()
    {
        return $this->hasMany('MenusComment','menu_id','id');
    }

    public function reserve()
    {
        return $this->hasOne('MenusReserve','menu_id','id');
    }

    public function getCoverImageAttr($value)
    {
        return $this->prefixImgUrl($value);
    }

}
