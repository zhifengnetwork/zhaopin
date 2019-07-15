<?php
namespace app\admin\controller;

use app\common\model\OrderRefund as OrderRefundModel;
use app\common\model\Order as OrderModel;
use app\common\model\OrderGoods as OrdeGoodsModel;
use Overtrue\Wechat\Payment\Business;
use Overtrue\Wechat\Payment\QueryRefund;
use Overtrue\Wechat\Payment\Refund;
use \think\Db;
use think\Exception;

class OrderRefund extends Common
{
   /**
     *  退货退款列表
     */
    public function index()
    {
      
        $params_key       = ['shipping_status', 'pay_status',  'kw', 'order_id','good_name'];

        //携带参数
        $where            = $this->get_where($params_key, $param_arr);
        
        $list   = OrderRefundModel::alias('r')->field('*')
                ->join("order uo",'uo.order_id=r.order_id','LEFT')
                ->where($where)
                ->order('r.id DESC')
                ->paginate(2, false, ['query' => $where]);
        // 导出设置
        $param_arr['tpl_type'] = 'export';
        // 模板变量赋值
        $this->assign('list', $list);
        
        $this->assign('param_arr', $param_arr);

        $this->assign('meta_title', '退货退款');
        return $this->fetch();
      
    }

    /**
     * 退款详情
     */
    public function edit(){
        $order_id   = input('order_id','');
        $orderGoodsMdel = new OrdeGoodsModel();
        $orderModel     = new OrderModel();
        $order_info     =  $orderModel->where(['order_id'=>$order_id])->find();
        $orderGoods     =  $orderGoodsMdel::all(['order_id'=>$order_id,'is_send'=>['lt',2]]);
        
         //订单状态
         $this->assign('order_status', config('ORDER_STATUS'));
         //支付方式
         $this->assign('type_list',config('PAY_TYPE'));
        //物流
        // $Api = new Api;
        // $data = M('delivery_doc')->where('order_id', $order_id)->find();
        // $shipping_code = $data['shipping_code'];
        // $invoice_no = $data['invoice_no'];
        // $result = $Api->queryExpress($shipping_code, $invoice_no);
        // if ($result['status'] == 0) {
        //     $result['result'] = $result['result']['list'];
        // }
        // $this->assign('invoice_no', $invoice_no);
        // $this->assign('result', $result);
        $this->assign('orderGoods', $orderGoods);
        $this->assign('order_info', $order_info);
        $this->assign('meta_title', '退款详情');
        return $this->fetch();
    }


    /**
     * 导出订单
     */
    public function export()
    {
    
        try {
             
            $begin_time = strtotime(input('begin_time'));
            $end_time   = strtotime(input('end_time'));
    
            $where['uo.create_time'] = array('BETWEEN', "$begin_time, $end_time");
           //每次导出数据量
           $perNum   = 10000;
           //总数据量
           $totalNum =  Db::table('order')->alias('uo')->where($where)->count();
           //循环取数据次数
           $times    = ceil($totalNum / $perNum);
    
           $fileName = '订单数据详情-' . date('YmdHi', time());
          
            
            header('Content-Type: application/vnd.ms-execl');
            header('Content-Disposition: attachment;filename="' . $fileName . '.csv"');

            //以写入追加的方式打开php标准输出流
            $fp     = fopen('php://output', 'a');

            $title  =  ['订单号','商品名称', '订单金额','最终订单金额', '订单状态','支付状态','支付方式','下单时间','支付时间'];
            $iconvTitle = array_map( function ($v) { return iconv('UTF-8', 'GBK', $v); }, $title);

            fputcsv($fp, $iconvTitle);
          

            //分批写入文件
            for ($i = 1; $i <= $times; $i++) {
                $list = Db::table('order')->alias('uo')
                    ->field('uo.id,uo.good_name,uo.price,uo.end_price,uo.status,uo.pid,uo.type,uo.create_time,uo.pay_time')
                    ->where($where)
                    ->order('uo.id DESC')
                    ->limit(($i - 1) * $perNum, $perNum)
                    ->select();
                   
                //转码写入
                foreach ($list as $key => &$item) {
                    $item['id']               = $item['id'];
                    $item['good_name']        = mb_convert_encoding($item['good_name'], "GBK", "UTF-8");;
                    $item['price']            = $item['price'] ;
                    $item['end_price']        = $item['end_price'] ;
                    $item['status']           = mb_convert_encoding($item['status'], "GBK", "UTF-8");
                    $item['pid']              = $item['pid'];
                    $item['type']             = mb_convert_encoding($item['type'], "GBK", "UTF-8");
                    $item['create_time']      = date('Y-m-d H:i:s', $item['create_time']) . "\t";
                    $item['pay_time']         = date('Y-m-d H:i:s', $item['pay_time']) . "\t";
                    fputcsv($fp, $item);
                }
                //刷新缓冲区
                ob_flush();
            }
        } catch (Exception $e) {
            pft_log('admin/order/explode', json_encode([$where, $e->getMessage()]));
            $this->error('导出订单错误: ' . $e->getMessage());
        }

    }


