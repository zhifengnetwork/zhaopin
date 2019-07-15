<?php


namespace app\common\logic;
use app\common\model\Cart;
use app\common\model\CouponList;
use app\common\model\Shop;
use app\common\util\TpshopException;
use app\mobile\controller\Sign;
use app\common\model\Goods;
use think\Model;
use think\Db;
/**
 * 计算价格类
 * Class CatsLogic
 * @package Home\Logic
 */
class Pay
{
    protected $payList;
    protected $userId;
    protected $user;

    private $totalAmount = 0;//订单总价
    private $orderAmount = 0;//应付金额
    private $shippingPrice = 0;//物流费
    private $goodsPrice = 0;//商品总价
    private $cutFee = 0;//共节约多少钱
    private $totalNum = 0;// 商品总共数量
    private $integralMoney = 0;//积分抵消金额
    private $userMoney = 0;//使用余额
    private $payPoints = 0;//使用积分
    private $couponPrice = 0;//优惠券抵消金额
    private $signPrice = 0;//签到抵消金额
    private $deposit = 0;//竞拍订金

    private $orderPromId;//订单优惠ID
    private $orderPromAmount = 0;//订单优惠金额
    private $couponId;
    private $shop;//自提点

    /**
     * 计算订单表的普通订单商品
     * @param $order_goods
     * @return $this
     * @throws TpshopException
     */
    public function payOrder($order_goods){
        $this->payList = $order_goods;
        $order = Db::name('order')->where('order_id',  $this->payList[0]['order_id'])->find();
        if(empty($order)){
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '找不到订单数据', 'result' => '']);
        }
        $reduce = tpCache('shopping.reduce');
        if($order['pay_status'] == 0 && $reduce == 2){
            $goodsListCount = count($this->payList);
            for ($payCursor = 0; $payCursor < $goodsListCount; $payCursor++) {
                $goods_stock = getGoodNum($this->payList[$payCursor]['goods_id'], $this->payList[$payCursor]['spec_key']); // 最多可购买的库存数量
                if($goods_stock <= 0 && $this->payList[$payCursor]['goods_num'] > $goods_stock){
                    throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => $this->payList[$payCursor]['goods_name'].','.$this->payList[$payCursor]['spec_key_name'] . "库存不足,请重新下单", 'result' => '']);
                }
            }
        }
        $this->Calculation();
        return $this;
    }

    /**
     * 计算购买购物车的商品
     * @param $cart_list
     * @return $this
     * @throws TpshopException
     */
    public function payCart($cart_list){
        $this->payList = $cart_list;
        $goodsListCount = count($this->payList);
        if ($goodsListCount == 0) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '你的购物车没有选中商品', 'result' => '']);
        }
        $this->Calculation();
        return $this;
    }

    /**
     * 计算购买商品表的商品
     * @param $goods_list
     * @return $this
     * @throws TpshopException
     */
    public function payGoodsList($goods_list)
    {
        $goodsListCount = count($goods_list);
        if ($goodsListCount == 0) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '你的购物车没有选中商品', 'result' => '']);
        }
        $discount = $this->getDiscount();
        for ($goodsCursor = 0; $goodsCursor < $goodsListCount; $goodsCursor++) {
            //优先使用member_goods_price，没有member_goods_price使用goods_price
            if(empty($goods_list[$goodsCursor]['member_goods_price'])){
                //积分商品不打折。因为是全积分商品打会员折扣，结算会出现负数
                if($goods_list[$goodsCursor]['exchange_integral'] > 0){
                    $goods_list[$goodsCursor]['member_goods_price'] = $goods_list[$goodsCursor]['goods_price'];
                }else{
                    $goods_list[$goodsCursor]['member_goods_price'] = $discount * $goods_list[$goodsCursor]['goods_price'];
                }

            }
        }
        $this->payList = $goods_list;
        $this->Calculation();
        return $this;
    }

    /**
     * 初始化计算
     */
    private function Calculation()
    {
        //查出搭配购的商品
        if($this->payList){
            $Cart = new Cart();
            foreach ($this->payList as $cartKey => $cartVal) {
                if ($cartVal['prom_type'] == 7) {
                    $arr = $Cart->where(['combination_group_id' => $cartVal['id'], 'id' => ['neq', $cartVal['id']]])->select();
                    $this->payList = array_merge($this->payList, $arr);
                }
            }
        }

        $goodsListCount = count($this->payList);

        for ($payCursor = 0; $payCursor < $goodsListCount; $payCursor++) {
            $this->payList[$payCursor]['goods_fee'] = $this->payList[$payCursor]['goods_num'] * $this->payList[$payCursor]['member_goods_price'];    // 小计
            $this->goodsPrice += $this->payList[$payCursor]['goods_fee']; // 商品总价
            if(array_key_exists('market_price',$this->payList[$payCursor])){
                $this->cutFee += $this->payList[$payCursor]['goods_num'] * ($this->payList[$payCursor]['market_price'] - $this->payList[$payCursor]['member_goods_price']);// 共节约
            }
            $this->totalNum += $this->payList[$payCursor]['goods_num'];
        }
        $this->orderAmount = $this->goodsPrice;
        $this->totalAmount = $this->goodsPrice;
    }

    /**
     * 设置用户ID
     * @param $user_id
     * @return $this
     * @throws TpshopException
     */
    public function setUserId($user_id)
    {
        $this->userId = $user_id;
        $this->user = Db::name('users')->where(['user_id' => $this->userId])->find();
        if(empty($this->user)){
            throw new TpshopException("计算订单价格",0,['status' => -9, 'msg' => '未找到用户', 'result' => '']);
        }
        return $this;
    }

    public function setShopById($shop_id)
    {
        if($shop_id){
            $this->shop = Shop::get($shop_id);
        }
        return $this;
    }

    /**
     * 使用积分
     * @throws TpshopException
     * @param $pay_points
     * @param $is_exchange|是否有使用积分兑换商品流程
     * @param $port
     * @return $this
     */
    public function usePayPoints($pay_points, $is_exchange = false, $port = "pc")
    {
        if($pay_points > 0 && $this->orderAmount > 0){
            //积分规则修改后的逻辑
            $isUseIntegral = tpCache('integral.is_use_integral');
            $isPointMinLimit = tpCache('integral.is_point_min_limit');
            $isPointRate = tpCache('integral.is_point_rate');
            $isPointUsePercent = tpCache('integral.is_point_use_percent');
            $point_rate = tpCache('integral.point_rate');
            if($is_exchange == false){
                if($isUseIntegral==1 && $isPointUsePercent==1) {
                    $use_percent_point = tpCache('integral.point_use_percent')/100;
                }else{
                    $use_percent_point = 1;
                }
                if($isUseIntegral==1 && $isPointMinLimit==1) {
                    $min_use_limit_point = tpCache('integral.point_min_limit');
                }else{
                    $min_use_limit_point = 0;
                }
                if($isUseIntegral == 0 || $isPointRate != 1){
                    throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => '该笔订单不能使用积分', 'result' => '']);
                }
                if($use_percent_point > 0 && $use_percent_point < 1){
                    //计算订单最多使用多少积分
                    $point_limit = intval($this->totalAmount * $point_rate * $use_percent_point);
                    if($pay_points > $point_limit){
                        if($port=="mobile"){
                            $pay_points = $point_limit;
                        }else {
                            throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于" . $point_limit, 'result' => '']);
                        }
                    }
                }
                //计算订单最多使用多少积分(没勾选比例的情况)
                $next_point_limit = intval($this->totalAmount * $point_rate * $use_percent_point);
                if($port!="mobile" && $pay_points > $next_point_limit){
                    throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于" . $next_point_limit, 'result' => '']);
                }

                if($pay_points > $this->user['pay_points']){
                    throw new TpshopException("计算订单价格",0,['status' => -5, 'msg' => "你的账户可用积分为:" . $this->user['pay_points'], 'result' => '']);
                }
                if ($min_use_limit_point > 0 && $this->user['pay_points'] < $min_use_limit_point) {
                    throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => "积分小于".$min_use_limit_point."时 ，不能使用积分", 'result' => '']);
                }
                $order_amount_pay_point = round($this->orderAmount * $point_rate,2);
                //$order_amount_pay_point = $this->orderAmount * $point_rate;
                if($pay_points > $order_amount_pay_point){
                    $this->payPoints = $order_amount_pay_point;
                }else{
                    $this->payPoints = $pay_points;
                }
                $this->integralMoney = $this->payPoints / $point_rate;
                $this->orderAmount = $this->orderAmount - $this->integralMoney;
            }else{
                //积分兑换流程
                if($pay_points <= $this->user['pay_points']){
                    $this->payPoints = $pay_points;
                    $this->integralMoney = $pay_points / $point_rate;//总积分兑换成的金额
                }else{
                    $this->payPoints = 0;//需要兑换的总积分
                    $this->integralMoney = 0;//总积分兑换成的金额
                }
                $this->orderAmount = $this->orderAmount - $this->integralMoney;
            }

        }
        return $this;
    }

    /**
     * 使用余额
     * @throws TpshopException
     * @param $user_money
     * @return $this
     */
    public function useUserMoney($user_money)
    {
        if($user_money > 0){
            if($user_money > $this->user['user_money']){
                throw new TpshopException("计算订单价格",0,['status' => -6, 'msg' =>  "你的账户可用余额为:" . $this->user['user_money'], 'result' => '']);
            }
            if($this->orderAmount > 0){
                if($user_money > $this->orderAmount){
                    $this->userMoney = $this->orderAmount;
                    $this->orderAmount = 0;
                }else{
                    $this->userMoney = $user_money;
                    $this->orderAmount = $this->orderAmount - $this->userMoney;
                }
            }
        }
        return $this;
    }

    /**
     * 减去应付金额
     * @param $cut_money
     * @return $this
     */
    public function cutOrderAmount($cut_money){
        $this->orderAmount = $this->orderAmount - $cut_money;
        return $this;
    }

    /**
     * 使用优惠券
     * @param $coupon_id
     * @return $this
     */
    public function useCouponById($coupon_id){
        if($coupon_id > 0){
            $couponList = new CouponList();
            $userCoupon = $couponList->where(['uid'=>$this->user['user_id'],'id'=>$coupon_id])->find();
            if($userCoupon){
                $coupon = Db::name('coupon')->where(['id'=>$userCoupon['cid'],'status'=>1])->find(); // 获取有效优惠券类型表
                if($coupon){
                    $this->couponId = $coupon_id;
                    if ($this->orderAmount > 0) {
                        if ($coupon['money'] > $this->orderAmount) {
                            $this->couponPrice = $this->orderAmount;
                            $this->orderAmount = 0;
                        } else {
                            $this->couponPrice = $coupon['money'];
                            $this->orderAmount = $this->orderAmount - $this->couponPrice;
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * 配送
     * @param $district_id
     * @throws TpshopException
     * @return $this
     */
    public function delivery($district_id){

        if (array_key_exists('is_virtual', $this->payList[0]) && $this->payList[0]['is_virtual'] == 0) {
            if (empty($this->shop) && empty($district_id['district'])) {
                throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => '请填写收货信息', 'result' => ['']]);
            }
        }
        $GoodsLogic = new GoodsLogic();
        $checkGoodsShipping = $GoodsLogic->checkGoodsListShipping($this->payList, $district_id['district']);
        foreach($checkGoodsShipping as $shippingKey => $shippingVal){
            if($shippingVal['shipping_able'] != true){
                throw new TpshopException("计算订单价格",0,['status'=>-1, 'code' => 301,
                    'msg'=>'订单中部分商品【 '.$shippingVal['goods_name'].' 】不支持对当前地址的配送请返回购物车修改',
                    'result'=>['goods_shipping'=>$checkGoodsShipping]]);
            }
        }
        //使用自提点不计算运费
        if(!empty($this->shop)){
            return $this;
        }
        //预售活动暂不计算运费
        if ($this->payList[0]['prom_type'] == 4) {
            return $this;
        }
        //非免费产品，内蒙、西藏、新疆满4件包邮
        if ($this->payList[0]['goods']->sign_free_receive != 1 ) {
            if ($district_id['province'] == 4670 || $district_id['province'] == 41103 || $district_id['province'] == 46047) {
                if ($this->totalNum >= 4 ) {
                    return $this;
                }
            }
        } else {
            // 免费产品 偏远地区满4件运费收商品价格
            if ($district_id['province'] == 4670 || $district_id['province'] == 41103 || $district_id['province'] == 46047) {
                if ($this->totalNum >= 4 ) {
                    $this->shippingPrice = $this->goodsPrice;
                    $this->orderAmount = $this->orderAmount + $this->shippingPrice;
                    $this->totalAmount = $this->totalAmount + $this->shippingPrice;
                    return $this;
                }
            }
        }

        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        if($this->goodsPrice < $freight_free || $freight_free == 0){
            $this->shippingPrice = $GoodsLogic->getFreight($this->payList, $district_id['district']);
            $this->orderAmount = $this->orderAmount + $this->shippingPrice;
            $this->totalAmount = $this->totalAmount + $this->shippingPrice;
        }else{
            $this->shippingPrice = 0;
        }


        return $this;
    }

    /**
     * 获取折扣
     * @return int
     */
    private function getDiscount()
    {
        if(empty($this->user['discount'])){
            return 1;
        }else{
            return $this->user['discount'];
        }
    }

    /**
     * 使用订单优惠
     */
    public function orderPromotion()
    {
        $time = time();
        $order_prom_where = ['type'=>['lt',2],'end_time'=>['gt',$time],'start_time'=>['lt',$time],'money'=>['elt',$this->goodsPrice],'is_close'=>0];
        $orderProm = Db::name('prom_order')->where($order_prom_where)->order('money desc')->find();
        if ($orderProm) {
            if ($orderProm['type'] == 0) {
                $expressionAmount = round($this->goodsPrice * $orderProm['expression'] / 100, 2);//满额打折
                $this->orderPromAmount = round($this->goodsPrice - $expressionAmount, 2);
                $this->orderPromId = $orderProm['id'];
            } elseif ($orderProm['type'] == 1) {
                $this->orderPromAmount = $orderProm['expression'];
                $this->orderPromId = $orderProm['id'];
            }
        }
        $this->orderAmount = $this->orderAmount - $this->orderPromAmount;
        return $this;
    }

    /**
     * 使用签到免费领取
     * @return int
     */
    public function getUserSign()
    {

       if ($this->payList[0]['goods']->sign_free_receive != 0 ) {
            if ( $this->user['super_nsign'] != 0 || $this->user['is_distribut'] != 0 || $this->user['is_agent'] != 0 ) {
                $isReceive = provingReceive($this->user, $this->payList[0]['goods']->sign_free_receive, $this->totalNum);
                //是代理又是分销的情况
                if ( $this->user['is_agent'] == 1 && $this->payList[0]['goods']->sign_free_receive == 2) {

                    $data = M('order_sign_receive')->where(['uid' => $this->user['user_id'], 'type' => 2])->order('addend_time desc')->select();
                    $newTimeM = date('m', time());//当前月份
                    $addTimeM = date('m', $data[0]['addend_time']); //最近下单月份
                    //代理每月可领取1次
                    if ($newTimeM == $addTimeM ) {
                        // 能否领取商品
                        $isReceive = ['status' => 0] ;
                    }
                }
                if($isReceive['status'] == 2){
                    if ($this->payList[0]['goods']->sign_free_receive == 1) {
                        // 免费领取的不限制数量
                        $this->orderAmount = $this->orderAmount - $this->payList[0]['goods']->shop_price * $this->totalNum; // 应付金额
                        $this->totalAmount = $this->totalAmount - $this->payList[0]['goods']->shop_price * $this->totalNum;
                        $this->signPrice = $this->payList[0]['goods']->shop_price * $this->totalNum; //签到抵扣
                    }else{
                        // 代理商品只扣取一份价钱
                        $this->orderAmount = $this->orderAmount - $this->payList[0]['goods']->shop_price; // 应付金额
                        $this->totalAmount = $this->totalAmount - $this->payList[0]['goods']->shop_price;;
                        $this->signPrice = $this->payList[0]['goods']->shop_price; //签到抵扣
                    }
                }
            }
        }
        return $this;
    }

    /**
     * 竞拍使用订金
     * @return int
     */
    public function getAuction()
    {
        $query = Db::name('AuctionPrice')
            ->where(['auction_id' => $this->payList[0]['prom_id'], 'is_out' => 2])
            ->order('offer_price desc')->find();

        if (!empty($query)) {

            $EarnestMoney = Db::name('AuctionDeposit')
                ->where(['auction_id' => $this->payList[0]['prom_id'], 'user_id' => $this->user['user_id'], 'status' => 1])
                ->value('deposit');
            if(!empty($EarnestMoney)){
                $this->deposit = $EarnestMoney;

                $this->orderAmount = $EarnestMoney > $query['offer_price'] ? 0 : $query['offer_price'] - $EarnestMoney;
            }

        }

        return $this;
    }

    /**
     * 获取实际上使用的余额
     * @return int
     */
    public function getUserMoney()
    {
        return $this->userMoney;
    }

    /**
     * 获取订单总价
     * @return int
     */
    public function getTotalAmount()
    {
        return number_format($this->totalAmount, 2, '.', '');
    }

    /**
     * 获取订单应付金额
     * @return int
     */
    public function getOrderAmount()
    {
        return number_format($this->orderAmount, 2, '.', '');
    }

    /**
     * 获取实际上使用的积分抵扣金额
     * @return float
     */
    public function getIntegralMoney(){
        return $this->integralMoney;
    }

    /**
     * 获取实际上使用的积分
     * @return float|int
     */
    public function getPayPoints()
    {
        return $this->payPoints;
    }

    /**
     * 获取物流费
     * @return int
     */
    public function getShippingPrice()
    {
        return $this->shippingPrice;
    }

    /**
     *  获取优惠券费
     * @return int
     */
    public function getCouponPrice()
    {
        return $this->couponPrice;
    }

    /**
     *  获取签到抵消费
     * @return int
     */
    public function getSignPrice()
    {
        return $this->signPrice;
    }

    /**
     *  竞拍订金
     * @return int
     */
    public function getAuctionDeposit()
    {
        return $this->deposit;
    }
    /**
     * 商品总价
     * @return int
     */
    public function getGoodsPrice()
    {
        return $this->goodsPrice;
    }

    /**
     * 获取用户
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    public function getPayList()
    {
        return $this->payList;
    }

    public function getCouponId()
    {
        return $this->couponId;
    }

    public function getOrderPromAmount()
    {
        return $this->orderPromAmount;
    }
    public function getOrderPromId()
    {
        return $this->orderPromId;
    }

    public function getShop()
    {
        return $this->shop;
    }

    public function getToTalNum()
    {
        return $this->totalNum;
    }

    public function toArray()
    {
        return [
            'shipping_price' => round($this->shippingPrice, 2),
            'coupon_price' => round($this->couponPrice, 2),
            'sign_price' => round($this->signPrice, 2),
            'deposit' => round($this->deposit, 2),
            'user_money' => round($this->userMoney, 2),
            'integral_money' => $this->integralMoney,
            'pay_points' => $this->payPoints,
            'order_amount' => round($this->orderAmount, 2),
            'total_amount' => round($this->totalAmount, 2),
            'goods_price' => round($this->goodsPrice, 2),
            'total_num' => $this->totalNum,
            'order_prom_amount' => round($this->orderPromAmount, 2),
        ];
    }
}