<?php
namespace app\admin\validate;
use think\Validate;
class Delivery extends Validate
{
    protected $rule = [
        'name'               => 'require',
        'firstweight'        => 'require',
        'firstprice'         => 'require',
        'secondweight'       => 'require',
        'secondprice'        => 'require',
    ];

    protected $message = [
        'name.require'              => '配送方式名称必须填写',
        'firstweight.require'       => '首重或首件必须填写',
        'firstprice.require'        => '首费必须填写',
        'secondweight.require'      => '续重或续件必须填写',
        'secondprice.require'       => '续费必须填写',
    ];

    protected $scene = [
        'add'     => ['name','firstweight','firstprice','secondweight','secondprice'],
        'edit'    => ['name','firstweight','firstprice','secondweight','secondprice'],
    ];
}
