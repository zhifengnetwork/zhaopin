<?php


namespace app\common\logic;
use app\common\model\WxMaterial;


/**
 * 编辑器类逻辑
 * Class EditorLogic
 * @package app\common\logic
 */
class EditorLogic
{
    /**
     * 水印
     * @param $img_path
     */
    public function waterImage($img_path)
    {
        
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if(strstr(strtolower($img_path),'.gif'))
        {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        
        $image = \think\Image::open($img_path);
        $water = tpCache('water');  //水印配置
        $return_data['mark_type'] = $water['mark_type'];
        if ($water['is_mark'] == 1 && $image->width() > $water['mark_width'] && $image->height() > $water['mark_height']) {
            if ($water['mark_type'] == 'text') {
                $ttf = './hgzb.ttf';
                if (file_exists($ttf)) {
                    $size = $water['mark_txt_size'] ? $water['mark_txt_size'] : 30;
                    $color = $water['mark_txt_color'] ?: '#000000';
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                        $color = '#000000';
                    }
                    $transparency = intval((100 - $water['mark_degree']) * (127 / 100));
                    $color .= dechex($transparency);
                    $water['mark_txt'] = $this->to_unicode($water['mark_txt']);
                    $image->open($img_path)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($img_path);
                    $return_data['mark_txt'] = $water['mark_txt'];
                }
            } else {
                $waterPath = "." . $water['mark_img'];
                $quality = $water['mark_quality'] ? $water['mark_quality'] : 80;
                $waterTempPath = dirname($waterPath) . '/temp_' . basename($waterPath);
                $image->open($waterPath)->save($waterTempPath, null, $quality);
                $image->open($img_path)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($img_path);
                @unlink($waterTempPath);
            }
        }
    }

    /**
     * http://www.dzc.me/2017/07/php%E6%8A%A5imagettfbbox-any2eucjp-invalid-code-in-input-string%E7%9A%84%E4%B8%A4%E4%B8%AA%E8%A7%A3%E5%86%B3%E5%8A%9E%E6%B3%95/
     * 报imagettfbbox(): any2eucjp(): invalid code in input string的两个解决办法
     * @param $string
     * @return string
     */
    function to_unicode($string)
    {
        $str = mb_convert_encoding($string, 'UCS-2', 'UTF-8');
        $arrstr = str_split($str, 2);
        $unistr = '';
        foreach ($arrstr as $n) {
            $dec = hexdec(bin2hex($n));
            $unistr .= '&#' . $dec . ';';
        }
        return $unistr;
    }
    /**
     * 保存上传的图片
     * @param $file
     * @param $save_path
     * @return array
     */
    public function saveUploadImage($file, $save_path)
    {
        $return_url = '';
        $state = "SUCCESS";
        $new_path = $save_path.date('Y').'/'.date('m-d').'/';

        $waterPaths = ['goods/', 'water/']; //哪种路径的图片需要放oss
        if (in_array($save_path, $waterPaths) && tpCache('oss.oss_switch')) {
            //商品图片可选择存放在oss
            $object = UPLOAD_PATH.$new_path.md5(time()).'.'.pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
            $ossClient = new \app\common\logic\OssLogic;
            $return_url = $ossClient->uploadFile($file->getRealPath(), $object);
            $real_path = $file->getRealPath();
            $file = null;//关闭文件句柄，不然无法删除
            @unlink($real_path); //上传后删除
            if (!$return_url) {
                $state = "ERROR" . $ossClient->getError();
                $return_url = '';
            }
        } else {
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->rule(function ($file) {
                return  md5(mt_rand()); // 使用自定义的文件保存规则
            })->move(UPLOAD_PATH.$new_path);
            if (!$info) {
                $state = "ERROR" . $file->getError();
            } else {
                $return_url = '/'.UPLOAD_PATH.$new_path.$info->getSaveName();
                $pos = strripos($return_url,'.');
                $filetype = substr($return_url, $pos);
                if ($save_path =='goods/' && $filetype != '.gif') {  //只有商品图才打水印，GIF格式不打水印
                    $this->waterImage(".".$return_url);  //水印
                }

                $state = $this->uploadWechatImage($save_path, $return_url);
                if ($state != 'SUCCESS') {
                    $info = null;//关闭文件句柄，不然无法删除
                    @unlink('.' . $return_url);
                    $return_url = '';
                }
            }
        }

        return [
            'state' => $state,
            'url'   => $return_url
        ];
    }

    /**
     * 上传微信公众号图片
     * @param $save_path
     * @param $return_url
     * @return string
     */
    private function uploadWechatImage($save_path, $return_url)
    {
        $state = "SUCCESS";

        //微信公众号图片,weixin_mp_image存放永久图片，weixin_mp_news存放文章图片，两者不能共用
        if ($save_path == 'weixin_mp_image/') {
            $wechat = new \app\common\logic\wechat\WechatUtil;
            $data = $wechat->uploadMaterial('.' . $return_url, 'image');
            if ($data === false) {
                $state = $wechat->getError();
            } else {
                WxMaterial::create([
                    'type' => WxMaterial::TYPE_IMAGE,
                    'key'  => md5($return_url),
                    'media_id' => $data['media_id'],
                    'update_time' => time(),
                    'data' => [
                        'url' => $return_url,
                        'mp_url' => $data['url'],
                    ]
                ]);
            }
        } elseif ($save_path == 'weixin_mp_news/') {
            $wechat = new \app\common\logic\wechat\WechatUtil;
            $news_img_url = $wechat->uploadNewsImage('.' . $return_url);
            if ($news_img_url === false) {
                $state = $wechat->getError();
            } else {
                WxMaterial::create([
                    'type' => WxMaterial::TYPE_NEWS_IMAGE,
                    'key' => md5($return_url),
                    'update_time' => time(),
                    'data' => [
                        'url' => $return_url,
                        'mp_url' => $news_img_url,
                    ]
                ]);
            }
        }

        return $state;
    }
}