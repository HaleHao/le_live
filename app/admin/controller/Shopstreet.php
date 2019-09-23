<?php

namespace app\admin\controller;

use app\admin\model\FitGym;
use app\admin\model\FitLaber;
use app\admin\model\FitUser;
use think\console\command\make\Model;
use \think\Db;
use \think\Cookie;
use \think\Session;
use app\admin\model\Admin as adminModel;//管理员模型
use app\admin\model\AdminMenu;
use app\admin\controller\Permissions;

class Shopstreet extends Permissions
{
    private $level = 10;

    /**
     * 编辑店铺商品
     */
    public function edit_goods()
    {
        $id = input('id');
        $gym_info = \db('admin')->where('id',Session::get('admin'))->field('id,gym_id')->find();
        if (request()->isPost()) {
            $post = $this->request->post();
            $validate = new \think\Validate([
                ['title', 'require', '商品名称不能为空'],
                ['type', 'require', '分类不能为空'],
                ['unit', 'require', '单位不能为空'],
                ['price', 'require', '价格不能为空'],
            ]);
            if (!$validate->check($post)) {
                $this->error('提交失败：' . $validate->getError());
            }
            $thumb = request()->file('thumb');
            if(!empty($thumb)){
                $data2 = '';
                foreach ($thumb as $file) {
                    $info = $file->getInfo();
                    $obj = addQiniu($info['tmp_name']);
                    if($obj['err'] == 2000){
                        $data['thumb'] = $obj['ret'];
                    }else{
                        $this->error('上传失败', 'gym/gym_screen');
                    }
                }
            }
            $main_figure = request()->file('main_figure');
            if(!empty($main_figure)){
                $data['main_figure'] = '';
                $data2 = '';
                foreach ($main_figure as $file) {
                    $info = $file->getInfo();
                    $obj = addQiniu($info['tmp_name']);
                    if($obj['err'] == 2000){
                        $data['thumb'] = $obj['ret'];
                    }else{
                        $this->error('上传失败', 'gym/gym_screen');
                    }
                }
            }
            if(!empty($post['spec_val'])){
                foreach ($post['spec_val'] as $k=>$v){
                    $act['spec'] = $v;
                    $post['inventory'][$k]?$act['inventory']=$post['inventory'][$k]:$act['inventory']=0;
                    $post['growth'][$k]?$act['growth']=$post['growth'][$k]:$act['growth']=0;
                    $arr[] = $act;
                }
                $data['spec'] = json_encode($arr);
            }
            !empty($post['is_recommend'])?$data['is_recommend']=1:$data['is_recommend']=2;
            $data['title'] = $post['title'];
            $data['edit_value'] = $post['edit_value'];
            $data['type'] = $post['type'];
            $data['unit'] = $post['unit'];
            $data['price'] = $post['price'];
            $data['ship_fee'] = $post['ship_fee'];
            $data['have_sales'] = $post['have_sales'];
            $data['content'] = $post['content'];
            if($id){
                $gym = \db('fit_gym_goods')->where('id',$id)->update($data);
            }else{
                $data['gym_id'] = $gym_info['gym_id'];
                $data['insert_at'] = date('Y-m-d H:i:s');
                $gym = \db('fit_gym_goods')->insertGetId($data);
            }
            if ($gym > 0) {
                $this->success('编辑成功', url('shopstreet/goods_list'));
            } else {
                $this->error('编辑失败', url('shopstreet/goods_list'));
            }
        } else {
            $ret = array();
            if($id){
                $ret = \db('fit_gym_goods')->alias('g')->where('g.id',$id)->field('g.*,t.pid as typeone,t.title as typeone_name,tt.title as typetwo_name')
                    ->join('fit_gym_goodstype t','t.id=g.type')
                    ->join('fit_gym_goodstype tt','tt.id=t.pid')
                    ->find();
                !empty($ret['thumb'])&&$ret['thumb']=changePath($ret['thumb']);
                $main_figure = array();
                if(!empty($ret['main_figure'])){
                    $main_figure = explode(':',trim($ret['main_figure'],':'));
                    foreach ($main_figure as $k=>$v){
                        !empty($v)&&$main_figure[$k]=changePath($v);
                    }
                }
                $this->assign('main_figure',$main_figure);
                $spec = array();
                $json_spec = json_decode($ret['edit_value'],true);
                if(!empty($json_spec)){
                    foreach ($json_spec as $k=>$v){
                        $spec_str = \db('spec')->where('id',$v['str'])->find();
                        if(!empty($v['arr'])){
                            foreach ($v['arr'] as $ko=>$vo){
                                $spec_str['arr'][] = \db('spec')->where('id',$vo)->find();
                            }
                            $spec[] = $spec_str;
                        }
                        $spec_i[] = $spec_str;
                    }
                    foreach ($spec as $k=>$v){
                        $spec[$k]['str'] = $k.'str';
                    }
                    if($ret['spec']){
                        $spec_val = json_decode($ret['spec'],true);
                        $specVal = array();
                        foreach ($spec_val as $k=>$v){
                            $string = explode(':',$v['spec']);
                            $act['inventory'] = $v['inventory'];
                            $act['growth'] = $v['growth'];
                            $act['spec'] = $v['spec'];
                            foreach ($string as $ko=>$vo){
                                $act[$ko.'str'] = $vo;
                            }
                            $specVal[] = $act;
                        }
                        $this->assign('spec_val',$specVal);
                    }
                    $this->assign('spec',$spec);
                    $this->assign('spec_i',$spec_i);
                }
            }
            $typeone = \db('fit_gym_goodstype')->where('pid',0)->select();
            $this->assign('info',$ret);
            $this->assign('typeone',$typeone);
        }
        $this->assign('id',$id);
        return $this->fetch();
    }

