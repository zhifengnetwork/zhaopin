<?php
namespace app\common\validate;

use think\Validate;

class Config extends Validate
{
    protected $rule = [
        // 验证的时候有主键号码自动避开主键
        'customer_tel|客服联系电话号码'                  =>  'number|max:13',
        'customer_qq|QQ客服联系号'                       => 'number|max:13',
        'customer_wx|客服微信联系号'                      => 'max:25',
        'agc_list|申请代理联系微信号'                     => 'max:25',
        'registered_nickname_mode|注册昵称生成方式'       => 'require|number|in:0,1,2,3',
        'up_nickname_num|修改昵称次数'                    => 'require|number|egt:0',
        'bind_tel_send_gold|绑定手机号赠送金币'            => 'require|number|egt:0',
        'agent_share_url|代理分享地址'                    => 'require',
        'ali_withdraw_percent|支付宝提现税率'             => 'require|float|egt:0|elt:0.1',
        'bank_withdraw_percent|银行提现税率'              => 'require|float|egt:0|elt:0.1',
        'agent_ali_withdraw_percent|代理支付宝提现手续费'  => 'require|float|egt:0|elt:0.1',
        'agent_bank_withdraw_percent|代理银行提现手续费'   => 'require|float|egt:0|elt:0.1',
    ];

    protected $scene = [
        'edit' => ['customer_tel', 'customer_qq', 'customer_wx', 'agc_list', 'registered_nickname_mode', 'up_nickname_num','bind_tel_send_gold',
                   'agent_share_url','ali_withdraw_percent','bank_withdraw_percent','agent_ali_withdraw_percent','agent_bank_withdraw_percent','bank_tx_explain'
        ],
    ];
}
