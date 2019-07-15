<?php

namespace app\common\logic;

use app\common\model\UserMessage;
use think\Db;

/**
 * 消息类
 * Class CatsLogic
 * @package app\common\logic
 */
class Message
{
    protected $userId;
    protected $user;

    /**
     * Message constructor.
     * @param int $userId
     */
    public function __construct($userId = 0)
    {

        if(empty($userId)){
            $user_info = session('user');
            $this->userId = $user_info['user_id'];
        }else{
            $this->userId = $userId;
        }
    }


    /**
     * 获取用户未查看的消息个数 pc和手机用
     * @return array
     */
    public function getUserMessageCount(){
        $where = array(
            'user_id' => $this->userId,
            'is_see' => 0,
            'deleted' => 0,
            'category' => 0
        );
        // 通知消息未查看数
        $data['message_notice_no_read'] = db("user_message")->where($where)->count();
        // 活动消息未查看数
        $where['category'] = 1;
        $data['message_activity_no_read'] = db("user_message")->where($where)->count();
        // 物流消息未查看数
        $where['category'] = 2;
        $data['message_logistics_no_read'] = db("user_message")->where($where)->count();
        // 私信未查看数
        $where['category'] = 3;
        $data['message_private_no_read'] = db("user_message")->where($where)->count();
        return $data;
    }

    /**
     * 未查看的消息总数 pc和手机用
     * @return int|string
     */
    public function getUserMessageNoReadCount(){
        $this->checkPublicMessage();
        $where = array(
            'user_id' => $this->userId,
            'is_see' => 0,
            'deleted' => 0
        );
        $message_no_read = db("user_message")->where($where)->count();
        return $message_no_read;
    }

    /**
     * 按发送时间排序
     * @param $rec_id | $rec_id = db("user_message")->where($where)->column('rec_id');
     * @param $type | 消息类型
     * @return array
     */
    public function sortMessageListBySendTime($rec_id,$type)
    {
        if (empty($rec_id)) return [];
        switch ($type){
            case 0:
                $name = 'MessageNotice';
                break;
            case 1:
                $name = 'MessageActivity';
                break;
            case 2:
                $name = 'MessageLogistics';
                break;
            default :
                $name = 'MessageLogistics';
                break;
        }
        $userMessage = new UserMessage();
        $list = $userMessage->with($name)->select($rec_id);
        $data = [];
        foreach ($list as $user){
            $data[] = $user->appendRelationAttr($name,['send_time','send_time_text','finished', 'order_text','mobile_url','home_url','start_time'])->toArray();
        }
        $data = array_sort($data,'send_time');
        return $data;
    }

    /**
     * 获取用户通知消息详情 pc和手机用
     * @param $rec_id | UserMessage.rec_id
     * @param $type | 消息类型
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getMessageDetails($rec_id, $type){
        $where = ['rec_id'=>$rec_id];
        $userMessage = new UserMessage();
        $data = $userMessage->where($where)->find();
        if ($data && $data['is_see'] == 0) {
            $this->setMessageForRead($data['rec_id']);
        }
        switch ($type){
            case 0:
                $name = 'MessageNotice';
                $category_name = '通知消息';
                break;
            case 1:
                $name = 'MessageActivity';
                $category_name = '活动消息';
                break;
            case 2:
                $name = 'MessageLogistics';
                $category_name = '物流消息';
                break;
            default :
                $name = 'MessageLogistics';
                $category_name = '物流消息';
                break;
        }
        $data['message_title'] = $data->$name->message_title;
        $data['send_time_text'] = $data->$name->send_time_text;
        $data['message_content'] = htmlspecialchars_decode($data->$name->message_content);
        $data['category_name'] = $category_name;
        return $data;
    }

    /**
     * 查询系统全体消息，如有将其插入用户信息表
     * pc和移动用
     */
    public function checkPublicMessage()
    {
        $user_info = session('user');
        $this->checkUserMessage($user_info['user_id']);
    }


    /**
     * 查询系统全体消息，如有将其插入用户信息表，也用于api接口
     * @param $user_id
     */
    public function checkUserMessage($user_id)
    {
        static $fun = false;
        if ($fun) return; // 防止重复调用
        $fun = true;
        $user_info = Db::name('users')->where('user_id', $user_id)->find();
        if ($user_info) {
            // 通知
            $user_message = Db::name('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => 0))->select();
            $message_where = array(
                'message_type' => 1,
                'send_time' => array('gt', $user_info['reg_time']),
            );
            if (!empty($user_message)) {
                $user_id_array = get_arr_column($user_message, 'message_id');
                $message_where['message_id'] = array('NOT IN', $user_id_array);
            }
            $message_notice_no_read = Db::name('message_notice')->field('message_id')->order('send_time ASC')->where($message_where)->select();
            foreach ($message_notice_no_read as $key) {
                DB::name('user_message')->insert(['user_id' => $user_info['user_id'], 'message_id' => $key['message_id'], 'category' => 0]);
            }

            // 活动
            $user_message = Db::name('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => 1))->select();
            $message_where = array(
                //'send_time' => array('gt', $user_info['reg_time']),
                'end_time' => array('gt', time()) // 只要活动没有结束
            );
            if (!empty($user_message)) {
                $user_id_array = get_arr_column($user_message, 'message_id');
                $message_where['message_id'] = array('NOT IN', $user_id_array);
            }
            $message_activity_no_read = Db::name('message_activity')->field('message_id')->order('send_time ASC')->where($message_where)->select();
            foreach ($message_activity_no_read as $key) {
                DB::name('user_message')->insert(['user_id' => $user_info['user_id'], 'message_id' => $key['message_id'], 'category' => 1]);
            }

            // 优惠券过期消息
            $messageFactory = new MessageFactory();
            $messageLogic = $messageFactory->makeModule(['category' => 0]);
            $messageLogic->couponWillExpire($user_id);
        }

    }


    /**
     * 设置用户消息已读
     * @param $rec_id |数组多条|指定某个|空的则全部
     * @return array
     */
    public function setMessageForRead($rec_id)
    {
        if (!empty($this->userId)) {
            $data['is_see'] = 1;
            $set_where['user_id'] = $this->userId;

            if (strpos($rec_id, ',')) {
                $rec_id = explode(',', $rec_id);
                $set_where['rec_id'] = ['in',$rec_id];
            } elseif (!empty($rec_id)) {
                $set_where['rec_id'] = $rec_id;
            }
            $result = db('user_message')->where($set_where)->update($data);
            if ($result) {
                return ['status'=>1,'msg'=>'操作成功'];
            }
        }
        return ['status'=>-1,'msg'=>'操做失败'];
    }

    /**
     * 删除消息
     * @param $rec_id |数组多条|指定某个|空的则全部
     * @param $type |0通知|1活动|2物流
     * @return array
     */
    public function deletedMessage($rec_id, $type)
    {
        if (!empty($this->userId)) {
            $data['deleted'] = 1;
            $set_where['user_id'] = $this->userId;
            if (strpos($rec_id, ',')) {
                $rec_id = explode(',', $rec_id);
                $set_where['rec_id'] = ['in',$rec_id];
            } elseif (!empty($rec_id)) {
                $set_where['rec_id'] = $rec_id;
            } else{
                if (empty($rec_id)) {
                    // 手机端清空消息
                    $set_where['category'] = $type;
                }
            }
            $result = db('user_message')->where($set_where)->update($data);
            if ($result) {
                return ['status'=>1,'msg'=>'操作成功'];
            }
        }
        return ['status'=>-1,'msg'=>'操做失败'];
    }    

}