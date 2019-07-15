<?php
use think\Db;
use think\Request;
use think\Session;
// 加载wechat微信处理类库
require VENDOR_PATH . 'wechat-2/autoload.php';

function slog($id='',$title=''){

    $ca = Request::instance()->controller() . '/' . Request::instance()->action();

    $all_menu = Session::get('all_menu');
    if($all_menu){
        $data['uid'] = Session::get('admin_user_auth.mgid');
        $data['type'] = $ca;
        $data['createtime'] = time();
        $data['ip'] = Request::instance()->ip();
        foreach($all_menu as $key=>$value){
            if( strtolower($value['url']) == strtolower($ca) ){
                if($title){
                    $pos = strpos($value['title'],"添加");
                    $value['title'] = substr_replace($value['title'], '修改', $pos, 6);
                }
                $data['name'] = $value['title'];
                $data['op'] = $value['title'] . ' ID:' . $id;
                if($value['pid']){
                    foreach($all_menu as $k=>$v ){
                        if( $v['id'] == $value['pid'] ){
                            $data['name'] = $v['title'] . '-' . $value['title'];
                            break;
                        }
                    }
                }
                break;
            }
        }
        if(isset($data['name'])){
            Db::table('admin_log')->insert($data);
        }
    }
}


/**
 * @param $arr
 * @param $key_name
 * @return array
 * 将数据库中查出的列表以指定的 id 作为数组的键名 
 */
function convert_arr_key($arr, $key_name)
{
	$arr2 = array();
	foreach($arr as $key => $val){
		$arr2[$val[$key_name]] = $val;        
	}
	return $arr2;
}
function array_allow_keys($array, $keys)
{
    $newArr = [];
    foreach ($keys as $key) {
        if (isset($array[$key])) {
            $newArr[$key] = $array[$key];
        }
    }
    return $newArr;
}

/**
 * 获取数组中的某一列
 * @param array $arr 数组
 * @param string $key_name  列名
 * @return array  返回那一列的数组
 */
function get_arr_column($arr, $key_name)
{
	$arr2 = array();
	foreach($arr as $key => $val){
		$arr2[] = $val[$key_name];        
	}
	return $arr2;
}

function jason($data=array(),$msg="ok",$code=1){
    $result=array(  
      'code'=>$code,  
      'msg'=>$msg, 
      'data'=>$data   
    );  
    //输出json  
    echo json_encode($result);  
    exit;  
} 
function get_agent_log($user_id){

	
	$agent_money = Db::table('agent_performance')->where(['user_id'=>$user_id])->find();
	
	if(empty($agent_money)){
		$money = 0;
	}else{
		$money = $agent_money['ind_per']+$agent_money['agent_per'];
	}

	return $money;
}

/***
 * 获取一个或者多个openid 的用户id
 */

function mc_openid2uid($openid) {

	if (is_numeric($openid)) {
		return $openid;
    }
    
	if (is_string($openid)) {
        $uid = Db::table('user')->where(['openid' => $openid])->value('uid');
		return $uid;
	}
	if (is_array($openid)) {
		$uids = array();
		foreach ($openid as $k => $v) {
			if (is_numeric($v)) {
				$uids[] = $v;
			} elseif (is_string($v)) {
				$fans[] = $v;
			}
		}
		if (!empty($fans)) {
            $fans = Db::table('user')->where(['openid'=>['in',$fans]])->select(); 
			$fans = array_keys($fans);
			$uids = array_merge((array)$uids, $fans);
		}
		return $uids;
	}
	return false;
}


function getSetData($_var_6 = 0)
	{
		global $_W;
		if (empty($_var_6)) {
			$_var_6 = $_W['uniacid'];
		}
		$_var_7 = m('cache')->getArray('sysset', $_var_6);
		if (empty($_var_7)) {
			$_var_7 = pdo_fetch('select * from ' . tablename('sz_yi_sysset') . ' where uniacid=:uniacid limit 1', array(':uniacid' => $_var_6));
			if (empty($_var_7)) {
				$_var_7 = array();
			}
			m('cache')->set('sysset', $_var_7, $_var_6);
		}
		return $_var_7;
	}

