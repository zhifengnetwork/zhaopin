<?php


namespace app\common\logic;

use think\Model;
use think\db;

/**
 * 活动消息逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class MessageActivityLogic extends MessageBase
{

    /**
     * 添加一条活动消息
     */
    public function addMessage(){
        $this->message['category'] = 1;
        db('message_activity')->insert($this->message);
        $message_id = db('message_activity')->getLastInsID();
        if($message_id) {
            $this->message['message_id'] = $message_id;
        }
    }

    /**
     * 发一条活动消息
     * @param array $send_data |发送内容
     */
    public function sendMessageActivity($send_data=[])
    {
        $data['message_title'] = ''; // 消息标题,如果空,则用模板名称
        $data['message_content'] = ''; // 如果空，则用模板内容
        $data['img_uri'] = ''; // 图片地址
        $data['end_time'] = 0; // 活动结束时间
        // prom_type:1抢购2团购3优惠促销4预售5虚拟6拼团7搭配9订单促销
        $data['prom_type'] = 1;
        $data['prom_id'] = ''; // 活动id
        $data['mmt_code'] = ''; // 消息模板编号
        $data['users'] = []; // 向用户发消息
        $data['message_val'] = []; // 模板变量名和值
        $data = array_merge($data, $send_data);

        $data['category'] = 1; // 活动类型
        $this->setSendData($data);
        $this->sendMessage();
    }

    /**
     * 后台批准活动时通知消息 抢购和团购
     * @param $id |活动id
     * @param $tab |表名
     */
    public function sendMessageById($id, $tab){
        switch ($tab) {
            case 'flash_sale':
                $this->setPromType(1);
                $flashSale = new \app\common\model\FlashSale();
                $data = $flashSale::get($id);
                $this->setMessageTitle($data['title']);
                $this->setMessageContent($data['description']);
                $this->setEndTime($data['end_time']);
                $this->setImgUri($data->goods->original_img);
                break;
            case 'group_buy':
                $this->setPromType(2);
                $groupBuy = new \app\common\model\GroupBuy();
                $data = $groupBuy::get($id);
                $this->setMessageTitle($data['title']);
                $this->setMessageContent($data['intro']);
                $this->setEndTime($data['end_time']);
                $this->setImgUri($data->goods->original_img);
                break;
            default:
                return;
                break;
        }
        $this->setPromId($id);
        $this->sendMessage();
    }

    /**
     * 发预售消息
     * @param $preSell
     */
    public function sendPreSell($preSell)
    {
        if ($preSell['status'] != 1) return;
        $this->setPromId($preSell['pre_sell_id']);
        $this->setPromType(4);
        $this->setMessageTitle($preSell['title']);
        $this->setMessageContent($preSell['desc']);
        $this->setEndTime($preSell['sell_end_time']);
        $this->setImgUri($preSell->goods->original_img);
        $this->sendMessage();
    }
    /**
     * 发拼团消息
     * @param $teamActivity
     */
    public function sendTeamActivity($teamActivity)
    {
        if ($teamActivity['status'] != 1) return;
        $this->setPromId($teamActivity['team_id']);
        $this->setPromType(6);
        $this->setMessageTitle($teamActivity['act_name']);
        $this->setMessageContent($teamActivity['share_desc']);
        $this->setEndTime(time() + $teamActivity['time_limit']);
        $this->setImgUri($teamActivity->goods->original_img);
        $this->sendMessage();
    }

    /**
     * 发优惠促销
     * @param $promGoods
     */
    public function sendPromGoods($promGoods)
    {
        if ($promGoods['status'] != 1) return;
        $this->setPromId($promGoods['id']);
        $this->setPromType(3);
        $this->setMessageTitle($promGoods['title']);
        $this->setMessageContent($promGoods['description']);
        $this->setEndTime($promGoods['end_time']);
        $this->setImgUri($promGoods['prom_img']);
        $this->sendMessage();
    }

    /**
     * 发订单促销
     * @param $promOrder
     */
    public function sendPromOrder($promOrder)
    {
        if ($promOrder['status'] != 1) return;
        $this->setPromId($promOrder['id']);
        $this->setPromType(9);
        $this->setMessageTitle($promOrder['title']);

        $money = $promOrder['money'];
        $expression = $promOrder['expression'];
        $start_time = date("Y-m-d H:i:s", $promOrder['start_time']);
        $end_time = date("Y-m-d H:i:s", $promOrder['end_time']);
        $text = "为答谢广大顾客，活动期间 {$start_time} ~ {$end_time}，凡在本商场消费的顾客，均可获得优惠：";
        switch ($promOrder['type']) {
            case 0:
                // 直接打折
                $expression /= 10;
                $text .= "每笔订单满{$money}元, 打{$expression}折。";
                break;
            case 1:
                //减价优惠
                $text .= "每笔订单满{$money}元, 立减{$expression}元。";
                break;
            case 2:
                // 满额送积分
                $text .= "每笔订单满{$money}元, 送{$expression}积分。";
                break;
            case 3:
                $expression_name = Db::name('coupon')->where('id', $expression)->value('name');
                //买就赠代金券
                $text .= "每笔订单满{$money}元, 送{$expression_name}优惠券。";
                break;
        }
        $this->setMessageContent($text . $promOrder['description']);
        $this->setEndTime($promOrder['end_time']);
        $this->setImgUri($promOrder['prom_img']);
        $this->sendMessage();
    }

    /**
     * 获取编号
     * @param $type
     * @return string
     */
    public function getCodeByType($type)
    {
        //  '1抢购2团购3优惠促销4预售5虚拟6拼团7搭配购8无9订单促销
        switch ($type) {
            case 1:
                $mmt_code = 'flash_sale_activity';
                break;
            case 2:
                $mmt_code = 'group_buy_activity';
                break;
            case 3:
                $mmt_code = 'prom_goods_activity';
                break;
            case 4:
                $mmt_code = '';
                break;
            case 5:
                $mmt_code = '';
                break;
            case 6:
                $mmt_code = 'team_activity';
                break;
            case 7:
                $mmt_code = 'combination_activity';
                break;
            case 9:
                $mmt_code = 'prom_order_activity';
                break;
            default:
                $mmt_code = '';
                break;
        }
        return $mmt_code;
    }

    /**
     * 删除消息
     * @param $prom_id |活动id
     * @param $type |活动类型
     * @throws \think\Exception
     */
    public function deletedMessage($prom_id, $type)
    {
        $message_id = db('message_activity')->where(['prom_id' => $prom_id, 'prom_type' => $type])->value('message_id');
        if ($message_id) {
            db('message_activity')->where(['prom_id' => $prom_id, 'prom_type' => $type])->delete();
            db('user_message')->where(['message_id' => $message_id, 'category' => 1])->delete();
        }

    }

    /**
     * 检测必填参数
     * @return bool
     */
    public function checkParam()
    {
        if (empty($this->message['message_title']) || empty($this->message['send_time'])
            || empty($this->message['end_time']) || empty($this->message['img_uri'])
            || empty($this->message['prom_type']) || empty($this->message['prom_id'])
            || empty($this->message['mmt_code'])
        ) {
            return false;
        }
        return true;
    }



    /**
     * 必填
     * @param $value
     */
    public function setImgUri($value){
        $this->message['img_uri'] = $value;
    }
    /**
     * 必填
     * @param $value
     */
    public function setEndTime($value){
        $this->message['end_time'] = $value;
    }
    /**
     * 必填
     * prom_type:1抢购2团购3优惠促销4预售5无6拼团7搭配9订单促销
     * @param $value
     */
    public function setPromType($value){
        $this->message['prom_type'] = $value;
        $this->message['mmt_code'] = $this->getCodeByType($value);
    }
    /**
     * 必填
     * @param $value
     */
    public function setPromId($value){
        $this->message['prom_id'] = $value;
    }
}