    /**
     * 删除店铺商品
     */
    public function del_goods_list(){
        $info = Db::name('fit_gym_goods')->where('id',input('id'))->field('thumb,main_figure')->find();
        $keys[] = $info['thumb'];
        $main_figure = explode(':',trim($info['main_figure'],':'));
        if(!empty($main_figure)){
            foreach ($main_figure as $k=>$v){
                $keys[] = $v;
            }
        }
        $ret = \db('fit_gym_goods')->where('id',input('id'))->delete();
        if ($ret) {
            !empty($keys)&&$obj = delQiniu($keys);
            return $data=[
                'code' => 0,
                'msg'  => '删除成功',
            ];
        } else {
            return $data=[
                'code' => 1,
                'msg'  => '删除失败',
            ];
        }
    }

    /**
     * 添加父类规格
     */
    public function addspec(){
        $edit_value = json_decode(input('edit_value'),true);
        $title = \db('spec')->where('title',input('value'))->where('pid',0)->find();
        $is_there = 0;
        $value = input('value');
        if(!empty($title)){
            $ret = $title['id'];
        }else{
            $data['title'] = input('value');
            $ret = \db('spec')->insertGetId($data);
        }
        if(!empty($edit_value)){
            foreach ($edit_value as $k=>$v){
                $v['str']==$ret&&$is_there=1;
            }
            if($is_there == 0){
                if(empty($edit_value)){
                    $edit_value[0]['str'] = $ret;
                    $edit_value[0]['arr'] = array();
                }else{
                    $key = count($edit_value);
                    if($key > 4){
                        return $data=[
                            'code' => 0,
                            'msg'  => '规格组最多5个',
                        ];
                    }
                    $edit_value[$key]['str'] = $ret;
                    $edit_value[$key]['arr'] = array();
                }
                $edit_value = json_encode($edit_value);
                $id = $ret.'spec';
                $opt = '<div class="layui-form-item" id="'.$id.'">
                        <label class="layui-form-label"></label>
                        <div class="dtb">
                            <div class="layui-input-inline dtt">
                                <div class="dtto">
                                    <div><b>'.$value.'</b></div>
                                    <a class="dttoo delspec" onclick="delone('.$ret.')">×</a>
                                </div>
                                <div class="dttt">
                                    <div class="son" id="'.$ret.'son'.'"></div>
                                    <div class="dtd">
                                        <div class="dtdo">规格值</div>
                                        <div class="dtdt"><input name="spec" lay-verify="required" id="'.$ret.'spectwo'.'" autocomplete="off" placeholder="颜色、尺码等" class="layui-input dtdta" type="text"></div>
                                        <div class="dtdf" onclick="addspecson('.$ret.')">添加</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                return $data=[
                    'code' => 1,
                    'msg'  => $opt,
                    'edit_value' => $edit_value,
                ];
            }else{
                return $data=[
                    'code' => 0,
                    'msg'  => '规格组已存在',
                ];
            }
        }else{
            $edit_value[0]['str'] = $ret;
            $edit_value[0]['arr'] = array();
            $edit_value = json_encode($edit_value);
            $id = $ret.'spec';
            $opt = '<div class="layui-form-item" id="'.$id.'">
                        <label class="layui-form-label"></label>
                        <div class="dtb">
                            <div class="layui-input-inline dtt">
                                <div class="dtto">
                                    <div><b>'.$value.'</b></div>
                                    <a class="dttoo delspec" onclick="delone('.$ret.')">×</a>
                                </div>
                                <div class="dttt">
                                    <div class="son" id="'.$ret.'son'.'"></div>
                                    <div class="dtd">
                                        <div class="dtdo">规格值</div>
                                        <div class="dtdt"><input name="spec" lay-verify="required" id="'.$ret.'spectwo'.'" autocomplete="off" placeholder="颜色、尺码等" class="layui-input dtdta" type="text"></div>
                                        <div class="dtdf" onclick="addspecson('.$ret.')">添加</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
            return $data=[
                'code' => 1,
                'msg'  => $opt,
                'edit_value' => $edit_value,
            ];
        }
    }

