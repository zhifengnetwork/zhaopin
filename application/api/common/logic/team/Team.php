<?php


namespace app\common\logic\team;
use app\common\logic\OrderLogic;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use app\common\model\TeamGoodsItem;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Cache;
use think\Db;

/**
 * 拼团活动逻辑类
 */
class Team
{
    private $teamGoodsItem;
    private $userId;
    private $user;
    private $teamActivity;
    private $teamId;
    private $foundId;
    private $teamFound;
    private $buyNum;
    private $order;

    private $teamGoods;//虚构的商品模型
    private $orderCanUseCouponNum;//订单能使用的优惠券数量

    public function setTeamGoodsItemById($goods_id, $item_id)
    {
        $this->teamGoodsItem = TeamGoodsItem::get(['goods_id' => $goods_id, 'item_id' => $item_id, 'deleted' => 0]);
        if ($this->teamGoodsItem) {
            $this->teamId = $this->teamGoodsItem['team_id'];
            $this->teamActivity = $this->teamGoodsItem['team_activity'];
        }
    }

    public function setTeamActivityById($team_id)
    {
        if($team_id > 0){
            $this->teamId = $team_id;
            $this->teamActivity = TeamActivity::get($team_id);
        }
    }

    public function getTeamActivity()
    {
        return $this->teamActivity;
    }

    public function getFoundId()
    {
        return $this->foundId;
    }

    public function setTeamFoundById($found_id)
    {
        if($found_id){
            $this->foundId = $found_id;
            $this->teamFound = TeamFound::get($this->foundId);
        }
    }

    public function setUserById($user_id)
    {
        if($user_id > 0){
            $this->userId = $user_id;
            $this->user = Users::get($user_id);
        }
    }

    public function setBuyNum($buy_num)
    {
        $this->buyNum = $buy_num;
    }

    /**
     * 设置order模型
     * @param $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * 拼团支付后操作
     * @throws \think\Exception
     */
    public function doOrderPayAfter(){
        $teamFound = TeamFound::get(['order_id' => $this->order['order_id']]);
        //团长的单
        if ($teamFound) {
            $teamFound->found_time = time();
            $teamFound->found_end_time = time() + intval($this->teamActivity['time_limit']);
            $teamFound->status = 1;
            $teamFound->save();
            $team_found_queue =  Cache::get('team_found_queue');
            $team_found_queue[$teamFound->found_id] = $teamFound->need - $teamFound->join;
            Cache::set('team_found_queue',$team_found_queue);
        }else{
            //团员的单
            $teamFollow = TeamFollow::get(['order_id' => $this->order['order_id']]);
            if($teamFollow){
                $teamFollow->status = 1;
                $teamFollow->save();
                //更新团长的单
                $teamFollow->team_found->join = $teamFollow['team_found']['join'] + 1;//参团人数+1
                //如果参团人数满足成团条件
                if($teamFollow->team_found->join >= $teamFollow->team_found->need){
                    $teamFollow->team_found->status = 2;//团长成团成功
                    //更新团员成团成功
                    Db::name('team_follow')->where(['found_id'=>$teamFollow->team_found->found_id,'status'=>1])->update(['status'=>2]);
                }
                $teamFollow->team_found->save();
            }

        }
    }

