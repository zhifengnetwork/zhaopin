<?php
namespace app\admin\validate;
use think\Validate;
class Coupon extends Validate
{
    protected $rule = [
        'goods_id'   => 'require',
        'title'        => 'require',
        'price'        => 'require',
        'number'       => 'require',
        'start_time'   => 'require',
        'end_time'     => 'require',
    ];

    protected $message = [
        'goods_id.require'  => '商品名称必须选择',
        'title.require'       => '优惠券标题必须填写',
        'price.require'       => '优惠金额必须填写',
        'number.require'      => '优惠数量必须填写',
        'start_time.require'  => '开始时间必须填写',
        'end_time.require'    => '结束时间必须填写',
    ];

    protected $scene = [
        'add'     => ['goods_id','title','price','number','start_time','end_time'],
        'edit'    => ['goods_id','title','price','number','start_time','end_time'],
    ];
}
