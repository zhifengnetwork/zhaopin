<?php


namespace app\common\logic;

use app\common\model\Combination;
use app\common\model\CombinationGoods;
use app\common\model\SpecGoodsPrice;
use app\common\model\Cart;
use app\common\model\Goods;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 购物车 逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class CartLogic extends Model
{
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型
    protected $goodsBuyNum;//购买的商品数量
    protected $session_id;//session_id
    protected $user_id = 0;//user_id
    protected $userGoodsTypeCount = 0;//用户购物车的全部商品种类
    protected $userCouponNumArr; //用户符合购物车店铺可用优惠券数量
    protected $combination;

    public function __construct()
    {
        parent::__construct();
        $this->session_id = session_id();
    }

    /**
     * 将session_id改成unique_id
     * @param $uniqueId |api唯一id 类似于 pc端的session id
     */
    public function setUniqueId($uniqueId)
    {
        $this->session_id = $uniqueId;
    }

    /**
     * 包含一个商品模型
     * @param $goods_id
     */
    public function setGoodsModel($goods_id)
    {
        if ($goods_id > 0) {
            $goodsModel = new Goods();
            $this->goods = $goodsModel::get($goods_id);
        }
    }

    /**
     * 通过item_id包含一个商品规格模型
     * @param $item_id
     */
    public function setSpecGoodsPriceById($item_id)
    {
        if ($item_id > 0) {
            $specGoodsPriceModel = new SpecGoodsPrice();
            $this->specGoodsPrice = $specGoodsPriceModel::get($item_id, '', 10);
        }else{
            $this->specGoodsPrice = null;
        }
    }

    /**
     * 通过eky包含一个商品规格模型
     * @param $key
     */
    public function setSpecGoodsPriceByKey($key)
    {
        if ($key) {
            $specGoodsPriceModel = new SpecGoodsPrice();
            $this->specGoodsPrice = $specGoodsPriceModel::get(['goods_id' => $this->goods['goods_id'], 'key' => $key], '', 10);
        }else{
            $this->specGoodsPrice = null;
        }
    }

    public function setCombination($combination)
    {
        $this->combination = $combination;
    }

    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
        $this->user = Db::name('users')->where(['user_id' => $this->user_id])->find();
    }

    /**
     * 设置购买的商品数量
     * @param $goodsBuyNum
     */
    public function setGoodsBuyNum($goodsBuyNum)
    {
        $this->goodsBuyNum = $goodsBuyNum;
    }

    /**
     * 立即购买
     * @return mixed
     * @throws TpshopException
     */
    public function buyNow()
    {
        if (empty($this->goods)) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '购买商品不存在1', 'result' => '']);
        }
        // 是否可免费领取
        if ($this->goods['sign_free_receive'] != 0 ) {

             $isReceive = provingReceive($this->user, $this->goods['sign_free_receive'], $this->goodsBuyNum); 

            if($isReceive['status'] == 0){
                // throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '购买商品不存在2', 'result' => '']);
                throw new TpshopException("立即购买",0, $isReceive);
            }
        }
        if (empty($this->goodsBuyNum)) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }
        if($this->goods['is_virtual'] == 1){
            if($this->goods['virtual_indate'] < time()){
                throw new TpshopException('立即购买',0,['status'=>0,'msg'=>'虚拟商品有效期已过','result'=>'']);
            }
            $isBuyWhere = [
                'og.goods_id'=>$this->goods['goods_id'],
                'o.user_id'  =>$this->user_id,
                'o.deleted'=>0,
                'o.order_status'=>['neq',3]
            ];
            $isBuySum = Db::name('order_goods')->alias('og')->join('__ORDER__ o','og.order_id = o.order_id','LEFT')->where($isBuyWhere)->sum('og.goods_num');
            if (($this->goodsBuyNum + $isBuySum) > $this->goods['virtual_limit']) {
                throw new TpshopException('立即购买',0,['status' => 0, 'msg' => '您已超过该商品的限制购买数', 'result' => '']);
            }
        }

        $buyGoods = [
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'goods_id' => $this->goods['goods_id'],
            'goods_sn' => $this->goods['goods_sn'],
            'goods_name' => $this->goods['goods_name'],
            'market_price' => $this->goods['market_price'],
            'goods_price' => $this->goods['shop_price'],
            'member_goods_price' => $this->goods['shop_price'],
            'goods_num' => $this->goodsBuyNum, // 购买数量
            'add_time' => time(), // 加入购物车时间
            'prom_type' => 0,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            'prom_id' => 0,   // 活动id
            'weight' => $this->goods['weight'],   // 商品重量
            'goods' => $this->goods,
            'is_virtual'=>$this->goods['is_virtual'],
            'virtual_indate'=>$this->goods['virtual_indate'],
        ];

        if (empty($this->specGoodsPrice)) {
            $specGoodsPriceCount = Db::name('SpecGoodsPrice')->where("goods_id", $this->goods['goods_id'])->count('item_id');
            if ($specGoodsPriceCount > 0) {
                throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '必须传递商品规格', 'result' => '']);
            }
            $prom_type = $this->goods['prom_type'];
            $store_count = $this->goods['store_count'];
        } else {
            $buyGoods['member_goods_price'] = $this->specGoodsPrice['price'];
            $buyGoods['goods_price'] = $this->specGoodsPrice['price'];
            $buyGoods['spec_key'] = $this->specGoodsPrice['key'];
            $buyGoods['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
            $buyGoods['sku'] = $this->specGoodsPrice['sku']; //商品条形码
            $prom_type = $this->specGoodsPrice['prom_type'];
            $store_count = $this->specGoodsPrice['store_count'];
        }
        if ($this->goodsBuyNum > $store_count) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $this->goods['store_count'], 'result' => '']);
        }
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($prom_type)) {
            $goodsPromLogic = $goodsPromFactory->makeModule($this->goods, $this->specGoodsPrice);
            if ($goodsPromLogic->checkActivityIsAble()) {
                $buyGoods = $goodsPromLogic->buyNow($buyGoods);
            }
        } else {
            if ($this->goods['prom_type'] == 0) {
                if (!empty($this->goods['price_ladder'])) {
                    //如果有阶梯价格,就是用阶梯价格
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = $this->goods['price_ladder'];
                    $buyGoods['goods_price'] = $buyGoods['member_goods_price'] = $goodsLogic->getGoodsPriceByLadder($this->goodsBuyNum, $buyGoods['goods_price'], $price_ladder);
                } else if ($this->user_id) {
                    $user = Users::get(['user_id' => $this->user_id]);
                    $discount = (empty((float)$user['discount'])) ? 1 : $user['discount'];
                    $buyGoods['goods_price'] = $buyGoods['member_goods_price'] = round($buyGoods['goods_price'] * $discount, 2);
                }
            }
        }
        $cart = new Cart();
        $buyGoods['member_goods_price']?$buyGoods['member_goods_price']=round($buyGoods['member_goods_price'],2):'';
        $buyGoods['cut_fee'] = $cart->getCutFeeAttr(0, $buyGoods);
        $buyGoods['goods_fee'] = $cart->getGoodsFeeAttr(0, $buyGoods);
        $buyGoods['total_fee'] = $cart->getTotalFeeAttr(0, $buyGoods);
        return $buyGoods;
    }

    /**
     * 加入购物车入口
     */
    public function addGoodsToCart()
    {
        if (empty($this->goods)) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '购买商品不存在']);
        }
        if ($this->goods['exchange_integral'] > 0) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '积分商品跳转', 'result' => ['url' => U('Goods/goodsInfo', ['id' => $this->goods['goods_id'], 'item_id' => $this->specGoodsPrice['item_id']], '', true)]]);
        }
        $userCartCount = Db::name('cart')->where(['user_id' => $this->user_id, 'session_id' => $this->session_id])->count();//获取用户购物车的商品有多少种
        if ($userCartCount >= 20) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '购物车最多只能放20种商品']);
        }
        $specGoodsPriceCount = Db::name('SpecGoodsPrice')->where("goods_id", $this->goods['goods_id'])->count('item_id');
        if (empty($this->specGoodsPrice) && !empty($specGoodsPriceCount)) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '必须传递商品规格', 'result' => ['url' => U('Goods/goodsInfo', ['id' => $this->goods['goods_id']], '', true)]]);
        }
        //有商品规格，和没有商品规格
        if($this->specGoodsPrice){
            $prom_type = $this->specGoodsPrice['prom_type'];
        }else{
            $prom_type = $this->goods['prom_type'];
        }
        switch($prom_type) {
            case 1:
                $this->addFlashSaleCart();
                break;
            case 2:
                $this->addGroupBuyCart();
                break;
            case 3:
                $this->addPromGoodsCart();
                break;
            default:
                $this->addNormalCart();
        }
    }

    /**
     * 购物车添加普通商品
     */
    private function addNormalCart()
    {
        if (empty($this->specGoodsPrice)) {
            $price = $this->goods['shop_price'];
            $store_count = $this->goods['store_count'];
        } else {
            //如果有规格价格，就使用规格价格，否则使用本店价。
            $price = $this->specGoodsPrice['price'];
            $store_count = $this->specGoodsPrice['store_count'];
        }
        // 查询购物车是否已经存在这商品
        $cart_where = ['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: ''), 'prom_type' => 0];
        if (!$this->user_id) {
            $cart_where['session_id'] = $this->session_id;
        }
        $userCartGoods = Cart::get($cart_where);
        //统计所有的商品
        //不止判断普通商品，还要统计同商品的其他类型
        $cart_whereCount = ['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')];
        // 未登录时判断
        if (!$this->user_id) {
            $cart_whereCount['session_id'] = $this->session_id;
        }
        $userCartGoodsSum = db('cart')->where($cart_whereCount)->sum('goods_num');
        //判断库存
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoodsSum;//本次要购买的数量加上购物车的本身存在的数量
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $store_count) {
            $userCartGoodsNum = empty($userCartGoodsSum) ? 0 : $userCartGoodsSum;///获取用户购物车的抢购商品数量
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $store_count . ',当前购物车已有' . $userCartGoodsNum . '件']);
        }

        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $userCartGoods['goods_num'] = $userCartGoodsSum?$userCartGoodsSum:0;
            $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num'];//本次要购买的数量加上购物车的本身存在的数量
            //如果有阶梯价格,就是用阶梯价格
            if (!empty($this->goods['price_ladder'])) {
                $goodsLogic = new GoodsLogic();
                $price_ladder = $this->goods['price_ladder'];
                $price = $goodsLogic->getGoodsPriceByLadder($userWantGoodsNum, $this->goods['shop_price'], $price_ladder);
            }
            if ($userWantGoodsNum > 200) {
                $userWantGoodsNum = 200;
            }
            if ($userWantGoodsNum > $store_count) {
                $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num'];///获取用户购物车的抢购商品数量
                throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $store_count . ',当前购物车已有' . $userCartGoodsNum . '件']);
            }
            $cartResult = $userCartGoods->save(['goods_num' => $userWantGoodsNum, 'goods_price' => $price, 'member_goods_price' => $price]);
        } else {
            //如果该商品没有存在购物车
            if ($this->goodsBuyNum > $store_count) {
                throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $this->goods['store_count']]);
            }
            //如果有阶梯价格,就是用阶梯价格
            if (!empty($this->goods['price_ladder'])) {
                $goodsLogic = new GoodsLogic();
                $price_ladder = $this->goods['price_ladder'];
                $price = $goodsLogic->getGoodsPriceByLadder($this->goodsBuyNum, $this->goods['shop_price'], $price_ladder);
            }
            $cartAddData = array(
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'goods_price' => $price,  // 原价
                'member_goods_price' => $price,  // 会员折扣价 默认为 购买价
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 0,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'prom_id' => 0,   // 活动id
            );
            if ($this->specGoodsPrice) {
                $cartAddData['item_id'] = $this->specGoodsPrice['item_id'];
                $cartAddData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
            }
            $cartResult = Db::name('Cart')->insertGetId($cartAddData);
        }
        if ($cartResult === false) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '加入购物车失败']);
        }
    }

    /**
     * 购物车添加秒杀商品
     */
    private function addFlashSaleCart()
    {
        $flashSaleLogic = new FlashSaleLogic($this->goods, $this->specGoodsPrice);
        $flashSale = $flashSaleLogic->getPromModel();
        $flashSaleIsEnd = $flashSaleLogic->checkActivityIsEnd();
        if ($flashSaleIsEnd) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '秒杀活动已结束']);
        }
        $flashSaleIsAble = $flashSaleLogic->checkActivityIsAble();
        if (!$flashSaleIsAble) {
            //活动没有进行中，走普通商品下单流程
            $this->addNormalCart();
            return;
        } else {
            //活动进行中
            if ($this->user_id == 0) {
                throw new TpshopException("加入购物车", 0, ['status' => -101, 'msg' => '购买活动商品必须先登录']);
            }
        }
        if ($this->goodsBuyNum > $flashSale['buy_limit']) {
            throw new TpshopException("加入购物车", 0, ['status' => 0,'msg' => '每人限购' . $flashSale['buy_limit'] . '件']);
        }
        //获取用户购物车的抢购商品 //加入有活动的商品购物车之前先将没有活动得该商品清除
        if (!$this->user_id) {
            db('cart')->where('user_id',$this->user_id)->where('goods_id',$this->goods['goods_id'])->where('session_id',$this->session_id)->where('prom_type','<>',1)->delete();
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            db('cart')->where('user_id',$this->user_id)->where('goods_id',$this->goods['goods_id'])->where('prom_type','<>',1)->delete();
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }
        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num'];///获取用户购物车的抢购商品数量
        $userFlashOrderGoodsNum = $flashSaleLogic->getUserFlashOrderGoodsNum($this->user_id); //获取用户抢购已购商品数量
        $flashSalePurchase = $flashSale['goods_num'] - $flashSale['buy_num'];//抢购剩余库存
        $userBuyGoodsNum = $this->goodsBuyNum + $userFlashOrderGoodsNum + $userCartGoodsNum;
        if ($userBuyGoodsNum > $flashSale['buy_limit']) {
            throw new TpshopException("加入购物车", 0, ['status' => 0,'msg' => '每人限购' . $flashSale['buy_limit'] . '件，您已下单' . $userFlashOrderGoodsNum . '件' . '购物车已有' . $userCartGoodsNum . '件']);
        }
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoodsNum;//本次要购买的数量加上购物车的本身存在的数量
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $flashSalePurchase) {
            throw new TpshopException("加入购物车", 0, ['status' => 0,'msg' => '商品库存不足，剩余' . $flashSalePurchase . ',当前购物车已有' . $userCartGoodsNum . '件']);
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $cartResult = $userCartGoods->save(['goods_num' => $userWantGoodsNum]);
        } else {
            $cartAddFlashSaleData = array(
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'member_goods_price' => $flashSale['price'],  // 会员折扣价 默认为 购买价
                'goods_num' => $userWantGoodsNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 1,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            );
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddFlashSaleData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddFlashSaleData['item_id'] = $this->specGoodsPrice['item_id'];
                $cartAddFlashSaleData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddFlashSaleData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddFlashSaleData['goods_price'] = $this->specGoodsPrice['price'];   // 规格价
                $cartAddFlashSaleData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddFlashSaleData['goods_price'] = $this->goods['shop_price'];   // 原价
                $cartAddFlashSaleData['prom_id'] = $this->goods['prom_id'];// 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddFlashSaleData);
        }
        if ($cartResult === false) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '加入购物车失败']);
        }
    }

    /**
     *  购物车添加团购商品
     */
    private function addGroupBuyCart()
    {
        $groupBuyLogic = new GroupBuyLogic($this->goods, $this->specGoodsPrice);
        $groupBuy = $groupBuyLogic->getPromModel();
        //活动是否已经结束
        if ($groupBuy['is_end'] == 1 || empty($groupBuy)) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '团购活动已结束']);
        }
        $groupBuyIsAble = $groupBuyLogic->checkActivityIsAble();
        if (!$groupBuyIsAble) {
            //活动没有进行中，走普通商品下单流程
            $this->addNormalCart();
            return;
        } else {
            //活动进行中
            if ($this->user_id == 0) {
                throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '购买活动商品必须先登录']);
            }
        }
        //获取用户购物车的团购商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }
        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num'];///获取用户购物车的团购商品数量
        $userWantGoodsNum = $userCartGoodsNum + $this->goodsBuyNum;//购物车加上要加入购物车的商品数量
        $groupBuyPurchase = $groupBuy['goods_num'] - $groupBuy['buy_num'];//团购剩余库存
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $groupBuyPurchase) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $groupBuyPurchase . ',当前购物车已有' . $userCartGoodsNum . '件']);
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $cartResult = $userCartGoods->save(['goods_num' => $userWantGoodsNum]);
        } else {
            $cartAddFlashSaleData = array(
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'member_goods_price' => $groupBuy['price'],  // 会员折扣价 默认为 购买价
                'goods_num' => $userWantGoodsNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 2,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            );
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddFlashSaleData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddFlashSaleData['item_id'] = $this->specGoodsPrice['item_id'];
                $cartAddFlashSaleData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddFlashSaleData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddFlashSaleData['goods_price'] = $this->specGoodsPrice['price'];   // 规格价
                $cartAddFlashSaleData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddFlashSaleData['goods_price'] = $this->goods['shop_price'];   // 原价
                $cartAddFlashSaleData['prom_id'] = $this->goods['prom_id'];// 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddFlashSaleData);
        }
        if ($cartResult === false) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '加入购物车失败']);
        }
    }

    /**
     *  购物车添加优惠促销商品
     */
    private function addPromGoodsCart()
    {
        $promGoodsLogic = new PromGoodsLogic($this->goods, $this->specGoodsPrice);
        $promGoods = $promGoodsLogic->getPromModel();
        //活动是否存在，是否关闭，是否处于有效期
        if ($promGoodsLogic->checkActivityIsEnd() || !$promGoodsLogic->checkActivityIsAble()) {
            //活动不存在，已关闭，不处于有效期,走添加普通商品流程
            $this->addNormalCart();
            return;
        } else {
            //活动进行中
            if ($this->user_id == 0) {
                throw new TpshopException("加入购物车", 0, ['status' => -101, 'msg' => '购买活动商品必须先登录']);
            }
        }
//        halt(($promGoodsLogic->checkActivityIsEnd() || !$promGoodsLogic->checkActivityIsAble()));
        //如果有规格价格，就使用规格价格，否则使用本店价。
        if ($this->specGoodsPrice) {
            $priceBefore = $this->specGoodsPrice['price'];
            $storeCount = $this->specGoodsPrice['store_count'];
        } else {
            $priceBefore = $this->goods['shop_price'];
            $storeCount = $this->goods['store_count'];
        }
        //计算优惠价格
        $priceAfter = $promGoodsLogic->getPromotionPrice($priceBefore);
        // 查询购物车是否已经存在这商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }

        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num']; ///获取用户购物车的促销商品数量
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num']; //本次要购买的数量加上购物车的本身存在的数量
        $UserPromOrderGoodsNum = $promGoodsLogic->getUserPromOrderGoodsNum($this->user_id); //获取用户促销已购商品数量
        $userBuyGoodsNum = $userWantGoodsNum + $UserPromOrderGoodsNum; //本次要购买的数量+购物车本身数量+已经买
        if ($userBuyGoodsNum > $promGoods['buy_limit']) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '每人限购' . $promGoods['buy_limit'] . '件，您已下单' . $UserPromOrderGoodsNum . '件，' . '购物车已有' . $userCartGoodsNum . '件']);
        }
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoodsNum;//本次要购买的数量加上购物车的本身存在的数量
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $storeCount) {   //用户购买量不得超过库存
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' =>'商品活动库存不足，剩余' . $storeCount . ',当前购物车已有' . $userCartGoodsNum . '件']);
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            /* $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num'];//本次要购买的数量加上购物车的本身存在的数量
             if($userWantGoodsNum > 200){
                 $userWantGoodsNum = 200;
             }*/
            $cartResult = $userCartGoods->save(['goods_num' => $userWantGoodsNum, 'goods_price' => $priceBefore, 'member_goods_price' => $priceAfter]);
        } else {
            $cartAddData = array(
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'goods_price' => $priceBefore,  // 原价
                'member_goods_price' => $priceAfter,  // 会员折扣价 默认为 购买价
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 3,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            );
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddData['item_id'] = $this->specGoodsPrice['item_id'];
                $cartAddData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddData['prom_id'] = $this->goods['prom_id'];// 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddData);
        }
        if ($cartResult === false) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' =>'加入购物车失败']);
        }
    }

    /**
     * 获取用户购物车商品总数
     * @return float|int
     */
    public function getUserCartGoodsNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->sum('goods_num');
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->sum('goods_num');
        }
        $goods_num = empty($goods_num) ? 0 : $goods_num;
        setcookie('cn', $goods_num, null, '/');
        return $goods_num;
    }

    /**
     * 获取用户购物车商品总数
     * @return float|int
     */

    public function getUserCartGoodsTypeNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->count();
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->count();
        }
        return empty($goods_num) ? 0 : $goods_num;
    }

    /**
     * @param int $selected |是否被用户勾选中的 0 为全部 1为选中  一般没有查询不选中的商品情况
     * 获取用户的购物车列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCartList($selected = 0)
    {
        $cart = new Cart();
        // 如果用户已经登录则按照用户id查询
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if ($selected != 0) {
            $cartWhere['selected'] = 1;
        }
        $cartWhere['combination_group_id'] = 0;
        $cartList = $cart->with('goods')->where($cartWhere)->select();  // 获取购物车商品
        $cartCheckAfterList = $this->checkCartList($cartList);
        return $cartCheckAfterList;
    }

    /**
     * 将cart_list转成数组
     * @param $cart_list
     * @return array
     */
    public function cartListToArray($cart_list){
        if($cart_list){
            $cart_list = collection($cart_list)->hidden(['goods'])->append(['combination_cart', 'cut_fee', 'total_fee', 'goods_fee', 'combination'])->toArray();
            foreach($cart_list as $cart_key=>$cart_value){
                foreach($cart_value['combination_cart'] as $combination_cart => $combination_cart_value){
                    $cart_list[$cart_key]['combination_cart'] = collection($cart_value['combination_cart'])->append(['cut_fee','total_fee', 'goods_fee'])->toArray();
                }
            }
            return $cart_list;
        }else{
            return [];
        }
    }


    /**
     * 过滤掉无效的购物车商品
     * @param $cartList
     */
    public function checkCartList($cartList)
    {
        $goodsPromFactory = new GoodsPromFactory();
        foreach ($cartList as $cartKey => $cart) {
            //商品不存在或者已经下架
            if (empty($cart['goods']) || $cart['goods']['is_del'] != 1 || $cart['stock'] == 0) {
                $cart->delete();
                unset($cartList[$cartKey]);
                continue;
            }
            //活动商品的活动是否失效
            // if ($goodsPromFactory->checkPromType($cart['prom_type'])) {
            //     if (!empty($cart['spec_key'])) {
            //         $specGoodsPrice = SpecGoodsPrice::get(['goods_id' => $cart['goods_id'], 'item_id' => $cart['item_id']], '', true);
            //         if ($specGoodsPrice['prom_id'] != $cart['prom_id']) {
            //             $cart->delete();
            //             unset($cartList[$cartKey]);
            //             continue;
            //         }
            //     } else {
            //         if ($cart['goods']['prom_id'] != $cart['prom_id']) {
            //             $cart->delete();
            //             unset($cartList[$cartKey]);
            //             continue;
            //         }
            //         $specGoodsPrice = null;
            //     }
            //     $goodsPromLogic = $goodsPromFactory->makeModule($cart['goods'], $specGoodsPrice);
            //     if ($goodsPromLogic && !$goodsPromLogic->isAble()) {
            //         $cart->delete();
            //         unset($cartList[$cartKey]);
            //         continue;
            //     }

            // }elseif ($cart['prom_type'] == 7){
            //     //如果结束时间小于当前时间，该套餐已过期
            //     if($cart['combination']['end_time'] < time() || $cart['combination']['is_on_sale']==0){
            //         //删除自己的过期套餐
            //         db('cart')->where(['user_id'=>$cart->user_id,'prom_id'=>$cart['combination']->combination_id])->delete();
            //         unset($cartList[$cartKey]);
            //   }

            // }
        }
        $this->getUserCartGoodsNum();//删除后，需要重新设置cookie值
        return $cartList;
    }

    /**
     *  modify ：cart_count
     *  获取用户购物车欲购买的商品有多少种
     * @return int|string
     */
    public function getUserCartOrderCount()
    {
        $count = Db::name('Cart')->where(['user_id' => $this->user_id, 'selected' => 1])->count();
        return $count;
    }

    /**
     * 用户登录后 对购物车操作
     * modify：login_cart_handle
     */
    public function doUserLoginHandle()
    {
        if (empty($this->session_id) || empty($this->user_id)) {
            return;
        }
        //登录后将购物车的商品的 user_id 改为当前登录的id
        $cart = new Cart();
        $cart->save(['user_id' => $this->user_id], ['session_id' => $this->session_id, 'user_id' => 0]);
        // 查找购物车两件完全相同的商品
        $cart_id_arr = $cart->field('id')->where(['user_id' => $this->user_id])->group('goods_id,spec_key')->having('count(goods_id) > 1')->select();
        if (!empty($cart_id_arr)) {
            $cart_id_arr = get_arr_column($cart_id_arr, 'id');
            M('cart')->delete($cart_id_arr); // 删除购物车完全相同的商品
        }
    }

    /**
     * 更改购物车的商品数量
     * @param $cart_id |购物车id
     * @param $goods_num |商品数量
     * @return array
     */
    public function changeNum($cart_id, $goods_num)
    {
        $Cart = new Cart();
        $cart = $Cart::get($cart_id);

        $cart_goods_where = ['user_id' => $cart['user_id'], 'goods_id' => $cart['goods_id'], 'item_id' => $cart['item_id']];
        if (!$this->user_id) {
            $cart_goods_where['session_id'] = $this->session_id;
        }
        //判断属性库存 和购物车有几个
        $cart_goods_where['id'] = array('neq',$cart_id);
        $cart_goods_where['combination_group_id'] = array('neq',$cart_id);
        $cart_goods_num_sum = Db::name('cart')->where($cart_goods_where)->sum('goods_num');
        $store_count = db('spec_goods_price')->where(['item_id'=>$cart['item_id'],'goods_id'=>$cart['goods_id']])->value('store_count');
        if($store_count){
            $cart->limit_num = $store_count;
        }

        if ($goods_num + $cart_goods_num_sum > $cart->limit_num) {
            return ['status' => 0, 'msg' => $cart->goods_name.$cart->spec_key_name.'商品数量不能大于' . $cart->limit_num, 'result' => ['limit_num' => $cart->limit_num]];
        }
        if ($goods_num > 200) {
            $goods_num = 200;
        }
        $cart->goods_num = $goods_num;
        if ($cart['prom_type'] == 0) {
            $cartGoods = Goods::get($cart['goods_id']);
            if (!empty($cartGoods['price_ladder'])) {
                //如果有阶梯价格,就是用阶梯价格
                $goodsLogic = new GoodsLogic();
                $price_ladder = $cartGoods['price_ladder'];
                $cart->goods_price = $cart->member_goods_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $cartGoods['shop_price'], $price_ladder);
            }
            $cart->save();
        }
        if ($cart['prom_type'] == 7) {
//            $carts = $Cart->where(['combination_group_id' => $cart['combination_group_id'], 'id' => ['neq', $cart['id']]])->select();
            //xwy-2018-6-4,加入购物车改了主商品的combination_group_id为0 ，这里只能能拿id
            $carts = $Cart->where(['combination_group_id' => $cart['id'], 'id' => ['neq', $cart['id']]])->select();
            // 启动事务
            Db::startTrans();
            foreach ($carts as $cart_item) {
                $cart_goods_where = ['user_id' => $cart_item['user_id'], 'goods_id' => $cart_item['goods_id'], 'item_id' => $cart_item['item_id']];
                if (!$this->user_id) {
                    $cart_goods_where['session_id'] = $this->session_id;
                }
                //判断属性库存 和购物车有几个
                $cart_goods_where['id'] = array('neq',$cart_id);
                $cart_goods_where['combination_group_id'] = array('neq',$cart_id);
                $cart_goods_num_sum = Db::name('cart')->where($cart_goods_where)->sum('goods_num');
                $store_count = db('spec_goods_price')->where(['item_id'=>$cart_item['item_id'],'goods_id'=>$cart_item['goods_id']])->value('store_count');
               if($store_count){
                   $cart_item->limit_num = $store_count;
               }
                if($goods_num + $cart_goods_num_sum > $cart_item->limit_num){
                    // 回滚事务
                    Db::rollback();
                    return ['status' => 0, 'msg' => $cart_item->goods_name.$cart_item->spec_key_name.'商品数量不能大于' . $cart_item->limit_num, 'result' => ['limit_num' => $cart_item->limit_num]];
                }
                $cart_item->goods_num = $goods_num;
                $cart_item->save();
            }
            // 提交事务
            Db::commit();
        }
        $cart->save();
        return ['status' => 1, 'msg' => '修改商品数量成功', 'result' => ''];
    }

    /**
     * 删除购物车商品
     * @param array $cart_ids
     * @return int
     * @throws \think\Exception
     * $unique_Id 删除提供给API调用的
     */
    public function delete($cart_ids = array())
    {
        //pc和mobile调用
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
            $user['user_id'] = 0;
        }
        $Cart = new Cart();
        $delete_cart_list = $Cart->where($cartWhere)->where('id', 'IN', $cart_ids)->select();
        $delete_cart_combination_group_id = $delete_cart_ids = [];
        foreach ($delete_cart_list as $cart) {
            if ($cart['prom_type'] == 7) {
                if ($cart['combination_group_id'] == 0) {
                    //主商品删除
                    array_push($delete_cart_combination_group_id, $cart['id']);
                    array_push($delete_cart_ids, $cart['id']);
                } else {
                    //副商品删除
                    array_push($delete_cart_ids, $cart['id']);
                    $combination_group_num = Db::name('cart')->where(['combination_group_id' => $cart['combination_group_id']])->count('id');
                    if ($combination_group_num <= 1) {
                        array_push($delete_cart_combination_group_id, $cart['combination_group_id']);
                        array_push($delete_cart_ids, $cart['combination_group_id']);
                    }
                }
            } else {
                array_push($delete_cart_ids, $cart['id']);
            }
        }
        $delete = Db::name('cart')
            ->where($cartWhere)
            ->where(function ($query) use ($delete_cart_ids, $delete_cart_combination_group_id) {
                if ($delete_cart_combination_group_id) {
                    $query->where('id', 'IN', $delete_cart_ids)->whereOr('combination_group_id', 'IN', $delete_cart_combination_group_id);
                } else {
                    $query->where('id', 'IN', $delete_cart_ids);
                }

            })
            ->delete();
        return $delete;
    }

    /**
     * 更新购物车，并返回计算结果
     * @param array $cart
     */
    public function AsyncUpdateCart($cart = [])
    {
        if(empty($cart)){
            return;
        }
        $cartSelectedId = $cartNoSelectedId = [];
        foreach ($cart as $key => $val) {
            if ($cart[$key]['selected'] == 1) {
                $cartSelectedId[] = $cart[$key]['id'];
            } else {
                $cartNoSelectedId[] = $cart[$key]['id'];
            }
        }
        $Cart = new Cart();
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if (!empty($cartNoSelectedId)) {
            $Cart->where('id', 'IN', $cartNoSelectedId)->where($cartWhere)->update(['selected' => 0]);
        }
        if (!empty($cartSelectedId)) {
            $cartList = $Cart->where('id', 'IN', $cartSelectedId)->where($cartWhere)->select();
            //查出搭配购的商品
            foreach ($cartList as $cartKey => $cartVal) {
                $cartVal->save(['selected' => 1]);
                if ($cartVal['prom_type'] == 7) {
                    //加入购物车改了主商品的combination_group_id为0 ，这里只能能拿id
                    $Cart->where(['combination_group_id' => $cartVal['id'], 'id' => ['neq', $cartVal['id']]])->update(['selected' => 1]);
                }
            }
        }
    }

    /**
     * 获取购物车的价格详情
     * @param $cartList |购物车列表
     * @return array
     */
    public function getCartPriceInfo($cartList = null)
    {
        $total_fee = $goods_fee = $goods_num = 0;//初始化数据。商品总额/节约金额/商品总共数量
        if ($cartList) {
            foreach ($cartList as $cartKey => $cartItem) {
                $total_fee += $cartItem['goods_fee'];
                $goods_fee += $cartItem['cut_fee'];
                $goods_num += $cartItem['goods_num'];
                if($cartItem['combination_cart']){
                    foreach($cartItem['combination_cart'] as $combinationCartKey=>$combinationCartItem){
                        $total_fee += $combinationCartItem['goods_fee'];
                        $goods_fee += $combinationCartItem['cut_fee'];
                        $goods_num += $combinationCartItem['goods_num'];
                    }
                }
            }
        }
        $total_fee = round($total_fee,2);
        $goods_fee = round($goods_fee,2);
        return compact('total_fee', 'goods_fee', 'goods_num');
    }

    /**
     * 转换购物车的优惠券数据
     * @param $cartList |购物车商品
     * @param $userCouponList |用户优惠券列表
     * @return mixedable
     */
    public function getCouponCartList($cartList, $userCouponList)
    {
        $userCouponArray = collection($userCouponList)->toArray();  //用户的优惠券
        $couponNewList = [];
        $coupon_num = 0;
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if ($userCouponArray[$couponKey]['coupon']['use_type'] == 0) { //全店使用优惠券
                if ($cartList['total_fee'] >= $userCouponArray[$couponKey]['coupon']['condition']) {  //订单商品总价是否符合优惠券购买价格
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    $coupon_num += 1;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } elseif ($userCouponArray[$couponKey]['coupon']['use_type'] == 1) { //指定商品优惠券
                $pointGoodsPrice = 0;//指定商品的购买总价
                $couponGoodsId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_id');
                if($cartList['cartList']){
                    foreach ($cartList['cartList'] as $tKey => $Item) {
                        if (in_array($Item['goods_id'], $couponGoodsId)) {
                            $pointGoodsPrice += $Item['member_goods_price'] * $Item['goods_num'];  //用会员折扣价统计每个商品的总价
                        }
                    }
                    if ($pointGoodsPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                        $userCouponArray[$couponKey]['coupon']['able'] = 1;
                        $coupon_num += 1;
                    } else {
                        $userCouponArray[$couponKey]['coupon']['able'] = 0;
                    }
                }

            } elseif ($userCouponArray[$couponKey]['coupon']['use_type'] == 2) { //指定商品分类优惠券
                $pointGoodsCatPrice = 0;//指定商品分类的购买总价
                $couponGoodsCatId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_category_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods']['cat_id'], $couponGoodsCatId)) {
                        $pointGoodsCatPrice += $Item['member_goods_price'] * $Item['goods_num']; //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsCatPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    $coupon_num += 1;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } else {
                $userCouponList[$couponKey]['coupon']['able'] = 1;
            }
            $couponNewList[] = $userCouponArray[$couponKey];
        }
        $this->userCouponNumArr['usable_num'] = $coupon_num;
        return $couponNewList;
    }

    /**
     * 获取可用的购物车优惠券返回。不可用的过滤掉。
     * @param $cartList |购物车商品
     * @param $userCouponList |用户优惠券列表
     * @return mixedable
     */
    public function getCouponAbleCartList($cartList, $userCouponList)
    {
        $userCouponArray = collection($userCouponList)->toArray();  //用户的优惠券
        $couponNewList = [];
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if ($userCouponArray[$couponKey]['coupon']['use_type'] == 0) { //全店使用优惠券
                if ($cartList['total_fee'] >= $userCouponArray[$couponKey]['coupon']['condition']) {  //订单商品总价是否符合优惠券购买价格
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } elseif ($userCouponArray[$couponKey]['coupon']['use_type'] == 1) { //指定商品优惠券
                $pointGoodsPrice = 0;//指定商品的购买总价
                $couponGoodsId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods_id'], $couponGoodsId)) {
                        $pointGoodsPrice += $Item['member_goods_price'] * $Item['goods_num'];  //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } elseif ($userCouponArray[$couponKey]['coupon']['use_type'] == 2) { //指定商品分类优惠券
                $pointGoodsCatPrice = 0;//指定商品分类的购买总价
                $couponGoodsCatId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_category_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods']['cat_id'], $couponGoodsCatId)) {
                        $pointGoodsCatPrice += $Item['member_goods_price'] * $Item['goods_num']; //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsCatPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } else {
                array_push($couponNewList, $userCouponArray[$couponKey]);
            }
        }
        return $couponNewList;
    }

    private function getApiCoupon($userCoupon)
    {
        $coupon['id'] = $userCoupon['id'];
        $coupon['cid'] = $userCoupon['cid'];
        $coupon['name'] = $userCoupon['coupon']['name'];
        $coupon['money'] = $userCoupon['coupon']['money'];
        $coupon['condition'] = $userCoupon['coupon']['condition'];
        $coupon['use_type_title'] = $userCoupon['coupon']['use_type_title'];
        return $coupon;
    }

    public function getUserCouponNumArr()
    {
        return $this->userCouponNumArr;
    }

    /**
     * 检查购物车数据是否满足库存购买
     * @param $cartList
     * @throws TpshopException
     */
    public function checkStockCartList($cartList)
    {
        foreach ($cartList as $cartKey => $cartVal) {
            if ($cartVal->goods_num > $cartVal->limit_num) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => $cartVal->goods_name . '购买数量不能大于' . $cartVal->limit_num, 'result' => ['limit_num' => $cartVal->limit_num]]);
            }
            if ($cartVal['prom_type'] == 7) {
                $combination_goods_where = ['combination_id' => $cartVal['prom_id'], 'goods_id' => $cartVal['goods_id']];
                if ($cartVal['spec_key'] != '') {
                    $spec_goods_price = Db::name('spec_goods_price')->where(['goods_id' => $cartVal['goods_id'], 'key' => $cartVal['spec_key']])->find();
                    $combination_goods_where['item_id'] = $spec_goods_price['item_id'];
                }
                $combination = Combination::get($cartVal['prom_id']);
                $combination_goods = CombinationGoods::get($combination_goods_where);
                if (empty($combination_goods) || ($combination_goods['price'] != $cartVal['member_goods_price'])) {
                    throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '搭配购' . $combination['title'] . '已变更，请重新加入']);
                }
            }
        }
    }

    /**
     * 清除用户购物车选中
     * @throws \think\Exception
     */
    public function clear()
    {
        Db::name('cart')->where(['user_id' => $this->user_id, 'selected' => 1])->delete();
    }

    /**
     * @param $combination_goods |array 每个item必须goods_id和item_id键
     * @throws TpshopException
     */
    public function addCombinationToCart($combination_goods)
    {
        if (empty($this->combination)) {
            throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => '搭配购套餐不存在']);
        }
        $combination_goods_list = [];
        foreach ($combination_goods as $item) {
            //判断配送地区
            $this->dispatching($item['goods_id'],$item['region_id']);
            $combination_goods_item = CombinationGoods::get(['combination_id' => $this->combination['combination_id'], 'goods_id' => $item['goods_id'], 'item_id' => $item['item_id']]);
            if ($combination_goods_item['is_master'] == 1) {
                array_unshift($combination_goods_list, $combination_goods_item);//主商品插入头部
            } else {
                array_push($combination_goods_list, $combination_goods_item);
            }
        }
        $combination_goods_count = count($combination_goods_list);
        if ($combination_goods_count <= 1) {
            throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => '请选择两件商品']);
        }
        if ($combination_goods_list[0]['is_master'] != 1) {
            throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => '没有选择主商品']);
        }
        $userCartCount = $this->getUserCartGoodsTypeNum();
        if (($userCartCount + $combination_goods_count) > 20) {
            throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => '购物车最多只能放20种商品']);
        }
        $cart_combination_goods_list = [];
        foreach ($combination_goods_list as $combination_goods_item) {
            $cart_goods_where = ['user_id' => $this->user_id, 'goods_id' => $combination_goods_item['goods_id']];
            if (!$this->user_id) {
                $cart_goods_where['session_id'] = $this->session_id;
            }
            $cart_combination_goods_item = [
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $combination_goods_item['goods_id'],   // 商品id
                'goods_sn' => $combination_goods_item['goods']['goods_sn'],   // 商品货号
                'goods_name' => $combination_goods_item['goods_name'],   // 商品名称
                'market_price' => $combination_goods_item['goods']['market_price'],   // 市场价
                'goods_price' => $combination_goods_item['original_price'],
                'member_goods_price' => $combination_goods_item['price'],
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'sku' => $combination_goods_item['goods']['sku'],
                'item_id' => $combination_goods_item['item_id'],
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 7,   //搭配购
                'prom_id' => $this->combination['combination_id'],   //搭配购
                'spec_key' => '',
                'spec_key_name' => '',
            ];
            $store_count = $combination_goods_item['goods']['store_count'];
            if (!empty($combination_goods_item['spec_goods_price'])) {
                $store_count = $combination_goods_item['spec_goods_price']['store_count'];
                $cart_combination_goods_item['spec_key'] = $cart_goods_where['spec_key'] = $combination_goods_item['spec_goods_price']['key'];
                $cart_combination_goods_item['spec_key_name'] = $combination_goods_item['spec_goods_price']['key_name'];
                $cart_combination_goods_item['sku'] = $combination_goods_item['spec_goods_price']['sku'];
            }
            $cart_goods_num_sum = Db::name('cart')->where($cart_goods_where)->sum('goods_num');
            if (($cart_goods_num_sum + $this->goodsBuyNum) > $store_count) {
//                throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => $combination_goods_item['goods_name'] . ' ' . $combination_goods_item['key_name'] . '商品库存不足，剩余' . $store_count]);
                throw new TpshopException('搭配购加入购物车', 0, ['status' => 0, 'msg' => $combination_goods_item['goods_name'] . ' ' . $combination_goods_item['key_name'] . '商品库存不足']);
            }
            array_push($cart_combination_goods_list, $cart_combination_goods_item);
        }

        $master_cart_list = Db::name('cart')->where(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $combination_goods_list[0]['goods_id'], 'spec_key' => ($combination_goods_list[0]['spec_goods_price']['key'] ?: ''),
            'prom_type' => 7, 'prom_id' => $this->combination['combination_id']])->select();
        $cart_combination_goods = [];
        if (!empty($master_cart_list)) {
            $is_insert = 1;
            $Cart = new Cart();

            foreach ($master_cart_list as $master_cart_item) {
//                $combination_goods_master_cart_list = $Cart->where(['combination_group_id' => $master_cart_item['combination_group_id']])->select();
                $combination_goods_master_cart_list = $Cart->where(['combination_group_id' => $master_cart_item['id']])->whereOr(['id' => $master_cart_item['id']])->select();
                $combination_goods_master_cart_list_count = count($combination_goods_master_cart_list);
                if ($combination_goods_master_cart_list_count != $combination_goods_count) {
                    continue;
//                    break;
                }

                $same_num = 0;//初始化个数
                foreach ($combination_goods_master_cart_list as $cart_key => $cart_val) {
                    foreach ($cart_combination_goods_list as $goods_item) {
                        if ($goods_item['goods_id'] == $cart_val['goods_id'] && $goods_item['spec_key'] == $cart_val['spec_key']) {
                            $combination_goods_master_cart_list[$cart_key]['goods_num'] = $combination_goods_master_cart_list[$cart_key]['goods_num'] + $this->goodsBuyNum;
                            $combination_goods_master_cart_list[$cart_key]['goods_price'] = $goods_item['goods_price'];
                            $combination_goods_master_cart_list[$cart_key]['member_goods_price'] = $goods_item['member_goods_price'];
                            $same_num++;
                        }
                    }
                }

                if ($same_num == $combination_goods_count) {
                    $cart_combination_goods = $combination_goods_master_cart_list;
                    $is_insert = 0;
                    break;
                }
            }
        } else {
            $is_insert = 1;
        }
        if ($is_insert) {
            //先插入主商品
            $master_cart = new Cart($cart_combination_goods_list[0]);
            $master_cart->save();
            //更新主商品的combination_group_id
//            $master_cart->save(['combination_group_id' => $master_cart['id']]);
            $master_cart->save(['combination_group_id' => 0]);//xwy-2018-6-4
            array_shift($cart_combination_goods_list);//移除主商品
            foreach ($cart_combination_goods_list as $goods_key => $goods_item) {
                $cart_combination_goods_list[$goods_key]['combination_group_id'] = $master_cart['id'];
            }
            Db::name('cart')->insertAll($cart_combination_goods_list);
        } else {
            foreach ($cart_combination_goods as $cart_key => $cart) {
                $cart->save();
            }
        }

    }

    /**
     * 商品物流配送和运费
     * @param $goods_id
     * @param $region_id
     * @throws TpshopException
     */
    public function dispatching($goods_id, $region_id)
    {
        $Goods = new \app\common\model\Goods();
        $goods = $Goods->cache(true)->where('goods_id',$goods_id)->find();
        $freightLogic = new FreightLogic();
        $freightLogic->setGoodsModel($goods);
        $freightLogic->setRegionId($region_id);
        $freightLogic->setGoodsNum(1);
        $isShipping = $freightLogic->checkShipping();
        if(!$isShipping){
            throw new TpshopException('该地区不支持配送', 0, ['status'=>0,'msg'=>$goods['goods_name'].'该地区不支持配送','result'=>'']);
        }
    }

    /**
     * 找出搭配副商品
     */
    public function getCombination($cartList){
        //查出搭配购的商品
        if($cartList){
            $Cart = new Cart();
            foreach ($cartList as $cartKey => $cartVal) {
                if ($cartVal['prom_type'] == 7) {
                    $arr = $Cart->where(['combination_group_id' => $cartVal['id'], 'id' => ['neq', $cartVal['id']]])->select();
                    $cartList = array_merge($cartList, $arr);
                }
            }
        }
        return $cartList;
    }

    /**
     * 转换成带店铺数据的购物车商品（拼凑成多商家同样的数据格式）
     * @param $cartList|购物车列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getStoreCartList($cartList){
        $storeList = [];
            foreach($cartList as $cartKey => $cartVal)
            {
                    $storeList[0]['cartList'][] = $cartList[$cartKey];
                    $storeList[0]['store_total_price'] += $cartList[$cartKey]['total_fee'];//店铺商品优惠前购买的总价
                    $storeList[0]['store_goods_price'] += $cartList[$cartKey]['goods_fee'];//店铺商品优惠后购买的总价
                    $storeList[0]['store_cut_price'] += $cartList[$cartKey]['cut_fee'];//店铺商品节省的总价
                    $storeList[0]['store_goods_weight'] += $cartList[$cartKey]['goods']['weight'] * $cartList[$cartKey]['goods_num'];//店铺商品的总重量
            }
        return $storeList;
    }

}