<?php

namespace app\common\logic\saas;

use app\common\model\saas\AppService;
use app\common\model\saas\Miniapp;
use app\common\model\saas\UserMiniapp;
use think\Validate;
use app\common\model\saas\Users;
use app\common\model\saas\ExtendService;
use app\common\model\saas\MiniappTemplate;
use app\common\logic\saas\wechat\MiniApp3rd;

/**
 * 微信小程序逻辑处理
 */
class MiniappLogic
{
    /**
     * 绑定/解绑体验者
     * @param string $wechatId 用户微信号
     * @param string $operate 操作码 'unbind'，'bind'
     */
    public function bindTester($miniappId, $userId, $wechatId, $operate)
    {
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }

        $miniApp3rd = new MiniApp3rd($miniapp);
        if ($operate == 'unbind') {
            $return = $miniApp3rd->unbindTester($wechatId);
        } else {
            $return = $miniApp3rd->bindTester($wechatId);
        }

        if ($return === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        $testers = $miniapp->testers;
        if ($operate == 'unbind') {
            foreach ($testers as $k => $tester) {
                if ($wechatId == $tester) {
                    unset($testers[$k]);
                    break;
                }
            }
        } else {
            if (!in_array($wechatId, $testers)) {
                $testers[] = $wechatId;
            }
        }

        $miniapp->save(['testers' => $testers]);
        return ['status' => 1, 'msg' => ($operate == 'unbind' ? '解绑成功' : '绑定成功')];
    }

    /**
     * 设置服务器域名
     */
    public function setServerDomains($miniappId, $userId, $domains)
    {
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }

        //$domains['requestdomain'];
        //$domains['wsrequestdomain'];
        //$domains['uploaddomain'];
        //$domains['downloaddomain'];
        foreach ($domains as &$domain) {
            $domain = explode(',', $domain);
            foreach ($domain as $k => $url) {
                $domain[$k] = urldecode($url);
            }
        }

        $miniApp3rd = new MiniApp3rd($miniapp);
        $return = $miniApp3rd->modifyDomain('set', $domains);
        if ($return === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        $domains = $miniApp3rd->getDomain();
        if ($domains === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        $msg = '';
        $return = $miniApp3rd->setWebViewDomain();
        if ($return === false) {
            if ($miniApp3rd->getErrCode() == 89231) {
                $msg = '，这是个人小程序，web-view部分功能没法使用';
            } else {
                return ['status' => -1, 'msg' => $miniApp3rd->getError()];
            }
        }

        Miniapp::update(['domains' => $domains], ['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1]);

        return ['status' => 1, 'msg' => '设置成功'.$msg];
    }

    /**
     * 重置域名
     */
    public function resetServerDomains($miniappId, $userId)
    {
        $appService = AppService::get(['miniapp_id' => $miniappId], 'app');
        if (!$appService || $appService->install_status != AppService::INSTALL_DONE) {
            return ['status' => -1, 'msg' => '请先关联应用或购买应用服务或安装应用'];
        }

        $url = $appService->app->miniapp_domain;
        $domains['requestdomain'] = 'https://'.$url;
        $domains['wsrequestdomain'] = 'wss://'.$url;
        $domains['uploaddomain'] = 'https://'.$url;
        $domains['downloaddomain'] = 'https://'.$url;

        return $this->setServerDomains($miniappId, $userId, $domains);
    }

    private function validateCommitMiniappData($data, $miniapp)
    {
        $validate = new Validate([
            'store_name'  => 'require|min:1',
        ]);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        $domains = $miniapp->domains;
        if (!$domains || !$domains['requestdomain'] || !$domains['wsrequestdomain'] || !$domains['uploaddomain'] || !$domains['downloaddomain']) {
            return ['status' => -1, 'msg' => '域名信息不全，请先设置服务器域名'];
        } elseif (!in_array($data['request_url'], $domains['requestdomain']) || !in_array($data['request_url'], $domains['uploaddomain'])) {
            //目前只用了requestdomain和uploaddomain
            return ['status' => -1, 'msg' => '所填域名不在服务器域名配置中'];
        }

        return ['status' => 1, 'msg' => '验证成功'];
    }

    /**
     * 提交小程序，使小程序进入体验版
     * @param $miniappId
     * @param $data array 提交的数据
     * @return array
     */
    public function commitMiniapp($miniappId, $userId, $data)
    {
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1], 'appService')) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }
        if (!$miniapp->app_service || $miniapp->app_service->install_status != AppService::INSTALL_DONE) {
            return ['status' => -1, 'msg' => '请先安装应用'];
        }

