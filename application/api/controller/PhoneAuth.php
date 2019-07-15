<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Db;

class PhoneAuth extends ApiBase
{

    public function verifycode()
	{
		
		$mobile = trim(input('mobile'));
        $temp = trim(input('temp'));
        $auth = trim(input('auth'));
        $type = input('type/d',1);
        
		if( !$mobile || ($temp != 'sms_forget' && $temp != 'sms_reg' ) || !$auth ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！']);
        }

        $Md5 = md5($mobile . md5($temp . "android+app"));
        if( $Md5 != $auth ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'非法请求！']);
        }
        $member = Db::table('member')->where('mobile',$mobile)->value('id');
        if($type == 1){
            if (($temp == 'sms_forget') && empty($member)) {
                $this->ajaxReturn(['status' => -2 , 'msg'=>'此手机号未注册！']);
            }
            if (($temp == 'sms_reg') && !(empty($member))) {
                $this->ajaxReturn(['status' => -2 , 'msg'=>'此手机号已注册，请直接登录！']);
            }
        }
        $phone_number = checkMobile($mobile);
        if ($phone_number == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'手机号码格式不对！']);
        }
        
        $res = Db::name('phone_auth')->field('exprie_time')->where('mobile','=',$mobile)->order('id DESC')->find();
        
		if( $res['exprie_time'] > time() ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'请求频繁请稍后重试！']);
		}
        
        $code = mt_rand(111111,999999);

		$data['mobile'] = $mobile;
		$data['auth_code'] = $code;
		$data['start_time'] = time();
		$data['exprie_time'] = time() + 60;

        $res = Db::table('phone_auth')->insert($data);
        if(!$res){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'发送失败，请重试！']);
        }

        $ret = send_zhangjun($mobile, $code);
        if($ret['message'] == 'ok'){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'发送成功！']);
        }
		$this->ajaxReturn(['status' => -2 , 'msg'=>'发送失败，请重试！']);
	}

	public function phoneAuth($mobile, $auth_code)
    {	
        $res = Db::name('phone_auth')->field('exprie_time')->where('mobile','=',$mobile)->where('auth_code',$auth_code)->order('id DESC')->find();
        
        if ($res) {
			if ($res['exprie_time'] >= time()) { // 还在有效期就可以验证
                return true;
            } else {
                return '-1';
            }
        }
        return false;
	}
}
