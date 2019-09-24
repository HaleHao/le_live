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

// 应用公共文件

/**
 * 根据附件表的id返回url地址
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
function geturl($id)
{
	if ($id) {
		$geturl = \think\Db::name("attachment")->where(['id' => $id])->find();
		if($geturl['status'] == 1) {
			//审核通过
			return $geturl['filepath'];
		} elseif($geturl['status'] == 0) {
			//待审核
			return '/uploads/xitong/beiyong1.jpg';
		} else {
			//不通过
			return '/uploads/xitong/beiyong2.jpg';
		} 
    }
    return false;
}



/**
 * 获取系统配置
 * @param null $name
 * @param null $default
 * Date: 2019/9/16 0016
 */
function GetConfig($name=null,$default=null)
{
    if ($name){
        $res = \think\Db::name('config')->where('name',$name)->find();
//        dump($value);exit;
//        return $value;
        if ($res['value']){
            return $res['value'];
        }else{
            return $default;
        }
    }else{
        $res = \think\Db::name('config')->field(['name','value'])->select();
//        return $value;
//        dump($res);
//        exit;
        foreach($res as $key => $val){
            $arr[$val['name']] = $val['value'];
        }
//       /**/foreach($arr as $val){
//            dump($arr);
//            exit;
////       }
        return $arr;
    }
}


/**
 * 返回json数据
 * Date: 2019/9/16 0016
 */
function JsonSuccess($data=[],$msg="成功",$code=200)
{
    $json = [
        'data' => $data,
        'msg' => $msg,
        'code' => $code,
    ];
    return json($json);
}

/**
 *  返回json数据
 * @param string $msg
 * @param string $data
 * @param int $cde
 * Date: 2019/9/16 0016
 */
function JsonError($msg='失败',$code=20001,$data=[])
{
    $json = [
        'data' => $data,
        'msg' => $msg,
        'code' => $code
    ];
    return json($json);
}


/**
 *  返回json数据
 * @param string $msg
 * @param string $data
 * @param int $cde
 * Date: 2019/9/16 0016
 */
function JsonLogin($msg='微信未授权',$code=40005,$data=[])
{
    $json = [
        'data' => $data,
        'msg' => $msg,
        'code' => $code
    ];
    return json($json);
}


function JsonAuth($msg='未缴纳保证金',$code=40006,$data=[])
{
    $json = [
        'data' => $data,
        'msg' => $msg,
        'code' => $code
    ];
    return json($json);
}

/**
 * 距离计算
 * @param $from
 * @param $to
 * @param bool $km
 * @param int $decimal
 * @return float
 * Date: 2019/9/17 0017
 */
function GetDistance($from, $to, $km = true, $decimal = 2)
{
    sort($from);
    sort($to);
    $EARTH_RADIUS = 6370.996; // 地球半径系数
    $distance = $EARTH_RADIUS * 2 * asin(sqrt(pow(sin(($from[0] * pi() / 180 - $to[0] * pi() / 180) / 2), 2) + cos($from[0] * pi() / 180) * cos($to[0] * pi() / 180) * pow(sin(($from[1] * pi() / 180 - $to[1] * pi() / 180) / 2), 2))) * 1000;
    if ($km) {
        $distance = $distance / 1000;
    }
    return round($distance, $decimal);
}


/**
 * 获取订单号
 * @return string
 * Date: 2019/9/23 0023
 */
function GetOrderNo()
{
    $order_no = date('YmdHis') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    return $order_no;
}


/**
 * 树状数组
 * @param $items
 * @param string $pid
 * @return array
 * Date: 2019/9/20 0020
 */
function ToTree($items,$pid ="parent_id") {

    $map  = [];
    $tree = [];
    foreach ($items as &$it){ $map[$it['id']] = &$it; }  //数据的ID名生成新的引用索引树
    foreach ($items as &$it){
        $parent = &$map[$it[$pid]];
        if($parent) {
            $parent['son'][] = &$it;
        }else{
            $tree[] = &$it;
        }
    }
    return $tree;
}

