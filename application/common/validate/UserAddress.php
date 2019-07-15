<?php
namespace app\common\validate;

use think\Validate;
use think\Db;

class UserAddress extends Validate
{
    // 验证规则
    protected $rule = [
        'consignee' => 'require|max:60',
        'province' => 'require|gt:0',
        'city' => 'require|gt:0',
        'district' => 'require|gt:0',
        'address' => 'require|max:255',
        'mobile' => 'require|checkMobile',
        'email' => 'email',
        'zipcode'=>'max:6',
    ];
    //错误信息
    protected $message = [
        'consignee.require' => '收货人不能为空',
        'consignee.max' => '收货人长度不得超过60字符',
        'province.require' => '省份必须选择',
        'city.require' => '市必须选择',
        'district.require' => '镇/区必须选择',
        'province.gt' => '请选择省',
        'city.gt' => '请选择市',
        'district.gt' => '请选择镇/区',
        'address.require' => '地址不能为空',
        'address.max' => '地址名称最多不能超过255个字符',
        'mobile.require' => '手机号不能为空',
        'email' => 'email格式错误',
        'zipcode' => '邮编长度为6位',
    ];

    /**
     * 检查活动时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkMobile($value, $rule, $data)
    {
        if(!check_mobile($data['mobile']) && !check_telephone($data['mobile'])){
            return '手机号码格式有误';
        }
        return true;
    }

}