        $orgRequestUrl = $miniapp->app_service->app->miniapp_domain;
        $data['request_url'] = 'https://'.$orgRequestUrl;
        $data['store_name'] = tpCache("shop_info.store_name");
        $data['store_logo'] = tpCache("shop_info.store_logo");
        $data['version'] = 'v3.0.0';
        $data['description'] = 'app_service_id:'.$miniapp->app_service->service_id;
        $return = $this->validateCommitMiniappData($data, $miniapp);
        if ($return['status'] != 1) {
            return $return;
        }

        $template = MiniappTemplate::get(['is_on_sale' => 1, 'app_id' => $miniapp->app_service->app_id]);
        if (! $template) {
            return ['status' => -1, 'msg' => '所选模板不存在或已下线'];
        }

        $extCfg = json_encode([
            'extAppid' => $miniapp->appid,
            'ext' => [
                'store_name'  => $data['store_name'],
                'store_logo'  => $data['store_logo'],
                'request_url' => $data['request_url'],
                'default_url' => $miniapp->app_service->app->miniapp_domain,
                'is_refactor' => $miniapp->app_service->app->miniapp_domain == $orgRequestUrl,
                'saas_app'    => $miniapp->app_service->domain
            ],
        ], JSON_UNESCAPED_UNICODE);

        $miniApp3rd = new MiniApp3rd($miniapp);
        $return = $miniApp3rd->commit($template['miniapp_tpl_id'], $extCfg, $data['version'], $data['description']);
        if ($return === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        $data['name'] = $template->name;
        $data['template_version'] = $template->template_version;
        $data['user_id']    = $miniapp->user_id;
        $data['miniapp_id'] = $miniapp->miniapp_id;
        $data['ext_config'] = $extCfg;
        $data['add_time']   = time();
        $data['status']     = UserMiniapp::STATUS_TEST;

        //只能有一个体验版
        $userMiniapp = UserMiniapp::get(['user_id' => $userId, 'miniapp_id' => $miniapp->miniapp_id, 'status' => UserMiniapp::STATUS_TEST]);
        if ($userMiniapp) {
            $userMiniapp->save($data);
        } else {
            UserMiniapp::create($data);
        }

        return ['status' => 1, 'msg' => '提交成功'];
    }
    /**
     * 检查提交审核的表单
     */
    private function checkAuditForm($miniapp, $data)
    {
        /* 检查题目合法性 */
        if (!$data['title'] || count($data['title']) > 32) {
            return ['status' => -1, 'msg' => '标题长度取值范围为 1~32'];
        }

        /* 检查tag合法性 */
        if ($data['tag']) {
            $tags = explode(' ', $data['tag']);
            if (count($tags) > 10) {
                return ['status' => -1, 'msg' => '标签不能多于10个'];
            }
            foreach ($tags as $tag) {
                if (count($tag) > 20) {
                    return ['status' => -1, 'msg' => '标签长度不超过20'];
                }
            }
        }

        /* 检查服务类目合法性 */
        $miniApp3rd = new MiniApp3rd($miniapp);
        $categories = $miniapp->categories ?: [];
        if (!$categories) {
            $categories = $miniApp3rd->getCategory();
            if ($categories === false) {
                return ['status' => -1, 'msg' => $miniApp3rd->getError()];
            }
            if (!$categories) {
                return ['status' => -1, 'msg' => '服务类目为空'];
            }
            Miniapp::update(['categories' => $categories], ['miniapp_id' => $miniapp->miniapp_id]);
        }
        $category = [];
        $isFindCategory = false;
        foreach ($categories as $category) {
            $category['second_id'] = isset($category['second_id']) ? $category['second_id'] : '';
            $category['third_id'] = isset($category['third_id']) ? $category['third_id'] : '';
            if ($category['first_id'] == $data['first_id']
                && $category['second_id'] == $data['second_id']
                && $category['third_id'] == $data['third_id']) {
                $isFindCategory = true;
                break;
            }
        }
        if (!$isFindCategory) {
            return ['status' => -1, 'msg' => '服务类目不在已设置的范围内'];
        }

        /* 提交审核的参数 */
        $data['first_class']  = $category['first_class'];
        $data['second_class'] = isset($category['second_class']) ? $category['second_class'] : '';
        $data['third_class']  = isset($category['third_class']) ? $category['third_class'] : '';
        $data['address'] = 'pages/index/index/index'; //目前只固定为首页，还没做定制,可从getPage获取

        $data = array_allow_keys($data, ['address', 'tag', 'first_class', 'second_class', 'third_class', 'first_id', 'second_id', 'third_id', 'title']);
        return ['status' => 1, 'msg' => '检查成功', 'result' => $data];
    }