    /**
     * 添加子类规格
     */
    public function addspecson(){
        $edit_value = json_decode(input('edit_value'),true);
        $title = \db('spec')->where('title',input('value'))->where('pid',input('id'))->find();
        $value = input('value');
        if(!empty($title)){
            $ret = $title['id'];
        }else{
            $data['title'] = input('value');
            $data['pid'] = input('id');
            $ret = \db('spec')->insertGetId($data);
        }
        if(!empty($edit_value)){
            $is_there = 0;
            foreach ($edit_value as $k=>$v){
                if(input('id') == $v['str']){
                    foreach ($v['arr'] as $ko=>$vo){
                        $vo==$ret&&$is_there=1;
                    }
                }
            }
            if($is_there == 0){
                foreach ($edit_value as $k=>$v){
                    input('id')==$v['str']&&$edit_value[$k]['arr'][] = $ret;
                }
                $edit_value_arr = json_encode($edit_value);
                $opt = '<div class="dttto" id="'.$ret.'cl'.'">
                        <span class="dtttot">'.$value.'</span>
                        <a class="dtttof" onclick="delspecson('.$ret.')">×</a>
                    </div>';
                $table = $this->getTable($edit_value);
                return $data=[
                    'code' => 1,
                    'msg'  => $opt,
                    'table'  => $table,
                    'edit_value' => $edit_value_arr,
                ];
            }else{
                return $data=[
                    'code' => 0,
                    'msg'  => '规格值已存在',
                ];
            }
        }else{
            foreach ($edit_value as $k=>$v){
                input('id') == $v['str']&&$edit_value[$k]['arr'][] = $ret;
            }
            $edit_value_arr = json_encode($edit_value);
            $opt = '<div class="dttto" id="'.$ret.'cl'.'">
                        <span class="dtttot">'.$value.'</span>
                        <a class="dtttof" onclick="delspecson('.$ret.')">×</a>
                    </div>';
            $table = $this->getTable($edit_value);
            return $data=[
                'code' => 1,
                'msg'  => $opt,
                'table'  => $table,
                'edit_value' => $edit_value_arr,
            ];
        }
    }

