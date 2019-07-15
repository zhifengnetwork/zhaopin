<?php

namespace app\common\logic;


/**
 * 消息工厂类
 * Class CatsLogic
 * @package admin\Logic
 */
class MessageFactory
{
    /**
     * @param $message|商品实例
     * @return MessageNoticeLogic|MessageActivityLogic|MessageLogisticsLogic|MessagePrivateLogic
     */
    public function makeModule($message)
    {
        switch ($message['category']) {
            case 0:
                return new MessageNoticeLogic($message);
            case 1:
                return new MessageActivityLogic($message);
            case 2:
                return new MessageLogisticsLogic($message);
            case 3:
                return new MessagePrivateLogic($message);
        }
    }

    /**
     * 检测是否符合消息工厂类的使用
     * @param $category |消息类型
     * @return bool
     */
    public function checkMessageCategory($category)
    {
        if (in_array($category, array_values([0, 1, 2, 3]))) {
            return true;
        } else {
            return false;
        }
    }

}
