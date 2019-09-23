<?php

namespace app\api\controller;

use app\admin\model\Users;
use think\Db;
use think\Request;
use app\admin\model\Menus;

class Common extends Base
{
    /**
     * 图片上传方法
     */
    public function upload($module='user',$use='wehcat_image')
    {
        if($this->request->file('file')){
            $file = $this->request->file('file');
        }else{
            return JsonError('没有上传文件');
        }
        $module = $this->request->has('module') ? $this->request->param('module') : $module;//模块
        $web_config = Db::name('webconfig')->where('web','web')->find();
        $info = $file->validate(['size'=>$web_config['file_size']*1024,'ext'=>$web_config['file_type']])->rule('date')->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $module . DS . $use);
        if($info) {
            //写入到附件表
            $data = [];
            $data['module'] = $module;
            $data['filename'] = $info->getFilename();//文件名
            $data['filepath'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();//文件路径
            $data['fileext'] = $info->getExtension();//文件后缀
            $data['filesize'] = $info->getSize();//文件大小
            $data['create_time'] = time();//时间
            $data['uploadip'] = $this->request->ip();//IP
            $data['user_id'] = $this->user_id ? $this->user_id : 0;
            if($data['module'] = 'user') {
                //通过后台上传的文件直接审核通过
                $data['status'] = 1;
                $data['admin_id'] = $data['user_id'];
                $data['audit_time'] = time();
            }
            $data['use'] = $this->request->has('use') ? $this->request->param('use') : $use;//用处
            $res['id'] = Db::name('attachment')->insertGetId($data);
            $res['src'] = DS . 'uploads' . DS . $module . DS . $use . DS . $info->getSaveName();
            $res['url'] = GetConfig('img_prefix','http://www.le-live.com') . $res['src'];
//            addlog($res['id']);//记录日志
            return JsonSuccess($res);
        } else {
            // 上传失败获取错误信息
            return JsonError('上传失败：'.$file->getError());
        }
    }
}