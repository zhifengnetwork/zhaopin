<?php
namespace app\common\exception;

use Exception;
use think\exception\Handle;
use think\exception\HttpException;

/**
 * 获取接口异常
 */
class Http extends Handle
{

    public function render(Exception $e)
    {
        return response(json_encode(['code' => -1, 'msg' => 'server_error, Please contact program ape']), 200);

        // 参数验证错误
        if ($e instanceof ValidateException) {
            return json($e->getError(), 422);
        }

        // 请求异常
        if ($e instanceof HttpException && request()->isAjax()) {
            return response($e->getMessage(), $e->getStatusCode());
        }

        //TODO::开发者对异常的操作
        //可以在此交由系统处理
        //return parent::render($e);
    }

}
