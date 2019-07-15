<?php
namespace app\api\model;

use app\common\model\Package;
use app\common\model\Version;
use think\Model;

/**
 * 配置模型
 */
class Config extends Model
{
    /**
     * 客户端服务配置
     */
    public static function game_config()
    {
        write_log('cofig.txt');
        $uid         = think_decrypt(input('token', ''));
        $subVer      = input('subVer/d', '');
        $mainVer     = input('mainVer/d', ''); // 主版本号（容器版本）
        $packageName = input('pkgName/s', 'zjsss'); // 包名 客户端定的字段
        $platform    = input('platform/s', 'iOS'); // platform = iOS / Android/ Windows 包名 (暂时没有使用)
        if (!$subVer || !$mainVer) {
            return ['', 1, '数据传输错误'];
        }

        $package = Package::where('name', $packageName)->field('id,hotter_address,android_download_url,ios_download_url,recommend_share_url,home_url')->find();

        if (!$package) {
            $package = Package::where('default', 1)->field('id,hotter_address,android_download_url,ios_download_url,recommend_share_url,home_url')->find();
        }
        $packageId   = $package['id'];
        $versionInfo = Version::where('version', $subVer)
            ->where('package_id', $packageId)
            ->where('is_main', '<>', 1)
            ->field('is_up,show,ios_pay,vip_pay,wc_pay,alipay')
            ->find();
        if ($versionInfo) {
            $versionInfo = $versionInfo->getdata();
        }

        $mainVerInfo = Version::where('is_main', 1)
            ->where('package_id', $packageId)
            ->field('is_up,show,version,ios_pay,vip_pay,wc_pay,alipay')
            ->find()->getdata();

        // 判断是否有子版本
        if ($versionInfo) {
            $is_up = (int) $mainVer < (int) $mainVerInfo['version'] ? 2 : $versionInfo['is_up']; // 冷更新判断

            $show = $versionInfo['show'];

        } else {
            $is_up = (int) $mainVer < (int) $mainVerInfo['version'] ? 2 : $mainVerInfo['is_up'];
            $show  = $mainVerInfo['show'];
        }

        $data = [
            'show'                 => $show,
            'is_up'                => $is_up,
            'gs_host'              => config('gs_host'),
            'gs_port'              => config('gs_port'),
            'ios_pay_url'          => config('ios_pay_url'),

            'agc_list'             => explode(',', config('agc_list')),
            'customer_wx'          => config('customer_wx'),
            'customer_qq'          => config('customer_qq'),
            'customer_tel'         => config('customer_tel'),
            'tx_record_url'        => config('tx_record_url'),
            'ali_tx_explain'       => config('ali_tx_explain'),
            'bank_tx_explain'      => config('bank_tx_explain'),
            'up_nickname_num'      => config('up_nickname_num'),
            'bind_tel_send_gold'   => config('bind_tel_send_gold'),
            'is_open_browser'      => (int)config('is_open_browser'),

            'home_url'             => $package['home_url'],
            'hotter_address'       => $package['hotter_address'], // 待删除
            'hu_url'               => $package['hotter_address'],
            'ios_download_url'     => $package['ios_download_url'],
            'recommend_share_url'  => $package['recommend_share_url'],
            'android_download_url' => $package['android_download_url'],
        ];

        /*if (request()->ip() == '110.80.152.33') {
        if ($subVer == 10) {
        $data['is_up'] = 1;
        }
        }*/
        return [$data, 0, ''];
    }
}
