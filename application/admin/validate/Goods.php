<?php
namespace app\admin\validate;
use think\Validate;
class Goods extends Validate
{
    protected $rule = [
        'goods_name'     => 'require',
        'cat_id1'        => 'require',
        'cat_id2'        => 'require',
        'type_id'        => 'require',
    ];

    protected $message = [
        'goods_name.require'    => '商品名称必须填写',
        'cat_id1.require'       => '分类必须选择',
        'cat_id2.require'       => '分类必须选择',
        'type_id.require'       => '类型必须选择',
    ];

    protected $scene = [
        'add'     => ['goods_name','cat_id1'],
        'edit'    => ['goods_name','cat_id1'],
    ];
}
