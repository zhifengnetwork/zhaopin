<?php

namespace app\common\logic\saas;

use app\common\model\saas\Users;
use app\common\model\saas\Wx3rd;
use app\common\model\saas\Miniapp;
use app\common\model\saas\UserMiniapp;
use app\common\logic\saas\wechat\Wx3rdPlatform;

/**
 * 微信第三方平台逻辑处理
 */
class Wx3rdLogic
{
    public $wx3rd = null;
    
    public function __construct()
    {
        $this->wx3rd = Wx3rdPlatform::getInstance();
    }

    /**
     * 处理：授权事件接收，配置在：第三方平台的授权事件接收URL
     */
    public function handleAuthMessage()
    {
        $this->wx3rd->handleAuthEvent(Wx3rdPlatform::AUTH_EVENT_VERIFY_TICKET, function ($msg) {
            $this->handleVerifyTicketEvent($msg);
        });

        $this->wx3rd->handleAuthEvent(Wx3rdPlatform::AUTH_EVENT_UNAUTHORIZED, function ($msg) {
            $this->handleUnauthorizedEvent($msg);
        });

        $this->wx3rd->handleAuthEvent(Wx3rdPlatform::AUTH_EVENT_AUTHORIZED, function ($msg) {
            $this->handleAuthSuccessEvent($msg);
        });

        $this->wx3rd->handleAuthEvent(Wx3rdPlatform::AUTH_EVENT_UPDATE_AUTHORIZED, function ($msg) {
            $this->handleAuthSuccessEvent($msg);
        });
    }

    private function handleVerifyTicketEvent($msg)
    {
        Wx3rd::update([
            'verify_ticket' => $msg['ComponentVerifyTicket'],
            'verify_ticket_time' => time()
        ], ['appid' => $msg['AppId']]);
    }

    private function handleUnauthorizedEvent($msg)
    {
        Miniapp::update([
            'is_auth' => 0,
            'access_token_expires' => 0,
            'tester' => ''
        ], ['appid' => $msg['AuthorizerAppid']]);
    }

    private function handleAuthSuccessEvent($msg)
    {
        $data = [
            'is_auth'           => 1,
            'appid'             => $msg['AuthorizerAppid'],
            //'auth_code'         => $msg['AuthorizationCode'],
            //'auth_code_expires' => $msg['AuthorizationCodeExpiredTime'],
            //'pre_auth_code'     => $msg['PreAuthCode'],
        ];
        $return = $this->getAuthUserInfo($msg['AuthorizerAppid']);
        if ($return['status'] == 1) {
            $data = array_merge($data, $return['result']);
        }
        $miniapp = Miniapp::update($data, ['appid' => $msg['AuthorizerAppid']]);

        $miniapp && $miniapp = Miniapp::get(['miniapp_id' => $miniapp->miniapp_id]);
        if ($miniapp && $user = Users::get(['user_id' => $miniapp->user_id])) {
            if (!$user->head_img && $miniapp->head_img) {
                $user->save(['head_img' => $miniapp->head_img]);
            }
        }
    }

    /**
     * 处理普通的推送消息，如公众号消息，小程序审核消息等
     * @param $appid
     */
    public function handleCommonMessage($appid)
    {
        $this->wx3rd->handleCommonEvent(Wx3rdPlatform::COMMON_EVENT_WEAPP_AUDIT_SUCCESS, function ($msg) {
            $this->handleMiniappAuditSuccessEvent($msg);
        });

        $this->wx3rd->handleCommonEvent(Wx3rdPlatform::COMMON_EVENT_WEAPP_AUDIT_FAIL, function ($msg) {
            $this->handleMiniappAuditFailEvent($msg);
        });
    }

    private function handleMiniappAuditSuccessEvent($msg)
    {
        $miniapp = Miniapp::get(['origin_id' => $msg['ToUserName']]);
        if ($miniapp) {
            $miniapp->userMiniapps()
                ->where(['status' => UserMiniapp::STATUS_AUDITING])
                ->update(['status' => UserMiniapp::STATUS_AUDIT_DONG]);
        }
    }

    private function handleMiniappAuditFailEvent($msg)
    {
        $miniapp = Miniapp::get(['origin_id' => $msg['ToUserName']]);
        if ($miniapp) {
            $miniapp->userMiniapps()
                ->where(['status' => UserMiniapp::STATUS_AUDITING])
                ->update(['status' => UserMiniapp::STATUS_AUDIT_FAIL, 'audit_fail_reason' => $msg['Reason']]);
        }
    }
    
    /**
     * 用户授权
     * @param string $userId
     * @param string $authCode 用户授权码，用户扫码授权后返回的授权码
     * @return array
     */
    public function authByUser($userId, $authCode)
    {
        $user = Users::get($userId);
        if (!$user) {
            return ['status' => -1, 'msg' => '账户不存在'.$userId];
        }

        $info = $this->wx3rd->getAuthInfo($authCode);
        if ($info === false) {
            return ['status' => -1, 'msg' => $this->wx3rd->getError()];
        }

        $miniapp = Miniapp::where(['appid' => $info['authorizer_appid']])->find();
        if ($miniapp && $miniapp->is_auth && $miniapp->user_id != $userId) {
            return ['status' => -1, 'msg' => '小程序已被其他账号绑定！'];
        }
        
        $data = [
            'appid'         => $info['authorizer_appid'],
            'access_token'  => $info['authorizer_access_token'],
            'access_token_expires' => time() + $info['expires_in'] - 200, //提前200s失效
            'refresh_token' => $info['authorizer_refresh_token'],
            'is_auth'       => 1,
            'auth_time'     => time(),
            'user_id'       => $userId
        ];
        $authUser = $this->getAuthUserInfo($info['authorizer_appid']);
        if ($authUser['status'] == 1) {
            $data = array_merge($data, $authUser['result']);
        }

        if ($miniapp) {
            $miniapp->save($data);
        } else {
            $miniapp = Miniapp::create($data);
        }

        if (!$user->head_img && $miniapp->head_img) {
            $user->save(['head_img' => $miniapp->head_img]);
        }

        session('saas_user', $user->toArray());

        return ['status' => 1, 'msg' => '授权成功'];
    }

    /**
     * 转换用户数据
     * @param array $data
     * @return array
     */
    public function convertUserData($data)
    {
        return [
            'origin_id'      => $data['authorizer_info']['user_name'],
            'nickname'       => $data['authorizer_info']['nick_name'] ?: '我的小程序'.rand(0, 999),
            'head_img'       => $data['authorizer_info']['head_img'],
            'principal_name' => $data['authorizer_info']['principal_name'],
            'signature'      => $data['authorizer_info']['signature'],
            'domains' => $data['authorizer_info']['MiniProgramInfo']['network'],
        ];
    }

    /**
     * 更新用户授权信息
     * @param $appid
     * @return array
     */
    public function getAuthUserInfo($appid)
    {
        $authUser = $this->wx3rd->getAuthorizerInfo($appid);
        if ($authUser === false) {
            return ['status' => -1, 'msg' => $this->wx3rd->getError()];
        }
        return ['status' => 1, 'msg' => '获取成功', 'result' => $this->convertUserData($authUser)];
    }

    /**
     * 接收消息成功后返回
     */
    public function echoMsgSuccess()
    {
        if (ob_get_level() == 0) {
            ob_start();
        }

        ob_implicit_flush(true);
        ob_clean();

        header("Content-type: text/plain");
        echo('success');

        ob_flush();
        flush();
        ob_end_flush();

        exit();
    }
}
