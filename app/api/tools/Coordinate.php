<?php
/**
 * Created by PhpStorm.
 * User: EDZ
 * Date: 2018/7/25
 * Time: 10:53
 */

namespace app\api\tools;



class Coordinate
{
    public $x = 0;
    public $y = 0;
    /**
     * Coordinate constructor.
     * @param $lon float 经度
     * @param $lat float 纬度
     */
    public function __construct ($lon,$lat)
    {
        $this->x = $lon;
        $this->y = $lat;
    }

}