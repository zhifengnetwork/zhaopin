<?php
// +----------------------------------------------------------------------
// | Minishop [ Easy to handle for Micro businesses]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.qasl.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: tangtanglove <dai_hang_love@126.com> <http://www.ixiaoquan.com>
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Validate;

class Channel extends Validate
{
    protected $rule = [
        'channel_name|渠道名称'      => 'require',
        'rate|费率'                => 'require|between:0,100',
        'weight|权重'              => 'require|between:0,100',
        'min_price|最小支持金额'       => 'require|gt:0|number',
        'max_price|最大支持金额'       => 'require|egt:min_price|number',
        'day_quota_price|渠道单日限额' => 'require|between:0,1000000|number',
        'interval_time|充值间隔'     => 'require|gt:0|number',
        'state|权重'               => 'require|in:0,1',
    ];

    protected $message = [
        'max_price.gt' => '最大支持金额必须大于最小支持金额',
    ];

    protected $scene = [
        'edit' => ['channel_name', 'rate', 'min_price', 'max_price', 'day_quota_price', 'interval_time', 'state'],

        'add'  => ['channel_name', 'rate', 'min_price', 'max_price', 'day_quota_price', 'interval_time', 'state'],
    ];
}
