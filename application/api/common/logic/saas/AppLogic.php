<?php

namespace app\common\logic\saas;

use app\common\model\saas\AppService;
use app\common\model\saas\Miniapp;

class AppLogic
{
    /**
     * 绑定小程序
     */
    public function bindMiniapp($userId, $serviceId, $miniappId)
    {
        if (!$appService = AppService::get(['service_id' => $serviceId, 'user_id' => $userId])) {
            return ['status' => -1, 'msg' => '应用服务不存在'];
        }
        if ($appService->end_time <= time() || $appService->status != AppService::STATUS_NORMAL) {
            return ['status' => -1, 'msg' => '应用服务已过期'];
        }
        if ($appService->miniapp_id) {
            return ['status' => -1, 'msg' => '该应用已绑定过小程序'];
        }
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '指定小程序不存在'];
        }
        if (AppService::get(['miniapp_id' => $miniappId, 'status' => AppService::STATUS_NORMAL])) {
            return ['status' => -1, 'msg' => '小程序已被绑定过'];
        }

        $appService->save(['miniapp_id' => $miniappId]);
        $miniapp->save(['service_id' => $serviceId]);

        return ['status' => 1, 'msg' => '绑定成功'];
    }

    /**
     * 解绑小程序
     */
    public function unbindMiniapp($serviceId)
    {
        if (!$appService = AppService::get(['service_id' => $serviceId])) {
            return ['status' => -1, 'msg' => '应用服务不存在'];
        }
        if (!$appService->miniapp_id) {
            return ['status' => -1, 'msg' => '应用没关联过小程序'];
        }
        if (!$miniapp = Miniapp::get(['miniapp_id' => $appService->miniapp_id, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '指定小程序不存在'];
        }

        $appService->save(['miniapp_id' => 0]);
        $miniapp->save(['service_id' => 0]);

        return ['status' => 1, 'msg' => '解绑成功'];
    }

}