function getSysset($_var_8 = '', $_var_6 = 0)
	{
		
		global $_W, $_GPC;
		
		$_var_7 = getSetData($_var_6);
		$_var_9 = unserialize($_var_7['sets']);
		$_var_10 = array();
		if (!empty($_var_8)) {
			if (is_array($_var_8)) {
				foreach ($_var_8 as $_var_11) {
					$_var_10[$_var_11] = isset($_var_9[$_var_11]) ? $_var_9[$_var_11] : array();
				}
			} else {
				$_var_10 = isset($_var_9[$_var_8]) ? $_var_9[$_var_8] : array();
			}
			return $_var_10;
		} else {
			return $_var_9;
		}
	}

function get_agent_user($first_leader){

	$first_leader_user = Db::table('users')->where(['user_id'=>$first_leader])->find();
	if(empty($first_leader_user)){
		$name = "无";
	}else{
		if($first_leader_user['nickname'] == null)
		{
			$name = $first_leader_user['mobile'];
		}else{
			$name = $first_leader_user['nickname'];
		}
	}

	return $name;
}
/*
 * 获取商品的第一分类的下拉列表
 */
function get_wxmenu_html($selid = 0)
{
    static $catearr;
    if (empty($catearr)) {
        $catearr = \think\Db::table('wx_menu')->where(array('pid' => 0, 'state' => 0))->column('title', 'id');
    }

    $list = '<option value="0">顶级菜单</option>';
    if ($catearr) {
        foreach ($catearr as $key => $val) {
            $list .= '<option value="' . $key . '" ' . ($key == $selid ? 'selected' : '') . '>' . $val . '</option>';
        }
    }
    return $list;
}

function getTree($array, $pid =0, $level = 0){
    //声明静态数组,避免递归调用时,多次声明导致数组覆盖
    static $list = [];
    foreach ($array as $key => $value){
        //第一次遍历,找到父节点为根节点的节点 也就是pid=0的节点
        if ($value['pid'] == $pid){
            //父节点为根节点的节点,级别为0，也就是第一级
            $value['level'] = $level;
            //把数组放到list中
            $list[] = $value;
            //把这个节点从数组中移除,减少后续递归消耗
            unset($array[$key]);
            //开始递归,查找父ID为该节点ID的节点,级别则为原级别+1
           getTree($array, $value['cat_id'], $level+1);
        }
    }
    return $list;
}

function setSukMore($goods_id=18, $data_spec)
{   
    $is_limited = Db::table('goods')->where("FIND_IN_SET(6,goods_attr)")->where('goods_id',$goods_id)->value('goods_id');
    if($is_limited){
        $redis = new app\common\util\Redis(config('cache.redis'));
    }

    $all_spec = Db::name('goods_spec')->column('spec_name', 'spec_id');
    foreach ($data_spec as $key => $val) {
        $sku_data = array();
        $sku_attr = '';
        $map = [];
        foreach ($val as $k => $v) {
            if ($v['key'] != '库存' && $v['key'] != 'pri' && $v['key'] != 'group_pri' && $v['key'] != 'img') {
                $goods_spec_data = array();
                $spec_id = array_keys($all_spec, $v['key'])[0];
                $goods_spec_data['spec_id'] = $spec_id;
                $goods_spec_data['attr_name'] = $v['value'];
                $goods_spec_data['goods_id'] = $goods_id;
                // 判断此商品的spec_attr是否存在，不存在才添加（去重操作）
                $map['goods_id'] = $goods_id;
                $map['attr_name'] = $v['value'];
                $find_data = Db::name('goods_spec_attr')->where($map)->find();
                if ($find_data === null) {
                    $attr_id = Db::name('goods_spec_attr')->insertGetId($goods_spec_data);
                } else {
                    $attr_id = $find_data['attr_id'];
                }
                $sku_attr .= "$spec_id:$attr_id,";
            } else if ($v['key'] == '库存') {
                $sku_data['inventory'] = $v['value'];
            } else if ($v['key'] == 'pri') {
                $sku_data['price'] = $v['value'];
            } else if ($v['key'] == 'group_pri') {
                $sku_data['groupon_price'] = $v['value'];
            } else if ($v['key'] == 'img') {
                $sku_data['img'] = $v['value'];
            }
        }

        $sku_attr = trim($sku_attr, ',');
        $sku_data['sku_attr'] = '{' . $sku_attr . '}';
        $sku_data['goods_id'] = $goods_id;
        $sku_data['sales'] = 0;
        $sku_data['virtual_sales'] = rand(223, 576);
        $sku_id = Db::table('goods_sku')->insertGetId($sku_data);
        if (!$sku_id) {
            return 0;
        }else{
            if($is_limited){
                for($i=0;$i<$sku_data['inventory'];$i++){
                    $redis->rpush("GOODS_LIMITED_{$sku_id}",1);
                }
            }
        }
    }
    return 1;
}

