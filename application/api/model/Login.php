<?php
namespace app\api\model;

use app\common\model\NicknameLibrary;
use app\common\model\UserBase;
use app\common\model\UserPlayer;
use think\Db;
use think\Model;
use think\Queue;

/**
 * 登入模型
 */
class Login extends Model
{

    /**
     * 游客登入
     */
    public static function visitor_login()
    {
        // 唯一设备号
        $deviceid = input('deviceid', '');
        if (!$deviceid) {
            return ['', 1, '数据传输错误'];
        }
        $where['deviceid'] = $deviceid;

        $userBase = UserBase::where($where)->master()->field('uid,headimg,sex,nickname,tel,alipay_account,bank_account')->find();
        if (!$userBase) {
            $agentid = input('agentid/d', '');
            $uid     = UserBase::getIncrementUid();
            //加入自增长uid end
            $data = [
                'uid'            => $uid,
                'headimg'        => rand(1, 15), //1 男  2 女  0 未设置
                'sex'            => '',
                'nickname'       => self::setNickname(),
                'deviceid'       => $deviceid,
                'tel'            => '',
                'alipay_account' => '',
                'bank_account'   => '',
                'source'         => self::getDeviceType(),
            ];

            if ($agentid) {
                $agent = UserBase::where('uid', $agentid)->field('is_agent')->find();

                $agent && $data['agent_uid'] = $agentid;
            }

            // 启动事务
            Db::startTrans();

            $userBase = new UserBase();
            $userBase->save($data);

            // 首次注册是否赠送金币
            $sendGold = config('register_send_gold');

            // 玩家号在新增
            $userPlayer = new UserPlayer();
            $userPlayer->save([
                'uid'             => $userBase->uid,
                'gold'            => $sendGold,
                'send_gold'       => $sendGold,
                'last_login_time' => time(),
            ]);

            // 模式三改变用户昵称为用户id
            if (config('registered_nickname_mode') == 3) {
                $userBase->nickname = $userBase->uid;
                $userBase->save();
            }

            // 提交事务
            Db::commit();

            // 推送报表记录
            $pushData = [
                'source'    => $data['source'],
                'agent_uid' => isset($data['agent_uid']) ? $data['agent_uid'] : 0,
                'is_agent'  => isset($agent['is_agent']) ? $agent['is_agent'] : 0,
                'send_gold' => $sendGold,
                'uid'       => $userBase->uid,
            ];

            $jobHandlerClassName = 'app\api\job\ReportStatisticsJob@registerStatistics';
            $isPushed            = Queue::push($jobHandlerClassName, $pushData, config('queue_job.reportStatistics'));
        }

        $result = [
            'token'        => think_encrypt($userBase->uid, '', 7 * 86400),
            'uid'          => (int) $userBase->uid,
            'headimg'      => (int) $userBase->headimg,
            'sex'          => $userBase->sex,
            'tel'          => $userBase->tel,
            'nickname'     => $userBase->nickname,
            'bank_account' => $userBase->bank_account,
            'ali_account'  => $userBase->alipay_account,
            'uno'          => (int) ($userBase->tel ? $userBase->tel : $userBase->uid),
        ];
        return [$result, 0, ''];
    }

    /**
     * 手机号登入
     */
    public static function tel_login()
    {
        $tel      = input('tel/d');
        $password = input('password');

        if (!$tel || !$password) {
            return ['', 1, '手机号、密码不能为空'];
        }

        $info = UserBase::field('uid,status,salt,password,headimg,sex,nickname,tel,alipay_account')
            ->where('tel', $tel)
            ->master()
            ->find();

        if (!$info) {
            return ['', 1, '该账号不存在'];
        }
        if (!$info['status']) {
            return ['', 1, '该账号已经被禁用'];
        }

        if (minishop_md5($password, $info['salt']) != $info['password']) {
            return ['', 1, '密码错误'];
        }

        $result = [
            'token'       => think_encrypt($info['uid'], '', 7 * 86400),
            'uid'         => $info['uid'],
            'uno'         => $info['tel'] ? $info['tel'] : $info['uid'],
            'headimg'     => $info['headimg'],
            'sex'         => $info['sex'],
            'tel'         => $info['tel'],
            'nickname'    => $info['nickname'],
            'ali_account' => $info['alipay_account'],
        ];
        return [$result, 0, '登录成功'];
    }

    /**
     * 判断注册来源 是ios还是安卓
     * @return [type] [description]
     */
    public static function getDeviceType()
    {
        //全部变成小写字母
        $agent = strtolower(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'iphone');

        //分别进行判断
        if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
            $type = 1;
        } else if (strpos($agent, 'android')) {
            $type = 0;
        } else {
            $type = 2; // 其他
        }
        return $type;
    }

    /**
     * 设置昵称
     */
    public static function setNickname()
    {
        // 根据配置做不同的更新
        $mode = config('registered_nickname_mode');
        switch ($mode) {
            case 0:
                return '游客';
                break;
            case 1:
                return '游客' . rand(1000, 9999);
                break;
            case 2:
                return NicknameLibrary::randNickname();
                break;
            default:

                break;
        }
        return '';
    }
}