    /**
     * 过滤拼团订单能使用的优惠券列表
     * @param $userCouponList
     * @return array
     */
    public function getCouponOrderList($userCouponList)
    {
        $this->orderCanUseCouponNum = 0;
        $userCouponArray = collection($userCouponList)->toArray();
        $couponNewList = [];
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if ($this->order['goods_price'] >= $userCouponArray[$couponKey]['coupon']['condition']) {
                $userCouponArray[$couponKey]['coupon']['able'] = 1;
                $this->orderCanUseCouponNum ++;
            } else {
                $userCouponArray[$couponKey]['coupon']['able'] = 0;
            }
            $couponNewList[] = $userCouponArray[$couponKey];
        }
        return $couponNewList;
    }

    /**
     * 订单能使用的优惠券数量
     * @return mixed
     */
    public function getOrderCanUseCouponNum()
    {
        return $this->orderCanUseCouponNum;
    }


    /**
     * 过滤拼团订单能使用的优惠券列表|api专用
     * @param $userCouponList
     * @return array
     */
    public function getCouponOrderAbleList($userCouponList)
    {
        $userCouponArray = collection($userCouponList)->toArray();
        $couponNewList = [];
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if ($this->order['goods_price'] >= $userCouponArray[$couponKey]['coupon']['condition']) {
                $coupon = $userCouponArray[$couponKey]['coupon'];
                $coupon['id'] = $userCouponArray[$couponKey]['id'];
                $coupon['cid'] = $userCouponArray[$couponKey]['cid'];
                unset($coupon['goods_coupon']);
                $couponNewList[] = $coupon;
            }
        }
        return $couponNewList;
    }

    public function buy()
    {
        if (empty($this->teamActivity) || $this->teamActivity['status'] != 1) {
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该商品拼团活动不存在或者已下架', 'result' => '']);
        }
        if ($this->teamActivity['is_lottery'] == 1) {
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该商品拼团活动已开奖', 'result' => '']);
        }
        $this->teamGoods = $goods = $this->teamActivity->goods;
        if (empty($goods) || $goods['is_on_sale'] != 1 || $goods['prom_type'] != 6) {
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该商品拼团活动不存在或者已下架', 'result' => '']);
        }
        if ($this->teamGoodsItem['item_id'] > 0) {
            $spec_goods_price = $this->teamGoodsItem->specGoodsPrice;
            $this->teamGoods['spec_key'] = $spec_goods_price['key'];
            $this->teamGoods['spec_key_name'] = $spec_goods_price['key_name'];
            $this->teamGoods['sku'] = $spec_goods_price['sku'];
            $this->teamGoods['prom_id'] = $spec_goods_price['prom_id'];
            $this->teamGoods['prom_type'] = $spec_goods_price['prom_type'];
            $this->teamGoods['shop_price'] = $spec_goods_price['price'];
            if(empty($spec_goods_price) || $spec_goods_price['prom_type'] != 6){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该商品拼团活动不存在或者已下架', 'result' => '']);
            }
            if($this->buyNum > $spec_goods_price['store_count']){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '商品库存仅剩余'.$spec_goods_price['store_count'], 'result' => '']);
            }
        }
        if($this->buyNum > $goods['store_count']){
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '商品库存仅剩余'.$goods['store_count'], 'result' => '']);
        }
        if ($this->buyNum <= 0) {
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '至少购买一份', 'result' => '']);
        }
        if ($this->teamActivity['buy_limit'] != 0 && $this->buyNum > $this->teamActivity['buy_limit']) {
            throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '购买数已超过该活动单次购买限制数(' . $this->teamActivity['buy_limit'] . ')', 'result' => '']);
        }
        if($this->foundId){
            if(empty($this->teamFound) || $this->teamFound['status'] != 1){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该拼单数据不存在或已失效', 'result' => '']);
            }
            if($this->teamFound['user_id'] == $this->userId){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '不能自己开团自己拼', 'result' => '']);
            }
            if($this->teamActivity['team_type'] == 2){
                //抽奖团，只能拼一次团
                $teamYouSelfFollow = Db::name('team_follow')->where(['follow_user_id' => $this->userId, 'team_id' => $this->teamId, 'status' => ['in', '1,2']])->find();
                if($teamYouSelfFollow){
                    throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '你已经参与过该拼团活动。', 'result' => '']);
                }
            }
            if($this->teamFound['team_id'] != $this->teamActivity['team_id']){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该拼单数据不存在或已失效', 'result' => '']);
            }
            //拼团订单里有可能存在未支付订单。
            $this->checkFollowNoPayByFound($this->teamFound);
            if($this->teamFound['join'] >= $this->teamFound['need']){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该单已成功结束', 'result' => '']);
            }
            if(time() - $this->teamFound['found_time'] > $this->teamActivity['time_limit']){
                throw new TpshopException('拼团购买商品',0,['status' => 0, 'msg' => '该拼单已过期', 'result' => '']);
            }
        }
        $this->teamGoods['goods_price'] = $this->teamGoodsItem['team_price'];
        $this->teamGoods['goods_num'] = $this->buyNum;
        $this->teamGoods['member_goods_price'] = $this->teamGoodsItem['team_price'];
    }

    public function getTeamBuyGoods()
    {
        return $this->teamGoods;
    }

    public function log(Order $order)
    {
        if($this->teamFound){
            /**团员拼团s**/
            $team_follow_data = [
                'follow_user_id' => $this->userId,
                'follow_user_nickname' => $this->user['nickname'],
                'follow_user_head_pic' => $this->user['head_pic'],
                'follow_time' => time(),
                'order_id' => $order['order_id'],
                'found_id' => $this->teamFound['found_id'],
                'found_user_id' => $this->teamFound['user_id'],
                'team_id' => $this->teamActivity['team_id'],
            ];
            Db::name('team_follow')->insert($team_follow_data);
            /***团员拼团e***/
        }else{
            /***团长开团s***/
            $team_found_data = [
                'found_time'=>time(),
                'found_end_time' => time() + intval($this->teamActivity['time_limit']),
                'user_id' => $this->userId,
                'team_id' => $this->teamActivity['team_id'],
                'nickname' => $this->user['nickname'],
                'head_pic' =>  $this->user['head_pic'],
                'order_id' => $order['order_id'],
                'need' => $this->teamActivity['needer'],
                'price'=> $this->teamGoodsItem['team_price'],
                'goods_price' => $this->teamGoods['shop_price'],
            ];
            Db::name('team_found')->insert($team_found_data);
            /***团长开团e***/
        }
    }
    public function getTeamFound()
    {
        $team = $this->teamFound->teamActivity;
        if(time() - $this->teamFound['found_time'] > $team['time_limit']){
            //时间到了
            if($this->teamFound['join'] < $this->teamFound['need']){
                //人数没齐
                $this->teamFound->status = 3;//成团失败
                $this->teamFound->save();
                //更新团员成团失败
                Db::name('team_follow')->where(['found_id'=>$this->teamFound['found_id'],'status'=>1])->update(['status'=>3]);
            }
        }
        if ($this->teamFound['status'] == 1) {
            //拼团订单里有可能存在未支付订单。
            $this->checkFollowNoPayByFound($this->teamFound);
        }
        return $this->teamFound;
    }
    /**
     * 拼团订单里有可能存在未支付订单。
     * @param $found|团长对象
     */
    private function checkFollowNoPayByFound($found){
        $noPayFollowOrderIds = Db::name('team_follow')->where(['status' => 0, 'found_id' => $found['found_id']])->column('order_id');
        if ($noPayFollowOrderIds) {
            $noPayOrderList = Db::name('order')->where(['order_id' => ['IN',$noPayFollowOrderIds], 'pay_status' => 0, 'order_status' => 0])->order('order_id asc')->select();
            //找到最先未支付订单。然后查看是否超时未支付。如果超时，就将该订单做取消订单处理。
            if($noPayOrderList){
                $team_order_limit_time = tpCache('shopping.team_order_limit_time');
                $limitTime = empty($team_order_limit_time) ? 1800 : $team_order_limit_time;
                $orderLogic = new OrderLogic();
                foreach($noPayOrderList as $order){
                    if ((time() - $order['add_time']) > $limitTime) {
                        $orderLogic->cancel_order($order['user_id'], $order['order_id']);
                    }
                }
            }
        }
    }


}