    public function getTable($edit_value){
        $retArr = array();
        $arr = array();
        foreach ($edit_value as $k=>$v){
            if(!empty($v['arr'])){
                $retArr[] = \db('spec')->where('id',$v['str'])->find();
                $a_Arr = array();
                foreach ($v['arr'] as $ko=>$vo){
                    $a_Arr[] = \db('spec')->where('id',$vo)->find();
                }
                if(!empty($a_Arr)){
                    $arr[] = $a_Arr;
                }
            }
        }
        $str = array();
        $num = count($arr);
        for ($i=0;$i<$num;$i++){
            $str[] = $i.'str';
        }
        $tableArr = $this->selectspec($arr,$num,$str);
        $table = '<thead>
                    <tr>';
        foreach ($retArr as $k=>$v){
            $table .= '<th>'.$v['title'].'</th>';
        }
        $table .= '<th>库存   <input type="text" style="height: 22px;width: 80px;" value="" class="layui-input layui-input-inline editinpi" id="setinventory"><a class="layui-btn layui-btn-xs layui-btn-primary" href="javascript:;" onclick="setinventory()">一键设置</a></th>
                        <th>价格   <input type="text" style="height: 22px;width: 80px;" value="" class="layui-input layui-input-inline editinpi" id="setgrowth"><a class="layui-btn layui-btn-xs layui-btn-primary" onclick="setgrowth()">一键设置</a></th>
                    </tr>
                    </thead>
                    <tbody>';
        foreach ($tableArr as $k=>$v){
            $tableStr = '<tr>';
            $tabv = '';
            for ($i=0;$i<$num;$i++){
                $tableStr .= '<td>'.$v[$str[$i]].'</td>';
                $tabv .= $v[$str[$i]].':';
            }
            $tableStr .= '<td><input type="text" style="height: 22px;width: 80px;" class="layui-input layui-input-inline editinp a" value="" name="inventory[]"></td>
                        <td><input type="text" style="height: 22px;width: 80px;" class="layui-input layui-input-inline editinp b" value="" name="growth[]"><input type="hidden" value="'.trim($tabv,':').'" name="spec_val[]"></td>
                    </tr>';
            $table .= $tableStr;
        }
        $table .= '</tbody>';
        return $table;
    }

    public function selectspec($arr,$num,$str){
        $kongArr = array();
        for ($i=0;$i<$num;$i++){
            if($i == 0){
                foreach ($arr[$i] as $k=>$v){
                    $act[$str[$i]] = $v['title'];
                    $kongArr[] = $act;
                }
            }else{
                $kong = $kongArr;
                $kongArr = array();
                foreach ($kong as $k=>$v){
                    foreach ($arr[$i] as $ko=>$vo){
                        $act = $v;
                        $act[$str[$i]] = $vo['title'];
                        $kongArr[] = $act;
                    }
                }
            }
        }
        return $kongArr;
    }

    /**
     * 删除父类
     */
    public function delspec(){
        $edit_value = json_decode(input('edit_value'),true);
        foreach ($edit_value as $k=>$v){
            if($v['str'] == input('id')){
                unset($edit_value[$k]);
            }
        }
        $table = $this->getTable($edit_value);
        return $data=[
            'code' => 1,
            'msg' => input('id'),
            'id' => input('id').'spec',
            'table' => $table,
            'edit_value' => json_encode($edit_value),
        ];
    }
    /**
     * 删除子类
     */
    public function delspecson(){
        $edit_value = json_decode(input('edit_value'),true);
        foreach ($edit_value as $k=>$v){
            foreach ($v['arr'] as $ko=>$vo){
                if($vo == input('id')){
                    unset($edit_value[$k]['arr'][$ko]);
                }
            }
        }
        $table = $this->getTable($edit_value);
        return $data=[
            'code' => 1,
            'msg' => input('id'),
            'id' => input('id').'cl',
            'table' => $table,
            'edit_value' => json_encode($edit_value),
        ];
    }


}