function setSukMore2($goods_id, $data_spec)
{
    $all_spec = Db::name('goods_spec')->column('spec_name', 'spec_id');
    $sku_all_id = Db::name('goods_sku')->where('goods_id', $goods_id)->column('sku_id');// 旧的
    $spec_attr_all = Db::name('goods_spec_attr')->where('goods_id', $goods_id)->column('attr_name', 'attr_id');
    foreach ($data_spec as $key => $val) {
        $sku_data = array();
        $sku_attr = '';
        $map = [];
        foreach ($val as $k => $v) {
            if ($k !== 'sku_id') {
                if ($v['key'] != '库存' && $v['key'] != 'pri' && $v['key'] != 'group_pri' && $v['key'] != 'img') {
                    $goods_spec_data = array();
                    $spec_id = array_keys($all_spec, $v['key'])[0];
                    $goods_spec_data['spec_id'] = $spec_id;
                    $goods_spec_data['attr_name'] = $v['value'];
                    $goods_spec_data['goods_id'] = $goods_id;
                    // 判断此商品的spec_attr是否存在，不存在才添加（去重操作）
                    $map['goods_id'] = $goods_id;
                    $map['attr_name'] = $v['value'];
                    $find_data = Db::name('goods_spec_attr')->where($map)->find();
                    if ($find_data === null) {
                        $attr_id = Db::name('goods_spec_attr')->insertGetId($goods_spec_data);
                    } else {
                        $attr_id = $find_data['attr_id'];
                    }
                    $new_spec_all[] = $attr_id;
                    $sku_attr .= "$spec_id:$attr_id,";
                } else if ($v['key'] == '库存') {
                    $sku_data['inventory'] = $v['value'];
                } else if ($v['key'] == 'pri') {
                    $sku_data['price'] = $v['value'];
                }else if ($v['key'] == 'group_pri') {
                    $sku_data['groupon_price'] = $v['value'];
                } else if ($v['key'] == 'img') {
                    $sku_data['img'] = $v['value'];
                }
            } else {
                $sku_data['sku_id'] = $v;
            }
        }
        $sku_attr = trim($sku_attr, ',');
        $sku_data['sku_attr'] = '{' . $sku_attr . '}';
        $sku_data['goods_id'] = $goods_id;
        $sku_data['sales'] = 0;
        $new_skuid_all[] = $val['sku_id'];
        if (!in_array($sku_data['sku_id'], $sku_all_id) && $sku_data['sku_id'] == '') {
            $sku_data['virtual_sales'] = rand(223, 576);
            $res2 = Db::name('goods_sku')->insertGetId($sku_data);
        } else {
            $res2 = Db::name('goods_sku')->where('sku_id', $sku_data['sku_id'])->update($sku_data);
        }

        if ($res2 === false) {
            return 0;
        }
    }
    // 删除多余的spec_attr
    foreach ($spec_attr_all as $key => $val) {
        if (!in_array($key, $new_spec_all)) {
            $res2 = Db::name('goods_spec_attr')->where('attr_id', $key)->delete();
            if (!$res2) {
                return 0;
            }
        }
    }
    // 删除多余的sku
    foreach ($sku_all_id as $key => $val) {
        if (!in_array($val, $new_skuid_all)) {
            $res2 = Db::name('goods_sku')->where('sku_id', $val)->delete();
            if (!$res2) {
                return 0;
            }
        }
    }
    return 1;
}
/**
 * 粉丝分组
 */
