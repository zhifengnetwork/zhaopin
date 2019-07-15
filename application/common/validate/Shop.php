<?php
namespace app\common\validate;

use think\Validate;
use think\Db;

class Shop extends Validate
{
    // 验证规则
    protected $rule = [
        'shop_id' => 'require',
        'shop_name' => 'require|max:20|unique:shop',
        'user_name' => 'require|checkUserName',
        'password'=>'require|checkPassword',
        'shopper_name' => 'require|unique:shopper',
        'province_id' => 'require|gt:0',
        'city_id' => 'require|gt:0',
        'district_id' => 'require|gt:0',
        'shop_address' => 'require|max:200',
        'shop_phone' => 'require|checkShopPhone',
        'shop_images' => 'length:1,5',
        'longitude' => 'require|checkLongitude',
        'latitude' => 'require|checkLatitude',
    ];
    protected $scene = [
        'edit' => ['shop_id', 'shop_name', 'province_id', 'city_id', 'district_id', 'shop_address', 'shop_phone', 'shop_images[]','longitude','latitude'],
        'add' => ['shop_name', 'user_name', 'password', 'shopper_name', 'province_id', 'city_id', 'district_id', 'shop_address', 'shop_phone', 'shop_images[]','longitude','latitude'],
    ];
    //错误信息
    protected $message = [
        'shop_id.require' => '参数错误',
        'shop_name.require' => '门店名称必须',
        'shop_name.max' => '门店名称长度不得超过20字符',
        'shop_name.unique' => '门店名称已存在',
        'user_name.require' => '会员账号必须',
        'password.require' => '密码必须',
        'shopper_name.require' => '门店账号必须',
        'shopper_name.unique' => '门店账号已被占用',
        'province_id.require' => '请选择省份',
        'city_id.require' => '请选择城市',
        'district_id.require' => '请选择区域',
        'province_id.gt' => '请选择省份',
        'city_id.gt' => '请选择城市',
        'district_id.gt' => '请选择区域',
        'shop_address.require' => '详细地址必须',
        'shop_address.max' => '详细地址长度不得超过200字符',
        'shop_phone.require' => '联系电话必须',
        'shop_phone.checkShopPhone' => '联系电话格式错误',
        'shop_images[].length' => '门店照片数量1至5张',
        'longitude.require' => '请在地图定位中标记坐标',
        'latitude.require' => '请在地图定位中标记坐标',
    ];

    /**
     * 检查会员账号
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkUserName($value, $rule, $data)
    {
        $isEmail = check_email($value);
        $isMobile = check_mobile($value);
        if ($isEmail == false && $isMobile == false) {
            return '请输入正确的手机或者邮箱';
        }
        if ($isEmail) {
            $where['email'] = $value;
        } else {
            $where['mobile'] = $value;
        }
        $user_id = Db::name('users')->where($where)->getField('user_id');
        if ($user_id) {
            $userShopCount = Db::name('shop')->where(['user_id' => $user_id])->count();
            if ($userShopCount > 0) {
                return '该会员已经拥有门店';
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * 检查门店电话
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkShopPhone($value, $rule, $data)
    {
        if (check_telephone($value) || check_mobile($value)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检查密码
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkPassword($value, $rule, $data)
    {
        $password_len = strlen($value);
        if($password_len < 6 || $password_len > 18){
            return '密码长度必须在6到18之间';
        }
        return true;
    }
    /**
     * 是否定位坐标
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkLongitude($value, $rule, $data)
    {
        if(ceil($data['longitude']) == 0){
            return '请在地图定位中标记坐标';
        }
        return true;
    }
    /**
     * 是否定位坐标
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkLatitude($value, $rule, $data)
    {
        if(ceil($data['latitude']) == 0){
            return '请在地图定位中标记坐标';
        }
        return true;
    }

}