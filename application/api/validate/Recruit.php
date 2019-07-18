<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/22
 * Time: 18:14
 */

namespace app\api\validate;

use think\Db;
use think\Validate;

class Recruit extends Validate
{
    protected $rule = [
        'title' => 'require|length:2,50',
        'type' => 'require|number',
        'work_age' => 'require|number',
        'salary' => 'require|number',
        'require_cert' => 'require|in:0,1',
        'detail' => 'length:0,200',
    ];

    protected $message = [
        'title.require' => '请填写标题',
        'title.length' => '标题长度2-50',
        'type.require' => '请选择工种',
        'type.number' => '请选择正确的工种',
        'work_age.require' => '请填写工龄',
        'work_age.number' => '请填写正确的工龄',
        'salary.require' => '请填写薪资',
        'salary.number' => '请填写正确的薪资',
        'require_cert.require' => '请选择要求证书',
        'require_cert.in' => '请选择正确的要求证书',
        'detail.length' => '详情最大长度200',
    ];

    protected $scene = [
        'edit' => ['title', 'type', 'work_age', 'salary', 'require_cert', 'detail'],
    ];

}