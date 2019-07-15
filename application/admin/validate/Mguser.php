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
use think\Input;
use think\Validate;

class Mguser extends Validate
{
    protected $rule = [
        'username'          => 'require|checkHasValue:username|alphaNum',
        'name'              => 'require',
        'email'             => 'checkHasValue:email',
        'mobile'            => 'checkHasValue:mobile',
        'password'          => 'require|length:6,20',
        'repassword'        => 'require|confirm:password',
        'second_password'   => 'require|length:6,20',
        'resecond_password' => 'require|confirm:second_password',
    ];

    protected $message = [
        'name.require'                   => '昵称不能为空',
        'name.checkHasValue:name'         => '昵称已存在',
        'username.require'               => '用户名不能为空',
        'username.checkHasValue:username' => '用户名已存在',
        'username.alphaNum'               => '用户名必须是字母、数字',
        'mobile.checkHasValue:mobile'     => '该手机号已存在',
        'email.checkHasValue:email'       => '邮箱已存在',
        'password.require'                => '请输入密码',
        'password.length'                 => '密码长度为6-20位',
        'repassword.require'              => '请输入确认密码',
        'repassword.confirm'              => '请重新输入确认密码',
        'second_password.require'         => '请输入二级密码',
        'second_password.length'          => '二级密码长度为6-20位',
        'resecond_password.require'       => '请输入确认二级密码',
        'resecond_password.confirm'       => '请重新输入确认二级密码',
    ];

    protected $scene = [
        'edit'     => ['email', 'name', 'mobile'],
        'add'      => ['username', 'name', 'password', 'repassword', 'second_password', 'resecond_password'],
        'editPass' => ['password', 'repassword'],
    ];

    protected function checkHasValue($value, $rule)
    {
        $mgid = input('mgid');

        switch ($rule) {
            case 'email':
                if (empty($mgid)) {
                    $hasValue = Db::table('mg_user')->where('email', $value)->find();
                    if (empty($hasValue)) {
                        return true;
                    } else {
                        return "邮箱已存在！";
                    }
                } else {
                    //更改资料判断邮箱是否与其他人的邮箱相同
                    $checkValue = Db::table('mg_user')
                        ->where('mgid', 'neq', $mgid)
                        ->where('email', $value)
                        ->find();
                    if (empty($checkValue)) {
                        return true;
                    } else {
                        return "邮箱已存在";
                    }
                }
                break;
            case 'mobile':
                if (empty($mgid)) {
                    $hasValue = Db::table('mg_user')->where('mobile', $value)->find();
                    if (empty($hasValue)) {
                        return true;
                    } else {
                        return '手机号已存在';
                    }
                } else {
                    //更改资料判断手机号是否与其他人的手机号相同
                    $checkValue = Db::table('mg_user')
                        ->where('mgid', 'neq', $mgid)
                        ->where('mobile', $value)
                        ->find();
                    if (empty($checkValue)) {
                        return true;
                    } else {
                        return "手机号已存在";
                    }
                }
                break;
            case 'name':
                if (empty($mgid)) {
                    $hasValue = Db::table('mg_user')->where('name', $value)->find();
                    if (empty($hasValue)) {
                        return true;
                    } else {
                        return '昵称已存在';
                    }
                } else {
                    //更改资料判断昵称是否与其他人的昵称相同
                    $checkValue = Db::table('mg_user')
                        ->where('mgid', 'neq', $mgid)
                        ->where('name', $value)
                        ->find();
                    if (empty($checkValue)) {
                        return true;
                    } else {
                        return "昵称已存在";
                    }
                }
                break;
            case 'username':
                $hasValue = Db::table('mg_user')->where('username', $value)->find();
                if (empty($hasValue)) {
                    return true;
                } else {
                    return '用户名已存在';
                }
            default:
                # code...
                break;
        }
    }

}
