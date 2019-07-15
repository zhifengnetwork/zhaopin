<?php

namespace app\common\logic;

use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 订单类
 * Class CatsLogic
 * @package Home\Logic
 */
class Order
{

    private $order;
    private $user_id = 0;

    public function __construct()
    {
        $this->order = new \app\common\model\Order();
    }

    public function setOrderById($order_id)
    {
        $this->order = \app\common\model\Order::get($order_id);
    }

    public function setOrderModel($order)
    {
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * 订单收货确认
     */
    public function deliveryConfirm()
    {
        if(empty($this->order)){
            throw new TpshopException('订单确认收货', 0, ['status' => 0, 'msg' => '订单不存在']);
        }
        if($this->order['order_status'] == 0){
            throw new TpshopException("自提订单核销",0,['status' => 0, 'msg' => '系统没有确认该订单']);
        }
        if($this->order['order_status'] == 2){
            throw new TpshopException("自提订单核销",0,['status' => 0, 'msg' => '该订单已收货']);
        }
        if($this->order['order_status'] == 3){
            throw new TpshopException("自提订单核销",0,['status' => 0, 'msg' => '该订单已取消']);
        }
        if($this->order['order_status'] == 4){
            throw new TpshopException("自提订单核销",0,['status' => 0, 'msg' => '该订单已完成']);
        }
        if($this->order['order_status'] == 5){
            throw new TpshopException("自提订单核销",0,['status' => 0, 'msg' => '该订单已作废']);
        }
        if(empty($this->order['pay_time']) || $this->order['pay_status'] != 1){
            throw new TpshopException('订单确认收货', 0, ['status' => 0, 'msg' => '商家未确定付款，该订单暂不能确定收货']);
        }
        $data['order_status'] = 2; // 已收货
        $data['shipping_status'] = 1; // 已发货
        $data['pay_status'] = 1; // 已付款
        $data['confirm_time'] = time(); // 收货确认时间
        if($this->order['pay_code'] == 'cod'){
            $data['pay_time'] = time();
        }
        $this->order->save($data);
        order_give($this->order);// 调用送礼物方法, 给下单这个人赠送相应的礼物

        // 自提点发货，改为待评价提醒 如果发了两次消息，那是因为order_give也发了次消息
        /*$order_arr = Db::name('order_goods')->where("order_id", $this->order['order_id'])->find();
        $goods_original_img = Db::name('goods')->where("goods_id", $order_arr['goods_id'])->value('original_img');
        $send_data = [
            'message_title' => '商品待评价',
            'message_content' => $order_arr['goods_name'],
            'img_uri' => $goods_original_img,
            'order_sn' => $this->order['order_sn'],
            'order_id' => $this->order['order_id'],
            'mmt_code' => 'evaluate_logistics',
            'type' => 4,
            'users' => [$this->order['user_id']],
            'category' => 2,
            'message_val' => []
        ];
        $messageFactory = new MessageFactory();
        $messageLogic = $messageFactory->makeModule($send_data);
        $messageLogic->sendMessage();*/

        //分销设置
        Db::name('rebate_log')->where("order_id", $this->order['order_id'])->save(['status'=>2,'confirm'=>time()]);
    }

    /**
     * 用户删除订单
     * @return array
     * @throws TpshopException
     */
    public function userDelOrder()
    {
        $validate = validate('order');
        $order_id = $this->order['order_id'];
        if (!$validate->scene('del')->check(['order_id' => $order_id])) {
            throw new TpshopException('用户删除订单', 0, ['status' => 0, 'msg' => $validate->getError()]);
        }
        if (empty($this->user_id)) {
            throw new TpshopException('用户删除订单', 0, ['status' => 0, 'msg' => '非法操作']);
        }
        $row = Db::name('order')->where(['user_id' => $this->user_id, 'order_id' => $order_id])->update(['deleted' => 1]);
        if (!$row) {
            Db::name('order_goods')->where(['order_id' => $order_id])->update(['deleted' => 1]);
            throw new TpshopException('用户删除订单', 0, ['status' => 0, 'msg' => '删除失败']);
        }
    }

    /**
     * 管理员删除订单
     * @return array
     * @throws \think\Exception
     */
    public function adminDelOrder()
    {
        Db::name('order_goods')->where('order_id',$this->order['order_id'])->delete();
        $this->order->delete();
    }

    /**
     * 订单操作记录
     * @param $action_note|备注
     * @param $status_desc|状态描述
     * @param $action_user
     * @return mixed
     */
    public function orderActionLog($action_note, $status_desc, $action_user = 0)
    {
        $data = [
            'order_id' => $this->order['order_id'],
            'action_user' => $action_user,
            'action_note' => $action_note,
            'order_status' => $this->order['order_status'],
            'pay_status' => $this->order['pay_status'],
            'log_time' => time(),
            'status_desc' => $status_desc,
            'shipping_status' => $this->order['shipping_status'],
        ];
        return Db::name('order_action')->add($data);//订单操作记录
    }
}