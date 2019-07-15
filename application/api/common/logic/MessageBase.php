<?php

namespace app\common\logic;

use think\Model;
use think\db;

/**
 * 消息基础
 * Class CatsLogic
 * @package admin\Logic
 */
class MessageBase extends Model
{
    protected $message;//消息模型

    public function __construct($message=[])
    {
        parent::__construct();
        $this->message = $message;
    }

    /**
     * 重新设置发送的消息内容
     * @param array $send_data
     */
    public function setSendData($send_data=[])
    {
        if (!empty($send_data)) {
            $this->message = array_merge($this->message, $send_data);
            if(!empty($send_data['users'])){
                $this->message['users'] = $send_data['users'];
            }
        }
    }

    /**
     * 发消息,
     */
    public function sendMessage()
    {
        $data = $this->message;
        $arr = db('user_msg_tpl')->where('mmt_code', $data['mmt_code'])->find();
        if (isset($data['mmt_message_switch']))
            $arr['mmt_message_switch'] = $data['mmt_message_switch'];
        if (isset($data['mmt_short_switch']))
            $arr['mmt_short_switch'] = $data['mmt_short_switch'];
        if (isset($data['mmt_mail_switch']))
            $arr['mmt_mail_switch'] = $data['mmt_mail_switch'];

        // 站内信
        if ($arr['mmt_message_switch'] == 1) {
            if ($data['mmt_code'] == $arr['mmt_code'] && empty($data['message_content'])) {
                $data['message_content'] = $this->getContent($data['message_val'], $arr['mmt_message_content']);
            }
            if ($data['mmt_code'] == $arr['mmt_code'] && empty($data['message_title'])) {
                $data['message_title'] = $this->getContent($data['message_val'], $arr['mmt_name']);
            }
            if (!isset($data['send_time'])) {
                $data['send_time'] = time();
            }
            $this->message = $data;
            if (isset($this->message['message_id'])){
                unset($this->message['message_id']);
            }
            $this->addMessage();
            $this->sendUserMessage();
        }
        // 短信
        if ($arr['mmt_short_switch'] == 1) {
            $this->sendShort($data, $arr);
        }
        // 邮件
        if ($arr['mmt_mail_switch'] == 1) {
            $this->sendMail($data, $arr);
        }
    }
    /**
     * 添加一条消息,不同类型消息表不同
     */
    public function addMessage(){
        db('message_notice')->insert($this->message);
        $message_id = db('message_notice')->getLastInsID();
        if($message_id) {
            $this->message['message_id'] = $message_id;
        }
    }

    /**
     * 发短信
     * @param $data |发送内容
     * @param $arr |模板内容
     * @return bool
     */
    public function sendShort($data, $arr)
    {
        if (empty($data['sender'])) {
            return false;
        }
        $msg = $this->getContent($data['message_val'], $arr['mmt_short_content']);
        $params = array_merge($data, $arr);
        //提取发送短信内容
        $params['msg'] = $msg;

        $code = !empty($params['code']) ? $params['code'] : false;
        $phone = !empty($params['phone']) ? $params['phone'] : false;
        $order_id = !empty($params['order_id']) ? $params['order_id'] : false;
        $user_name = !empty($params['user_name']) ? $params['user_name'] : false;
        $consignee = !empty($params['consignee']) ? $params['consignee'] : false;

        $smsParams = [ // 短信模板中字段的值
            1 => ['code'=>$code],                                    //1. 用户注册 (验证码类型短信只能有一个变量)
            2 => ['code'=>$code],                                    //2. 用户找回密码 (验证码类型短信只能有一个变量)
            3 => ['consignee'=>$consignee ,'phone'=>$phone],         //3. 客户下单
            4 => ['orderId'=>$order_id],                             //4. 客户支付
            5 => ['userName'=>$user_name, 'consignee'=>$consignee],  //5. 商家发货
            6 => ['code'=>$code],
            'arrival_notice' => ['consignee'=>$consignee ,'phone'=>$phone],
            '4163' => ['consignee'=>$consignee ,'phone'=>$phone],
        ];

        if (isset($params['scene'])) {
            $params['mmt_code'] = $params['scene'];
        }
        $scene = $params['mmt_code']; // 为兼容以前，把sms_log表的scene字段改为varchar 50长度
        $params['smsParams'] = $smsParams[$scene];
        $smsLogic = new SmsLogic();
        if (is_array($data['sender'])) {
            foreach ($data['sender'] as $sender) {
                $params['sender'] = $sender;
                $smsLogic->sendMsg($params);
            }
        }else{
            $smsLogic->sendMsg($params);
        }
        return true;
    }

    /**
     * 发邮件
     * @param $data |发送内容
     * @param $arr |模板内容
     * @return bool
     */
    public function sendMail($data, $arr)
    {
        if (empty($data['email'])) {
            return false;
        }
        $subject = $this->getContent($data['message_val'], $arr['mmt_mail_subject']);
        $content = $this->getContent($data['message_val'], $arr['mmt_mail_content']);
        return send_email($data['email'], $subject, htmlspecialchars_decode($content));
    }

    /**
     * 把消息发送给用户，站内信通用
     */
    protected function sendUserMessage()
    {
        $data = $this->message;
        if (!empty($data['users']) && !empty($data['message_id'])) {
            foreach ($data['users'] as $key) {
                db('user_message')->insert(array('user_id' => $key, 'message_id' => $data['message_id'], 'category' => $data['category']));
            }
        }
    }

    /**
     * 模板内容处理
     * @param $data |发送内容变量值
     * @param $str |模板内容支持变量 {$name} or ${name}
     * @return mixed
     */
    protected function getContent($data, $str)
    {
        foreach ($data as $k => $v) {
            $str = str_replace('{$' . $k . '}', $v, $str);
            $str = str_replace('${' . $k . '}', $v, $str);
        }
        return $str;
    }
    /**
     * 必填
     * @param $value
     */
    public function setMessageTitle($value){
        $this->message['message_title'] = $value;
    }
    /**
     * 可空
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
    /**
     * 向用户发消息,可以空
     * @param $array | [1,2,3]
     */
    public function setUsers($array){
        $this->message['users'] = $array;
    }
    /**
     * 模板变量名和值,可以空
     * @param $array |['name'=>'value']
     */
    public function setMessageVal($array){
        $this->message['message_val'] = $array;
    }
}