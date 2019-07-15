<?php
namespace app\admin\controller;

use app\common\model\Order as OrderModel;
use app\common\model\OrderGoods as OrdeGoodsModel;
use app\common\model\OrderRefund;
use Overtrue\Wechat\Payment\Business;
use Overtrue\Wechat\Payment\QueryRefund;
use Overtrue\Wechat\Payment\Refund;
use think\Request;
use \think\Db;
use think\Exception;

//物流api
use app\home\controller\Api;

class Order extends Common
{
   /**
     * 订单列表
     */
    public function index()
    {
        $begin_time        = input('begin_time', '');
        $end_time          = input('end_time', '');
        $order_id          = input('order_id', '');
        $invoice_no        = input('invoice_no', '');
        $orderstatus       = input('orderstatus',-1);
        $kw                = input('kw', '');
        $paycode           = input('paycode', -1);
        $paystatus         = input('paystatus',-1);
        $where = [];
        if (!empty($order_id)) {
            $where['uo.order_id']    = $order_id;
        }
        if (!empty($invoice_no)) {
            $where['d.invoice_no']   = $invoice_no;
        }
        if($orderstatus >= 0){
            $where['uo.order_status'] = $orderstatus;
        }
        if($paycode >= 0){
            $where['uo.pay_code'] = $paycode;
        }
        if($paystatus >= 0){
            $where['uo.pay_status'] = $paystatus;
        }
        if(!empty($kw)){
            is_numeric($kw)?$where['uo.mobile'] = $kw:$where['a.realname'] = $kw;
        }
         // 携带参数
        $carryParameter = [
            'kw'               => $kw,
            'begin_time'       => $begin_time,
            'end_time'         => $end_time,
            'invoice_no'       => $invoice_no,
            'orderstatus'      => $orderstatus,
            'paycode'          => $paycode,
            'paystatus'        => $paystatus,
        ];
        $list  = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d",'uo.order_id=d.order_id','LEFT')
                ->join("member a",'a.id=uo.user_id','LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->paginate(10, false, ['query' => $carryParameter]);
        
        // 模板变量赋值
        //订单状态
        $order_status     = config('ORDER_STATUS');
        $order_status['-1'] = '默认全部';
        //支付方式
        $pay_type         = config('PAY_TYPE');
        $pay_type['-1']     = '默认全部';
        //支付状态
        $pay_status         = config('PAY_STATUS');
        $pay_status['-1']     = '默认全部';

        // 导出
        $exportParam            = $carryParameter;
        $exportParam['tplType'] = 'export';
        $tplType                = input('tplType', '');
        if ($tplType == 'export') {
            $list  = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d",'uo.order_id=d.order_id','LEFT')
                ->join("member a",'a.id=uo.user_id','LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->select();
            $str = "订单ID,用户id,订单金额\n";

            foreach ($list as $key => $val) {
                $str .= $val['order_id'] . ',' . $val['user_id'] . ',' . $val['order_amount'] . ',';
                $str .= "\n";
            }
            export_to_csv($str, '订单列表', $exportParam);
        }
     
        return $this->fetch('',[ 
            'list'         => $list,
            'exportParam'  => $exportParam,
            'order_status' => $order_status,
            'pay_type'     => $pay_type,
            'pay_status'   => $pay_status,
            'kw'           => $kw,
            'invoice_no'   => $invoice_no,
            'paystatus'    => $paystatus,
            'orderstatus'  => $orderstatus,
            'paycode'      => $paycode,
            'order_id'     => $order_id,
            'begin_time'   => empty($begin_time)?date('Y-m-d'):$begin_time,
            'end_time'     => empty($end_time)?date('Y-m-d'):$end_time,
            'meta_title'   => '订单列表',
        ]);
      
    }
    
    /**
     * 订单详情
     */
    public function edit(){
        $order_id       =  input('order_id','');
        $orderGoodsMdel =  new OrdeGoodsModel();
        $orderModel     =  new OrderModel();
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
        $this->assign('meta_title', '订单详情');
        return $this->fetch();
    }

    /***
     * 退换货列表
     */
    public function order_refund(){
        $refundstatus  = input('refundstatus',-1);
        $order_id       = input('order_id','');
        $where = array();
        if(!empty($order_id)){
            $where['order_sn']       = $order_id;
        }

        if($refundstatus >= 0){
            $where['uo.refund_status']   = $refundstatus;
        }

        $list  = Db::name('order_refund')->alias('uo')->field('uo.*,order_sn,order_amount')
                ->join("order d",'uo.order_id=d.order_id','LEFT')
                ->where($where)
                ->order('uo.id DESC')
                ->paginate(10, false, ['query' => [
                    'refundstatus' => $refundstatus,
                    'order_id'      => $order_id,
                ]]);
        //退换货状态
       
        $refund_status           = config('REFUND_STATUS');
        $refund_status['-1']     = '默认全部';
        //退货原因
        $refund_reason           = config('REFUND_REASON');
        return $this->fetch('',[
            'meta_title'    => '退换货列表', 
            'list'          => $list, 
            'refund_reason' => $refund_reason,
            'refund_status' => $refund_status,
            'order_id'      => $order_id,
            'refundstatus'  => $refundstatus,
        ]);
    }
    /**
     *退换货详情
     */
    public function refund_edit(){
        $id    = input('id');
        $info  = Db::name('order_refund')->alias('uo')->field('uo.*,order_sn,order_amount,realname')
                ->join("order d",'uo.order_id=d.order_id','LEFT')
                ->join("member m",'uo.user_id=m.id','LEFT')
                ->where(['uo.id' => $id])
                ->find();
        if( Request::instance()->isPost()){

            $refund_status = input('refund_status/d',0);
            $handle_remark = input('handle_remark','');
            $update = [
                'end_time'        => time(),
                'handle_remark'   => $handle_remark,
                'refund_status'   => $refund_status,
            ]; 
            if($refund_status == 2){
                //todo::调用退款程序 
               $relut = OrderRefund::refund_obj($info);
               


            }
            $res = Db::name('order_refund')->where(['id' => $id])->update($update);

            if($res !== false){
                $this->success('审核成功', url('order/refund_edit',['id' => $id]));
            }
            $this->error('审核失败');


        }
        $img = empty($info['img'])?'': explode(",", $info['img']);
        return $this->fetch('',[
            'img'           => $img,
            'meta_title'    => '退换货详情', 
            'info'          => $info, 
            'refund_reason' => config('REFUND_REASON'), //退货原因
            'refund_status' => config('REFUND_STATUS'),//退换货状态
        ]);

    }
    /***
     * 发货单列表
     */
    public function  delivery_list(){

        
        $shipping_status = input('shipping_status',-1);
        $consignee       = input('consignee','');
        $order_sn        = input('order_sn','');

        $where = array();

        if(!empty($consignee)){
            $where['uo.consignee']  = $consignee;
        }

        if(!empty($order_sn)){
            $where['uo.order_sn']   = $order_sn;
        }

        if($shipping_status >= 0){
            $where['uo.shipping_status']   = $shipping_status;
        }

        $where['uo.pay_status']   = 1;

        // $where['uo.pay_status']   = 1;

        // $where['uo.order_status'] = array('in','0,1,2,4');

        $list  = OrderModel::alias('uo')->field('uo.*')
                ->order('uo.order_id DESC')
                ->where($where)
                ->paginate(10, false, ['query' => [
                    'shipping_status' => $shipping_status,
                    'consignee'       => $consignee,
                    'order_sn'        => $order_sn
                ]]);
        return $this->fetch('',[
            'meta_title'  => '发货单列表', 
            'list'        => $list,
            'consignee'   => $consignee,
            'order_sn'    => $order_sn
        ]);
        

    }
    
    /***
     *发货单编辑
     */
    public function delivery_info($id=''){
        if($id){
            $order_id   = $id; 
        }else{
            $ids        = input('order_id','');
            $order_id   = trim($ids,',');
        }
    	$orderGoodsMdel =  new OrdeGoodsModel();
        $orderModel     =  new OrderModel();
        $orderObj       =  $orderModel->where(['order_id'=>$order_id])->find();
      
        $order          =  $orderObj->append(['full_address'])->toArray();
        $orderGoods     =  $orderGoodsMdel::all(['order_id'=>$order_id,'is_send'=>['lt',2]]);
       
        
        if($id){
            if(!$orderGoods){
                $this->error('所选订单有商品已完成退货或换货');//已经完成售后的不能再发货
            }
        }else{
            if(!$orderGoods){
                $this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货  
            }
        }
        

        $delivery_record = Db::name('delivery_doc')->alias('d')->where('d.order_id='.$order_id)->select();
        if(!empty($delivery_record)){
            $order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
        }else{
            $order['invoice_no'] = '';
        }
        $this->assign('order',$order);
        $this->assign('orderGoods',$orderGoods);
        $this->assign('delivery_record',$delivery_record);//发货记录
        $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();
        $this->assign('shipping_list',$shipping_list);
        $this->assign('express_switch',0);
        $this->assign('meta_title','发货单编辑');
        return $this->fetch();    
    }


        /**
        *批量发货
        */
      public function delivery_batch(){
            $order_id  = input('order_id','');
            $order_id  = trim($order_id,',');
        
            $orderGoodsMdel = new OrdeGoodsModel();
            $orderModel     = new OrderModel();
            $orderObj       = $orderModel->whereIn('order_id',$order_id)->select();//订单
            $orderGoods     = $orderGoodsMdel::all(['order_id'=>['in',$order_id],'is_send'=>['lt',2]]);
            //订单商品
            
            if ($orderObj){
                $order = collection($orderObj)->append(['orderGoods','full_address'])->toArray();
            }
            if (!$orderGoods){
                $this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货  
            }
            print_r($order);
            die;
            $this->assign('order',$order);
            $this->assign('orderGoods',$orderGoods);
            $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();
            $this->assign('shipping_list',$shipping_list);
            $this->assign('express_switch',0);
            $this->assign('order_ids',$order_id);
            return $this->fetch();    
        
    }


    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $data  = input('post.');
      
       
        if($data['send_type'] == 0 && isset($data['invoice_no']) && empty($data['invoice_no'])){
            $this->error('请输入配送单号');
        }

        if($data['send_type'] == 0 && isset($data['invoice_no']) && empty($data['invoice_no'])){
            $this->error('请输入配送单号');
        }

        if($data['send_type'] != 3 && isset($data['shipping_code']) && empty($data['shipping_code'])){
            $this->error('请选择物流');
        }
       
        if(!isset($data['goods']) || $data['shipping']  != 1 && count($data['goods']) < 1) {
            $this->error('请选择发货商品');
        }
       
        $count = 0;
        if(isset($data['pldelivery'])){
            foreach($data['order_id'] as $k => $v){
                $count++;
                $datas['shipping']      = $data['shipping'][$v];
                $datas['shipping_code'] = $data['shipping_code'][$v];
                $datas['send_type']     = $data['send_type'][$v];
                $datas['invoice_no']    = $data['invoice_no'][$v];
                $datas['order_id']      = $v;
                $datas['note']          = $data['note'][$v];
                $datas['goods']         = $data['goods'][$v];
                if(!empty($data['shipping_name'][$v])){
                    $datas['shipping_name'] = $data['shipping_name'][$v];
                }
                if(!empty($data['shipping_code'][$v])){
                    $datas['shipping_code'] = $data['shipping_code'][$v];
                }
                if(!empty($data['invoice_no'][$v])){
                    $datas['invoice_no'] = $data['invoice_no'][$v];
                }
                $res =(new OrderModel())->deliveryHandle($datas);
                if($count == count($data['order_id'])){
                    break;
                }
             }
             if($res['status'] == 1 && $count == count($data['order_id'])){
                $this->success('操作成功',url('order/delivery_list'));
             }else{
                $this->error($res['msg']);
             }
        }else{
             $res = (new OrderModel())->deliveryHandle($data);
             if($res['status'] == 1){
                  $this->success('操作成功',url('order/delivery_list'));
            }else{
                $this->success($res['msg']);
            }
        }
		
    }






    /***
     * 发货单信息管理
     */
    public function senduser(){
        $where      = array();
        $list       = Db::table('exhelper_senduser')->field('*')
        
                    ->where($where)
                    ->order('id')
                    ->paginate(10, false, ['query' => $where]);
        $this->assign('list', $list);
        $this->assign('meta_title', '发货单信息管理');
        return $this->fetch();
    }




    /***
     * 发货单打印
     */
    public function doprint(){
        $this->assign('meta_title', '发货单打印');
        return $this->fetch();
    }

    /***
     * 快递单和发货单模板管理
     */
    public function express(){
        $this->assign('meta_title', '模板管理');
        return $this->fetch();
    }

    /***
     * 打印设置
     */
    public function printset(){
        $printset = Db::table('exhelper_sys')->find();
        $this->assign('printset',$printset);
        $this->assign('meta_title', '打印设置');
        return $this->fetch();
    }

    /**
     * 获取where条件，一般用于列表或者筛选，整体项目结构统一
     * TODO: 获取的where对应的表前缀可能有点问题，输入变量与筛选字段的区别，...
     * @param  array $params_key  条件key数组
     * @param  array &$params_arr 结果参数数组，便于调用处使用
     * @return array              $where
     */
    private function &get_where()
    {
        $begin_time  = input('begin_time', '');
        $end_time    = input('end_time', '');
        $order_id    = input('order_id', '');
        $invoice_no  = input('invoice_no', '');
        $status      = input('order_status','');
        $kw                = input('kw', '');
        $pay_code          = input('pay_code', '');
        $pay_status        = input('pay_status', '');
        $where = [];
        if (!empty($order_id)) {
            $where['uo.order_id']    = $order_id;
        }
        if (!empty($invoice_no)) {
            $where['d.invoice_no']   = $invoice_no;
        }
        if(!empty($status)){
            $where['uo.order_status'] = $status;
        }
        if(!empty($pay_code)){
            $where['uo.pay_code'] = $pay_code;
        }
        if(!empty($pay_status)){
            $where['uo.pay_status'] = $pay_status;
        }
        // var_dump($where);
        // die;
        if(!empty($kw)){
            is_numeric($kw)?$where['uo.mobile'] = $kw:$where['a.realname'] = $kw;
        }
       

        // if ($begin_time && $end_time) {
        //     $where['uo.create_time'] = [['EGT', $begin_time], ['LT', $end_time]];
        // } elseif ($begin_time) {
        //     $where['uo.create_time'] = ['EGT', $begin_time];
        // } elseif ($end_time) {
        //     $where['uo.create_time'] = ['LT', $end_time];
        // }
        // $params_arr = array(
        //     'begin_time'      => $begin_time,
        //     'end_time'        => $end_time,
        // );
        $this->assign('kw', $kw);
        $this->assign('invoice_no', $invoice_no);
        $this->assign('status', $status);
        $this->assign('pay_status', $pay_status);
        $this->assign('pay_code', $pay_code);
        $this->assign('order_id', $order_id);
        $this->assign('begin_time', empty($begin_time)?date('Y-m-d'):$begin_time);
        $this->assign('end_time', empty($end_time)?date('Y-m-d'):$end_time);
        // $this->assign('params_arr', $params_arr);
        
        return $where;
    }

   
}
