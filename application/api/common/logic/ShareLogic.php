<?php


namespace app\common\logic;

use think\Db;
use think\Page;
use think\Session;
use think\Cache;

/**
 * 分享逻辑
 */
class ShareLogic
{

  
    /**
     * 获取 ticket
     */
    public function get_ticket($user_id){
       
        $ticket = M('ticket')->where(array('user_id'=>$user_id))->find();
        if(!empty($ticket)){
            
            return  $ticket['ticket'];
            
        }else{
            $access_token = access_token();
            $url="https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$access_token;
            $json = array(
                'action_name'=>"QR_LIMIT_STR_SCENE",
                'action_info'=>array(
                    'scene'=>array(
                        'scene_str'=>$user_id,
                    ),
                ),
            );
            $json = json_encode($json);
            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $out=curl_exec($ch);
            curl_close($ch);
            $out = json_decode($out);
            $newticket = $out->{'ticket'};
            $url = $out->{'url'};
            M('ticket')->save(array('user_id'=>$user_id,'ticket'=>$newticket,'scene_id'=>$user_id,'url'=>$url));
            
            return  $newticket;
        }
        
    }

    
    public function getImage($url,$save_dir='',$filename='',$type=0){

        //本地图片
        if(strpos($url,'public') !== false){ 
            $url = '/www/wwwroot/www.dchqzg1688.com'.$url;
        }else{
            
            //是微信图片
            $end = substr($url,-3);
            if($end == '132'){
                $url = substr($url,0,count($url)-4).'0';
            }

        }



        if(trim($url)==''){
            return array('file_name'=>'','save_path'=>'','error'=>1);
        }
        if(trim($save_dir)==''){
            $save_dir='./';
        }
        if(trim($filename)==''){//保存文件名
            $ext= '.jpg';
            /* strrchr($url,'.');
            if($ext!='.gif'&&$ext!='.jpg'){
                return array('file_name'=>'','save_path'=>'','error'=>3);
            } */
            $filename=time().$ext;
        }
        if(0!==strrpos($save_dir,'/')){
            $save_dir.='/';
        }
        //创建保存目录
        if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){
            return array('file_name'=>'','save_path'=>'','error'=>5);
        }
        //获取远程文件所采用的方法
        if($type){
            $ch=curl_init();
            $timeout=5;
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
            $img=curl_exec($ch);
            curl_close($ch);
        }else{
            ob_start();
            readfile($url);
            $img=ob_get_contents();
            ob_end_clean();
        }
        //$size=strlen($img);
        //文件大小
        $fp2 = @fopen($save_dir.$filename,'a');
     
        if ($fp2) { 

            fwrite($fp2,$img);
            fclose($fp2);
        }

        unset($img,$url);
        return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
    }
}