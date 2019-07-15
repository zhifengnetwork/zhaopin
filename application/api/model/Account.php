<?php
namespace app\api\model;

use app\common\model\ChangeGoldLog;
use app\common\model\NotifyService;
use app\common\model\SendSmsCode;
use app\common\model\UserBase;
use app\common\model\UserPlayer;
use think\Db;
use think\Model;
use think\Queue;
use think\Validate;

/**
 * 账户模型
 */
class Account extends Model
{
    /**
     * 验证码接口
     */
    public static function get_code()
    {
        $tel  = input('tel');
        $type = input('type', 0); //0 手机注册登入  1 绑定手机  2重置密码

        if (!isMobile($tel)) {
            return ['', 1, '手机号码输入有误'];
        }

        if ($type != 1 && $type != 2) {
            return ['', 1, 'type错误'];
        }

        if ($type == 1) {
            if (UserBase::where('tel', $tel)->value('tel')) {
                return ['', 1, '该手机号已经被绑定'];
            }
        }

        $expireMinute = (int) config('sms_validate_expire_minute'); // 失效分钟
        $createTime   = SendSmsCode::where(['tel' => $tel, 'type' => $type])->master()->order('id desc')->value('create_time');

        if ($createTime && time() - $createTime <= 60) {
            // 60秒只能发送一次
            return ['', 1, '验证码已下发,请查看'];
        }

        $code = rand(1000, 9999); //四位验证码

        //短信发送
        $result = SendSmsCode::sendSms($tel, array($code, $expireMinute . '分钟'), config('send_sms_model_id'));
        //如果发送成功
        if ($result === true) {
            $data = array(
                'tel'  => $tel,
                'code' => $code,
                'type' => $type,
            );
            $sendSmsCode = new SendSmsCode($data);
            $sendSmsCode->save();

            return ['', 0, '发送验证码成功'];
        } else if ($result === false) {
            return ['', 1, '发送验证码失败'];
        } else {

            // 存在“发送上限”时，返回验证码获取次数超过当天限制
            if (strstr($result, '发送上限')) {
                $result = '返回验证码获取次数超过当天限制';
            }
            return ['', 1, $result];
        }

    }

    /**
     * 短信验证
     */
    public static function checkSms($tel, $code, $type = 0)
    {
        if (!$tel || !isMobile($tel)) {
            return ['', 1, '手机号码有误'];
        }

        if (!$code) {
            return ['', 1, '验证码不能为空'];
        }

        $expireMinute = (int) config('sms_validate_expire_minute'); // 失效分钟
        $info         = SendSmsCode::where(['tel' => $tel, 'status' => 0, 'type' => $type])->master()->order('id desc')->field('code,id,create_time')->find();

        if ($info && (time() - (int) $info->getData('create_time') > 60 * $expireMinute)) {
            return ['', 1, '验证码已过期'];
        }

        if ($code != $info['code']) {
            return ['', 1, '验证码有误'];
        }
        SendSmsCode::where('id', $info['id'])->update(['status' => 1]);
        return ['', 0, ''];
    }

    /**
     * 游客添加密码和绑定电话
     */
    public static function bind_tel()
    {
        $tel      = input('tel/d');
        $code     = input('code');
        $password = input('password');

        $uid = think_decrypt(input('token'));

        if (!$uid) {
            return ['', 1, 'token已失效'];
        }

        if (!$password) {
            return ['', 1, '密码不能为空'];
        }
        $user_info = UserBase::where('uid', $uid)->master()->value('tel');
        if ($user_info) {
            return ['', 1, '不能重复绑定'];
        }

        $user_info = UserBase::where('tel', $tel)->master()->value('uid');
        if ($user_info) {
            return ['', 1, '该手机号已被绑定'];
        }

        $res = self::checkSms($tel, $code, 1); // 短信验证码验证
        if ($res[1]) {
            return $res;
        }

        // 启动事务
        Db::startTrans();

        $result           = self::bindTelSendGold($uid);
        $sendGold         = $result[0];
        $data['tel']      = $tel; //用户密码后缀，用于加密
        $data['salt']     = create_salt(); //用户密码后缀，用于加密
        $data['password'] = minishop_md5($password, $data['salt']);

        if ($sendGold) {
            $update['gold'] = Db::raw('gold+' . $sendGold);
            $msgArr         = [
                $uid => $result[1],
            ];

            $result = UserPlayer::where('uid', $uid)->update($update);

            // 银行变动通知游服
            $res = NotifyService::upUserCard(1102, $msgArr);
            if (!$res) {
                Db::rollback();
                return ['', 1, '绑定失败'];
            }
        }

        // 推送绑定电话人数 和 绑定赠送金币
        $pushData = ['bing_tel_player' => 1];
        if ($sendGold) {
            $pushData['send_gold'] = $sendGold;
        }

        $jobHandlerClassName = 'app\api\job\ReportStatisticsJob@updataStatistics';
        $isPushed            = Queue::push($jobHandlerClassName, $pushData, config('queue_job.reportStatistics'));

        UserBase::where('uid', $uid)->update($data);
        // 提交事务
        Db::commit();
        return ['', 0, '绑定成功'];
    }