    /**
     * 提交审核
     * @param $miniappId int 小程序id
     * @param $data array 提交的数据（title,tag,first_id,second_id,third_id）
     * @return array
     */
    public function submitAudit($miniappId, $userId, $data)
    {
        /* 检查小程序数据是否完整 */
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }
        if (!$miniappTest = UserMiniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'status' => UserMiniapp::STATUS_TEST])) {
            return ['status' => -1, 'msg' => '该体验版本不存在，不能提交审核'];
        }
        /**
        $miniappService = ExtendService::get(['user_id' => $userId, 'extend_id' => $miniappTest->template_id, 'extend_type' => EXTEND_MINIAPP]);
        if (!$miniappService) {
            return ['status' => -1, 'msg' => '该小程序模板尚未购买'];
        } elseif ($miniappService->status != ExtendService::STATUS_NORMAL) {
            return ['status' => -1, 'msg' => '该小程序模板已过期'];
        }
        **/
        /* 检查是否有已经提交的审核 */
        $miniappAudit = UserMiniapp::get(['miniapp_id' => $miniapp->miniapp_id, 'status' => ['in', [UserMiniapp::STATUS_AUDITING, UserMiniapp::STATUS_AUDIT_DONG]]]);
        if ($miniappAudit) {
            return ['status' => -1, 'msg' => '已有在审核中的版本或审核通过尚未发布的版本'];
        }

        /* 检查提交审核的参数 */
        $return = $this->checkAuditForm($miniapp, $data);
        if ($return['status'] != 1) {
            return $return;
        }

        /* 提交审核 */
        $itemList[] = $return['result']; //目前只能设置一个页面的配置
        $miniApp3rd = new MiniApp3rd($miniapp);
        $auditId = $miniApp3rd->submitAudit($itemList);
        if ($auditId === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }
        $miniappTest->save([
            'audit_id'  => $auditId,
            'status'    => UserMiniapp::STATUS_AUDITING,
            'audit_time' => time(),
        ]);

        return ['status' => 1, 'msg' => '提交审核成功'];
    }

    /**
     * 发布小程序
     */
    public function releaseMiniapp($miniappId, $userId)
    {
        if (!$miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1])) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }

        $miniApp3rd = new MiniApp3rd($miniapp);
        if ($miniApp3rd->release() === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        //同步一下可访问状态，不处理错误
        $miniApp3rd->changeVisitStatus($miniapp->is_show_release);

        //以前上线的已无效
        UserMiniapp::update(['status' => UserMiniapp::STATUS_INVALID], [
            'miniapp_id' => $miniapp->miniapp_id,
            'status' => UserMiniapp::STATUS_ON_RELEASE
        ]);

        //审核通过的改为已上线
        UserMiniapp::update(['status' => UserMiniapp::STATUS_ON_RELEASE, 'release_time' => time()], [
            'miniapp_id' => $miniapp->miniapp_id,
            'status' => UserMiniapp::STATUS_AUDIT_DONG
        ]);

        return ['status' => 1, 'msg' => '发布成功'];
    }

    /**
     * 设置可见（可访问）状态
     * @param $status int|boolean 设置访问状态
     * @return array
     */
    public function setVisitStatus($miniappId, $userId, $status)
    {
        $miniapp = Miniapp::get(['user_id' => $userId, 'miniapp_id' => $miniappId, 'is_auth' => 1]);
        if (!$miniapp) {
            return ['status' => -1, 'msg' => '小程序尚未授权'];
        }

        $status = $status ? 1 : 0;
        $miniApp3rd = new MiniApp3rd($miniapp);
        if ($miniApp3rd->changeVisitStatus($status) === false) {
            return ['status' => -1, 'msg' => $miniApp3rd->getError()];
        }

        $miniapp->save(['is_show_release' => $status]);

        return ['status' => 1, 'msg' => '设置成功'];
    }

    /**
     * 获取各版本的信息
     * @param $miniapp
     * @return array
     */
    public function getVersionsInfo($miniapp)
    {
        // 体验版本
        $test = UserMiniapp::where(['miniapp_id' => $miniapp->miniapp_id, 'status' => UserMiniapp::STATUS_TEST])->order('add_time desc')->find();
        // 审核版本
        $auditStatus = [UserMiniapp::STATUS_AUDITING, UserMiniapp::STATUS_AUDIT_DONG, UserMiniapp::STATUS_AUDIT_FAIL];
        $audit = UserMiniapp::where(['miniapp_id' => $miniapp->miniapp_id, 'status' => ['in', $auditStatus]])->order('audit_time desc')->find();
        if ($audit && $audit->status == UserMiniapp::STATUS_AUDITING) { //正在审核
            $miniApp3rd = new MiniApp3rd($miniapp);
            $result = $miniApp3rd->getAuditStatus($audit->audit_id);
            if ($result === false) {
                return ['status' => -1, 'msg' => $miniApp3rd->getError()];
            }
            $statusMap = [0 => UserMiniapp::STATUS_AUDIT_DONG, 1 => UserMiniapp::STATUS_AUDIT_FAIL, 2 => UserMiniapp::STATUS_AUDITING];
            $returnStatus = $statusMap[$result['status']];
            if ($returnStatus != 1) {
                $audit->status = $returnStatus;
                $audit->audit_fail_reason = $result['reason'];
                $audit->save();
            }
        }
        // 发布版本
        $release = UserMiniapp::where(['miniapp_id' => $miniapp->miniapp_id, 'status' => UserMiniapp::STATUS_ON_RELEASE])->order('release_time desc')->find();

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'test' => $test,
            'audit' => $audit,
            'release' => $release
        ]];
    }

    private function checkTemplateForm($data)
    {
        $validate = new Validate([
            ['price','require|number','价格必填|价格格式不正确'],
            ['miniapp_tpl_id', 'require|number','模板id必须|模板id必须为数字'],
            ['name', 'require|min:1', '模板名称必须|模板名称非空'],
            ['template_version', 'require|min:1', '模板名称必须|模板名称非空'],
            ['app_id', 'require|>:0','app_id必须|app_id不能为空'],
        ]);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        return ['status' => 1, 'msg' => '验证成功'];
    }

    /**
     * 添加模板
     */
    public function addTemplate($data)
    {
        $return = $this->checkTemplateForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($template = MiniappTemplate::get($data['miniapp_tpl_id'])) {
            return ['status' => -1, 'msg' => '小程序模板id已存在'];
        }

        $template = MiniappTemplate::create($data);

        return ['status' => 1, 'msg' => '添加成功', 'result' => $template->template_id];
    }

    /**
     * 编辑模板
     */
    public function editTemplate($data)
    {
        $return = $this->checkTemplateForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if (!$template = MiniappTemplate::get(['template_id' => $data['template_id']])) {
            return ['status' => -1, 'msg' => '模板不存在'];
        }

        if (MiniappTemplate::get(['template_id' => ['<>', $data['template_id']], 'miniapp_tpl_id' => $data['miniapp_tpl_id']])) {
            return ['status' => -1, 'msg' => '小程序模板id已存在'];
        }

        $template->save($data);

        return ['status' => 1, 'msg' => '编辑成功', 'result' => $template->template_id];
    }

    /**
     * 删除模板
     */
    public function deleteTemplate($templateId)
    {
        if (!$template = MiniappTemplate::get(['template_id' => $templateId])) {
            return ['status' => -1, 'msg' => '模板不存在'];
        }

        if (ExtendService::get(['extend_id' => $template->template_id, 'extend_type' => EXTEND_MINIAPP, 'status' => ExtendService::STATUS_NORMAL])) {
            return ['status' => -1, 'msg' => '该模板已有用户在使用'];
        }

        $template->delete();

        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 更新小程序信息
     */
    public function updateMiniapp($userId, $miniappId)
    {
        if (!$miniapp = Miniapp::get(['miniapp_id' => $miniappId, 'user_id' => $userId])) {
            return ['status' => -1, 'msg' => '小程序不存在'];
        }

        $logic = new Wx3rdLogic;
        $return = $logic->getAuthUserInfo($miniapp->appid);
        if ($return['status'] != 1) {
            return $return;
        }

        $miniapp->save($return['result']);

        if ($user = Users::get(['user_id' => $miniapp->user_id])) {
            if (!$user->head_img && $miniapp->head_img) {
                $user->save(['head_img' => $miniapp->head_img]); //更新头像
            }
        }

        return ['status' => 1, 'msg' => '更新成功'];
    }
}