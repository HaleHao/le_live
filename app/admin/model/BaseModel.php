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
class BaseModel extends Model
{
    public function prefixImgUrl($value)
    {
        if (!preg_match('/(http:\/\/)|(https:\/\/)/i', $value) && $value) {
            return GetConfig('img_prefix', 'http://www.le-live.com') . $value;
        }else{
            return $value;
        }

    }
}
