<?php
// +----------------------------------------------------------------------
// | Minishop [ Easy to handle for Micro businesses]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.qasl.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: tangtanglove <dai_hang_love@126.com> <http://www.ixiaoquan.com>
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Db;
use think\Validate;

class Auths extends Validate
{
    // 验证规则
    protected $rule = [
        'group_name' => 'require|checkName:name',
    ];

    // 错误提示消息
    protected $message = [
        'group_name.require' => '用户组不能为空',
    ];

    // 自定义验证规则
    protected function checkName($value)
    {
        $info = Db::table('UserGroup')->where('title', $value)->find();
        if ($info) {
            return '用户组不能重复';
        }
        return true;
    }
}
