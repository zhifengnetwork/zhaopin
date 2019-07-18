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
        'work_age' => 'require',
        'salary' => 'require',
        'require_cert' => 'require|in:0,1',
        'detail' => 'require|length:10,200',
    ];

    protected $message = [
        'title.require' => '请填写标题',
        'title.length' => '标题长度2-50',
        'type.require' => '请选择工种',
        'type.number' => '请选择正确的工种',
        'work_age.require' => '请选择工龄',
        'salary.require' => '请选择薪资',
        'require_cert.require' => '请选择要求证书',
        'require_cert.in' => '请选择正确的要求证书',
        'detail.require' => '请填写职位详情',
        'detail.length' => '详情长度10-200',
    ];

    protected $scene = [
        'edit' => ['title', 'type', 'work_age', 'salary', 'require_cert', 'detail'],
    ];

}