    /**
     * 获取where条件，一般用于列表或者筛选，整体项目结构统一
     * TODO: 获取的where对应的表前缀可能有点问题，输入变量与筛选字段的区别，...
     * @param  array $params_key  条件key数组
     * @param  array &$params_arr 结果参数数组，便于调用处使用
     * @return array              $where
     */
    private function &get_where($params_key, &$params_arr)
    {
        $begin_time  = input('begin_time', '');
        $end_time    = input('end_time', '');
        $order_id    = input('order_id', '');
        $good_name   = input('good_name', '');
        $status      = input('status/d',0);
        $kw          = input('kw', '');
        $type        = input('type/d', 0);
        $where = [];
        if (!empty($order_id)) {
            $where['uo.order_id']    = $order_id;
        }
        if (!empty($good_name)) {
            $where['uo.good_name']   = $good_name;
        }
        if($status > 0){
            $where['uo.status'] = $status;
        }

        if($type >  0){
            $where['uo.type'] = $type;
        }

        if(!empty($kw)){
            is_numeric($kw)?$where['uo.mobile'] = $kw:$where['uo.user_name'] = $kw;
        }
       

        if ($begin_time && $end_time) {
            $where['uo.create_time'] = [['EGT', $begin_time], ['LT', $end_time]];
        } elseif ($begin_time) {
            $where['uo.create_time'] = ['EGT', $begin_time];
        } elseif ($end_time) {
            $where['uo.create_time'] = ['LT', $end_time];
        }
        $params_arr = array(
            'begin_time'      => $begin_time,
            'end_time'        => $end_time,
        );
        $this->assign('kw', $kw);
        $this->assign('good_name', $good_name);
        $this->assign('type', $type);
        $this->assign('order_id', $order_id);
        $this->assign('begin_time', $begin_time);
        $this->assign('end_time', $end_time);
        $this->assign('status', $status);
        $this->assign('params_arr', $params_arr);
        
        return $where;
    }



    /**
     *  退款功能
     * @return array
     *
     */
    public function refund()
    {
        //请求数据处理
        $orderId       = input('order_id/d', 0);

        $refund_type   = input('refund_type/d', -1);
        $refund_status = input('refund_status/d',-1);
        $handle_remark = input('handle_remark','');
        
        if (!$orderId) {
            $this->error("参数错误");
        }

        if($refund_type < 0){
            $this->error("参数错误");
        }

        if($refund_status < 0){
            $this->error("参数错误");
        }
        //todo 退款操作


        

        // $orderSerice = new OrderService();
        // $refundRes   = $orderSerice->refund($orderId, $this->mginfo['mgid'], $isProd);
        // $code        = $refundRes[0];
        // $msg         = $refundRes[1];

        // //统一添加退款日志
        // //pft_log('order_refund', json_encode([$this->mginfo, $orderId, $refundRes]));

        // //添加微信退款通知
        // if ($isProd) {
        //     $warnMsg = "退款通知：{$this->mginfo['name']}【{$this->mginfo['username']}】将订单【{$orderId}】进行退款，退款结果：{$msg}【{$code}】。";
        //     NoticeService::warningMsg(date('Y-m-d H:i:s'), $warnMsg);
        // }

        // switch ($code) {
        //     case 0:
        //         //退款失败
        //         $this->error($msg);
        //         break;
        //     case 1:
        //         //退款成功
        //         StatisService::addStatisAsynchTask($orderId, StatisService::REFUND_ACTION);
        //         $this->success($msg);
        //         break;
        //     case 2:
        //         //退款超时
        //         $this->error("退款超时，请重新请求");
        //         break;
        //     case 3:
        //         //退款失败
        //         $this->error($msg);
        //         break;
        // }
    }

    /**
     *  查询退款功能
     *  @return array
     *
     */
    public function queryRefund()
    {
        $request['out_trade_no'] = input('order_id', '');
        if ($request['out_trade_no'] == '') {
            $this->error('查询订单号不能为空');
            exit;
        }
        try {
            include_once VENDOR_PATH . 'wechat-2/autoload.php';
            // 获取实例化参数
            $wxConfigArr   = Config::get('wx_config');
            $appId         = $wxConfigArr['appid'];
            $appSecret     = $wxConfigArr['appsecret'];
            $mchId         = $wxConfigArr['mch_id'];
            $mchKey        = $wxConfigArr['mch_key'];
            $apiclientCert = $wxConfigArr['apiclient_cert'];
            $apiclientKey  = $wxConfigArr['apiclient_key'];

            $business = new Business($appId, $appSecret, $mchId, $mchKey);
            $business->setClientCert($apiclientCert);
            $business->setClientKey($apiclientKey);

            $queryRefund                = new QueryRefund($business);
            $queryRefund->out_refund_no = $request['out_trade_no'];
            $queryRefundRes             = $queryRefund->getResponse();
            $msg                        = '';
        } catch (\Exception $e) {
            $msg            = $e->getMessage();
            $queryRefundRes = '';
        }

        if ($msg) {
            $this->error($msg);
        } elseif ($queryRefundRes) {
            // todo
        }
        exit;
    }

      /**
     * 判断登录用户是否有退款的权限
     * @author dwer
     * @date   2017-11-06
     *
     * @return bool
     */
    private function _hasRefundAuth()
    {
      if (TERRACE_ID == 1006) {
          return in_array($this->mginfo['mgid'], Config::get('order_refund_mgid')) ? true : false;
      } else {
          return in_array($this->_loginRole,  ['super_manager', 'manager']) ? true : false;
      }
    }

   
     
   
}