    /**
     * 短信重置密码接口
     */
    public static function sms_reset_password()
    {
        $tel      = input('tel');
        $password = input('password');
        $code     = input('code/d', '');

        if (!$password) {
            return ['', 1, '密码不能为空'];
        }

        $uid = UserBase::where('tel', $tel)->master()->value('uid');
        if (!$uid) {
            return ['', 1, '该手机号未被绑定'];
        }

        $res = self::checkSms($tel, $code, 2); // 短信验证码验证
        if ($res[1]) {
            return $res;
        }

        $password_salt = create_salt(); //用户密码后缀，用于加密
        $password_save = minishop_md5($password, $password_salt);

        UserBase::where('uid', $uid)->update(['password' => $password_save, 'salt' => $password_salt]);
        return ['', 0, '您的登陆密码已重置成功，请牢记密码'];
    }

    /**
     * 修改用户信息
     */
    public static function update_user_info()
    {
        $uid = think_decrypt(input('token'));
        if (!$uid) {
            return ['', 1, 'token已失效'];
        }

        $nickname = input('nickname');
        $headimg  = input('headimg/d');

        $data                          = [];
        $headimg && $data['headimg']   = $headimg;
        $nickname && $data['nickname'] = $nickname;

        // 昵称长度判断
        if ($nickname) {
            $length = strlen($nickname);
            if ($length < 2 || $length > 18) {
                return ['', 1, '昵称不合法'];
            }
        }
        if ($headimg) {
            $data['sex'] = ''; //1 男  2 女  0 未设置
        }

        // 验证器
        $msg = [
            //'nickname.length' => '昵称长度不能超过18个字符',
        ];

        $rule = [
            //'nickname|昵称' => 'length:2,18',
            'headimg|头像' => 'between:1,15',
        ];

        $validate = new Validate($rule, $msg);
        $result   = $validate->check($data);
        if (!$result) {
            return ['', 1, $validate->getError()];
        }

        // 判断是否修改昵称
        if ($nickname) {
            $info       = UserBase::where('uid', $uid)->master()->field('up_nickname_num,nickname')->find();
            $allowUpNum = config('UP_NICKNAME_NUM');

            if ($info['up_nickname_num'] >= $allowUpNum) {
                return ['', 1, '昵称可修改次数为' . $allowUpNum . '次，您已达到上限'];
            }

            // 推送绑定电话人数
            $pushData            = ['uid' => $uid, 'ori_nickname' => $info['nickname'], 'nickname' => $nickname];
            $jobHandlerClassName = 'app\api\job\ReportStatisticsJob@nicknameChangeLog';
            $isPushed            = Queue::push($jobHandlerClassName, $pushData, config('queue_job.reportStatistics'));

            $data['up_nickname_num'] = Db::raw('up_nickname_num+1');
        }
        $userBase = new UserBase;

        if ($userBase->save($data, ['uid' => $uid]) !== false) {
            return [$userBase, 0, '修改成功'];
        }
        return ['', 1, '修改失败'];
    }

    /**
     * 首次注册是否赠送金币
     */
    public static function bindTelSendGold($uid)
    {
        $sendGold = config('bind_tel_send_gold');
        $endGold  = 0;
        if ($sendGold > 0) {

            $info          = UserPlayer::where('uid', $uid)->master()->field('gold,bankgold')->find();
            $endGold       = $info['gold'] + $sendGold;
            $changeGoldLog = new ChangeGoldLog([
                'type'        => ChangeGoldLog::BIND_TEL,
                'uid'         => $uid,
                'change_gold' => $sendGold,
                'end_gold'    => $endGold,
            ]);
            $changeGoldLog->save();
        }
        return [$sendGold, $endGold + $info['bankgold']];
    }
}