/**
 * [SendMail 邮件发送]
 * @param [type] $address  [description]
 * @param [type] $title    [description]
 * @param [type] $message  [description]
 * @param [type] $from     [description]
 * @param [type] $fromname [description]
 * @param [type] $smtp     [description]
 * @param [type] $username [description]
 * @param [type] $password [description]
 */
function SendMail($address)
{
    vendor('phpmailer.PHPMailerAutoload');
    //vendor('PHPMailer.class#PHPMailer');
    $mail = new \PHPMailer();          
     // 设置PHPMailer使用SMTP服务器发送Email
    $mail->IsSMTP();                
    // 设置邮件的字符编码，若不指定，则为'UTF-8'
    $mail->CharSet='UTF-8';         
    // 添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address); 

    $data = \think\Db::name('emailconfig')->where('email','email')->find();
            $title = $data['title'];
            $message = $data['content'];
            $from = $data['from_email'];
            $fromname = $data['from_name'];
            $smtp = $data['smtp'];
            $username = $data['username'];
            $password = $data['password'];   
    // 设置邮件正文
    $mail->Body=$message;           
    // 设置邮件头的From字段。
    $mail->From=$from;  
    // 设置发件人名字
    $mail->FromName=$fromname;  
    // 设置邮件标题
    $mail->Subject=$title;          
    // 设置SMTP服务器。
    $mail->Host=$smtp;
    // 设置为"需要验证" ThinkPHP 的config方法读取配置文件
    $mail->SMTPAuth=true;
    //设置html发送格式
    $mail->isHTML(true);           
    // 设置用户名和密码。
    $mail->Username=$username;
    $mail->Password=$password; 
    // 发送邮件。
    return($mail->Send());
}




/**
 * 阿里大鱼短信发送
 * @param [type] $appkey    [description]
 * @param [type] $secretKey [description]
 * @param [type] $type      [description]
 * @param [type] $name      [description]
 * @param [type] $param     [description]
 * @param [type] $phone     [description]
 * @param [type] $code      [description]
 * @param [type] $data      [description]
 */
function SendSms($param,$phone)
{
    // 配置信息
    import('dayu.top.TopClient');
    import('dayu.top.TopLogger');
    import('dayu.top.request.AlibabaAliqinFcSmsNumSendRequest');
    import('dayu.top.ResultSet');
    import('dayu.top.RequestCheckUtil');

    //获取短信配置
    $data = \think\Db::name('smsconfig')->where('sms','sms')->find();
            $appkey = $data['appkey'];
            $secretkey = $data['secretkey'];
            $type = $data['type'];
            $name = $data['name'];
            $code = $data['code'];
    
    $c = new \TopClient();
    $c ->appkey = $appkey;
    $c ->secretKey = $secretkey;
    
    $req = new \AlibabaAliqinFcSmsNumSendRequest();
    //公共回传参数，在“消息返回”中会透传回该参数。非必须
    $req ->setExtend("");
    //短信类型，传入值请填写normal
    $req ->setSmsType($type);
    //短信签名，传入的短信签名必须是在阿里大于“管理中心-验证码/短信通知/推广短信-配置短信签名”中的可用签名。
    $req ->setSmsFreeSignName($name);
    //短信模板变量，传参规则{"key":"value"}，key的名字须和申请模板中的变量名一致，多个变量之间以逗号隔开。
    $req ->setSmsParam($param);
    //短信接收号码。支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，一次调用最多传入200个号码。
    $req ->setRecNum($phone);
    //短信模板ID，传入的模板必须是在阿里大于“管理中心-短信模板管理”中的可用模板。
    $req ->setSmsTemplateCode($code);
    //发送
    

    $resp = $c ->execute($req);
}


/**
 * 替换手机号码中间四位数字
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function hide_phone($str){
    $resstr = substr_replace($str,'****',3,4);  
    return $resstr;  
}