function get_fans_group()
{
    $arr = \think\Db::table('user_group')->column('*', 'wechatgroupid');
    return $arr;
}
/*
 * 获取微信分组下拉html
 */
function get_fans_group_html($g_id = -1)
{
    static $groups;
    if (empty($groups)) {
        $groups = get_fans_group();
    }

    foreach ($groups as $key => $val) {

        $list .= '<option value="' . $key . '"' . ($key == $g_id ? 'selected' : '') . '>' . $val['name'] . '</option>';
    }
    return $list;
}

function group_text($id)
{
    static $groups;
    if (empty($groups)) {
        $groups = get_fans_group();
    }

    return empty($groups[$id]) ? '' : $groups[$id]['name'];
}

function sex_text($id)
{
    $arr = array('0' => '未设置', '1' => '男', '2' => '女');

    return $arr[$id];
}

/**
 * 获取当前用户权限，控制菜单对某个用户是否显示
 */
function get_menu_auth()
{
    //超级管理员，直接返回
    if (UID == IS_ROOT) {
        return 1;
    }
    //获取当前登录用户所在的用户组(可以是多组)
    $groups = Db::table('auth_group_access')->where('mgid', UID)->column('group_id');
    if (!$groups) {
        return 2; //没有任何权限
    }
    //所有权限数组
    $rules_array = [];
    $arr         = [];
    foreach ($groups as $v) {
        $rules = Db::table('auth_group')->where('id', $v)->where('status', 1)->value('rules');
        if ($rules) {
            $arr = explode(',', $rules);
        }
        $rules_array = array_merge($rules_array, $arr);
    }
    //去除重复
    $rules_array = array_unique($rules_array);
    return $rules_array;
}

/**
 * 权限判断，设置菜单对某个用户是否可见
 * @param  [type] $rule_id      [当前菜单id]
 * @param  [type] $rules       [权限id组]
 */
function check_menu_auth($rule_id, $rules)
{
    //权限判断
    if (is_array($rules)) {
        if (!in_array($rule_id, $rules)) {
            return false;
        }
        return true;
    } else {
        if ($rules == 1) {
            //超级管理员，拥有所有权�?
            return true;
        }
        return false;
    }
}

/**
 * 获取牌局的对应图片
 */
function get_play_pic($str, $gid, $flg = 0)
{
    if ($gid == 1) {
        $url = '/static/images/cards/';

    } else if ($gid == 5) {
        $url = '/static/images/mj_cards/';
    }
    if (!$flg) {
        $list = [];
        if ($gid == 1) {
            $arr = $str ? explode(',', $str) : [];
        } else if ($gid == 5) {
            $mjh_cardArr = config('mjh_cardArr');
            $arr         = str_split($str, 2);
            if (count($arr) == 1) {
                $arr = [];
            }

        }
        if ($arr) {
            foreach ($arr as $v) {
                if ($gid == 1) {
                    $list[] = $url . $v . '.png';
                } else if ($gid == 5) {
                    $list[] = isset($mjh_cardArr[$v]) ? $url . $mjh_cardArr[$v] . '.png' : '';
                }
            }
        }
    } else {
        $list = $url . $str . '.png';
    }
    return $list;
}

/**
 * 获取得分符号
 */
function get_score_sign($score = 0)
{
    $str = '';
    if ($score > 0) {
        $str = '+';
    }
    return $str . $score;
}

/**
 * 将秒数转换为时分秒
 */
function get_hms_time($seconds = 0)
{
    if ($seconds <= 0) {
        return '0';
    }

    $str  = '';
    $hour = floor($seconds / 3600);
    $str .= $hour != 0 ? $hour . '小时' : '';
    $minute = floor(($seconds - 3600 * $hour) / 60);
    $str .= $minute != 0 ? $minute . '分' : '';
    $second = floor((($seconds - 3600 * $hour) - 60 * $minute) % 60);
    $str .= $second != 0 ? $second . '秒' : '';
    return $str;
}

