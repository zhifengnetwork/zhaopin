<?php


namespace app\common\logic;


use app\common\model\UserMessage;
use app\common\util\TpshopException;
use think\Model;
use think\db;

/**
 * 私信消息逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class MessagePrivateLogic extends MessageBase
{
    /**
     * 添加一条私信消息
     */
    public function addMessage(){
        $this->message['category'] = 3;
        db('message_private')->insert($this->message);
        $message_id = db('message_private')->getLastInsID();
        if($message_id) {
            $this->message['message_id'] = $message_id;
        }
    }

    /**
     * 发私信
     * @param array $send_data |发送内容
     */
    public function sendMessagePrivate($send_data=[])
    {
        $data['send_user_id'] = 0; // 发送者
        $data['message_content'] = ''; // 内容
        $data['users'] = []; // 接收者
        $data = array_merge($data, $send_data);
        $data['category'] = 3; // 私信
        $this->setSendData($data);
        $this->sendMessage();
    }

    /**
     * 删除消息
     * @param $message_id
     * @param $send_user_id
     * @throws \think\Exception
     */
    public function deletedMessage($message_id, $send_user_id)
    {
        db('message_notice')->where(['message_id' => $message_id, 'send_user_id' => $send_user_id])->delete();
        db('user_message')->where(['message_id' => $message_id, 'category' => 3])->delete();
    }

    /**
     * 检测必填参数
     * @return bool
     */
    public function checkParam()
    {
        if (empty($this->message['send_user_id'])
            || empty($this->message['message_content'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * 必填
     * @param $value
     */
    public function setSendUserId($value){
        $this->message['send_user_id'] = $value;
    }
    /**
     * 必填
     * @param $value
     */
    public function setMessageContent($value){
        $this->message['message_content'] = $value;
    }

    /**
     * 必填
     * @param $value
     */
    public function setSendTime($value){
        $this->message['send_time'] = $value;
    }
}