/**
 * 获取类型的符号
 */
function get_type_symbol($type)
{
    return (($type > 0 && $type < 200) || ($type > 300 && $type < 400)) ? '+' : '-';
}

/**
 * 导出csv
 */
function export_to_csv($str, $filename, $data_time)
{
    /*表头时间*/
    $s_date   = isset($data_time['start_date']) && $data_time['start_date'] ? date('Ymd', strtotime($data_time['start_date'])) : date('Ymd');
    $e_date   = isset($data_time['end_date']) && $data_time['end_date'] ? date('Ymd', strtotime($data_time['end_date'])) : date('Ymd');
    $time_str = ($s_date == $e_date) ? $s_date : ($s_date . '-' . $e_date);

    $str      = mb_convert_encoding($str, "GBK", "UTF-8");
    $filename = $time_str . $filename . '.csv'; //设置文件�?
    header("Content-type:text/csv;");
    header("Content-Disposition:attachment;filename=" . $filename);
    header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
    header('Expires:0');
    header('Pragma:public');
    echo $str;
    exit;
}

/**
 * 时间处理
 */
function time_condition($startDate, $endDate, $field)
{
    $where     = [];
    $startTime = strtotime($startDate);
    $endTime   = strtotime($endDate) + 86400 - 1;
    if ($startDate && !$endDate) {
        $where[$field] = ['egt', $startTime];
    } elseif (!$startDate && $endDate) {
        $where[$field] = ['elt', $endTime];
    } elseif ($startDate && $endDate) {
        $where[$field][] = ['egt', $startTime];
        $where[$field][] = ['elt', $endTime];
    }
    return $where;
}

/**
 * 时间条件
 */
function time_where($startTime, $endTime, $field)
{
    $where[$field][] = ['egt', $startTime];
    $where[$field][] = ['elt', $endTime];
    return $where;
}

/**
 * 密码验证
 */
function check_second_password()
{
    //验证密码
    $password = input('password', 0);
    $mg_info  = Db::table('mg_user')->field('second_password, salt')->where('mgid', UID)->find();
    if (minishop_md5($password, $mg_info['salt']) !== $mg_info['second_password']) {
        return false;
    }
    return true;
}

/**
 * 颜色显示
 */
function color_show($num)
{
    $str = 'style="color:';
    $str .= $num > 0 ? 'red' : '#1ab394';
    $str .= '"';
    return $str;
}

function formatsize($size)
{
    $prec  = 3;
    $size  = round(abs($size));
    $units = array(0 => " B", 1 => " KB", 2 => " MB", 3 => " GB", 4 => " TB");
    if ($size == 0) {
        return str_repeat(" ", $prec) . "0" . $units[0];
    }
    $unit = min(4, floor(log($size) / log(2) / 10));
    $size = $size * pow(2, -10 * $unit);
    $digi = $prec - 1 - floor(log($size) / log(10));
    $size = round($size * pow(10, $digi)) * pow(10, -$digi);
    return $size . $units[$unit];
}

function detect_encoding($str)
{
    $chars = null;
    $list  = array('GBK', 'UTF-8');
    foreach ($list as $item) {
        $tmp = mb_convert_encoding($str, $item, $item);
        if (md5($tmp) == md5($str)) {
            $chars = $item;
        }
    }
    return strtolower($chars) !== 'Utf-8' ? iconv($chars, strtoupper('Utf-8') . '//IGNORE', $str) : $str;
}

function set_chars()
{
    return 0 == 'gbk' ? 'GB2312' : 'UTF-8';
}

function convert_charset($str)
{
    return $str;
}

//删除文件夹或者目录
function delDirAndFile($path, $delDir = false)
{
    $handle = opendir($path);
    if ($handle) {
        while (false !== ($item = readdir($handle))) {
            if ($item != "." && $item != "..") {
                is_dir("$path/$item") ? delDirAndFile("$path/$item", $delDir) : unlink("$path/$item");
            }

        }
        closedir($handle);
        if ($delDir) {
            return rmdir($path);
        }

    } else {
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
}
