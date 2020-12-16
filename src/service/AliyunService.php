<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\HttpExtend;
use think\admin\Service;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;


/**
 * 阿里云短信服务
 * Class AliyunService
 * @package think\admin\service
 */
class AliyunService extends Service
{
    protected $status;

    public function __construct()
    {
        $this->config = [
        		'version'       => sysconf('aliyun_sms.version'),
		        'host'          => sysconf('aliyun_sms.host'),
		        'scheme'        => sysconf('aliyun_sms.scheme'),
		        'region_id'     => sysconf('aliyun_sms.region_id'),
		        'access_key'    => sysconf('aliyun_sms.access_key'),
		        'access_secret' => sysconf('aliyun_sms.access_secret'),
		        'sign_name'     => sysconf('aliyun_sms.sign_name'),
		        'actions'       => [
		            'register'        => [
		                'actions_name'      => '注册验证',
		                'template_id'  => sysconf('aliyun_sms.register_template_id'),
		            ],
		            'login'           => [
		                'actions_name'      => '登录验证',
		                'template_id'  => sysconf('aliyun_sms.login_template_id'),
		            ],
		            'changePassword' => [
		                'actions_name'      => '修改密码',
		                'template_id'  => sysconf('aliyun_sms.changePassword_template_id'),
		            ],
		            'changeUserinfo' => [
		                'actions_name'      => '变更信息',
		                'template_id'  => sysconf('aliyun_sms.changeUserinfo_template_id'),
		            ],
		        ],
        ];

        if (empty($this->config['access_key']) || empty($this->config['access_secret'])) {
            $this->status = false;
        } else {
            $this->status = true;
            AlibabaCloud::accessKeyClient($this->config['access_key'], $this->config['access_secret'])
                ->regionId($this->config['region_id'])
                ->asDefaultClient();
        }
    }

    /**
     * @Author   Cindy
     * @DateTime 2020-11-17T17:28:21+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    string                     $action    [方法名]
     * @param    array                      $arguments [description]
     * @return   [type]                                [description]
     */
    private function doRequest(string $action, array $options): array
    {
    	if ($this->status) {
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->version($this->config['version'])
                ->action($action)
                ->method('POST')
                ->host($this->config['host'])
                ->options([
                    'query' => $options,
                ])
                ->request();
            $result = $result->toArray();
            if ($result['Code'] == "OK") {
                $data['code'] = 200;
                $data['msg'] = '发送成功';
            } else {
                $data['code'] = $result['Code'];
                $data['msg'] = '发送失败，'.$result['Message'];
            }
        } else {
            $data['code'] = 103;
            $data['msg'] = '请在后台设置accessKeyId和accessKeySecret';
        }
        return $data;
    }

    /**
     * 验证手机短信验证码
     * @param string $code 验证码
     * @param string $phone 手机号验证
     * @param string $tplcode
     * @return boolean
     */
    public function checkVerifyCode(string $code, string $phone, string $tplcode = 'register'): bool
    {
        $cache = $this->app->cache->get($ckey = md5("code-{$tplcode}-{$phone}"), []);
        if (is_array($cache) && isset($cache['code']) && $cache['code'] == $code) {
            $this->app->cache->delete($ckey);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 验证手机短信验证码
     * @param string $phone 手机号码
     * @param integer $wait 等待时间
     * @param string $tplcode 模板编号
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sendVerifyCode(string $phone, string $tplcode = 'register', int $wait = 120): array
    {
        $content = '您的短信验证码为{code}，请在十分钟内完成操作！';
        $cache = $this->app->cache->get($ckey = md5("code-{$tplcode}-{$phone}"), []);
        // 检查是否已经发送
        if (is_array($cache) && isset($cache['time']) && $cache['time'] > time() - $wait) {
            $dtime = ($cache['time'] + $wait < time()) ? 0 : ($wait - time() + $cache['time']);
            return [1, '短信验证码已经发送！', ['time' => $dtime]];
        }
        // 生成新的验证码
        [$code, $time] = [rand(100000, 999999), time()];
        $this->app->cache->set($ckey, ['code' => $code, 'time' => $time], 600);
        // 尝试发送短信内容
        [$state] = $this->timeSend($phone, preg_replace_callback("|{(.*?)}|", function ($matches) use ($code) {
            return $matches[1] === 'code' ? $code : $matches[1];
        }, $content),0,$tplcode);
        if ($state) return [1, '短信验证码发送成功！', [
            'time' => ($time + $wait < time()) ? 0 : ($wait - time() + $time)],
        ]; else {
            $this->app->cache->delete($ckey);
            return [0, '短信发送失败，请稍候再试！', []];
        }
    }

    /**
     * 发送定时短信
     * @param string $mobile 发送手机号码
     * @param string $content 发送短信内容
     * @param integer $time 定时发送时间（为 0 立即发送）
     * @param string $name 模板名称
     * @return array
     */
    public function timeSend(string $mobile, string $code, int $time = 0, string $name): array
    {
    	$conf = $this->config['actions'][$name];
        $data = [ 
        			'RegionId' => $this->config['region_id'],
                    'PhoneNumbers' => $mobile,
                    'SignName' => $this->config['sign_name'],
                    'TemplateCode' => $conf['template_id'],
                    'TemplateParam' => json_encode(['code'=>$code]),
                ];
        if ($time > 0) $data['time'] = $time;
        return $this->doRequest('SendSms', $data);
    }

    /**
     * @Author   Cindy
     * @DateTime 2020-11-17T17:10:09+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    array                      $mobile   [手机号]
     * @param    array                      $signname [短信签名名称]
     * @param    int|integer                $time     [description]
     * @param    string                     $name     [模板名称]
     * @return   [type]                               [批量发送短信]
     */
    public function batchSend(string $mobile,string $signname,int $time = 0, string $name, string $content = '', string $smsextendcode = ''): array
    {
    	$conf = $this->config['actions'][$name];
        $data = [ 
                    'RegionId' => $this->config['region_id'],
                    'PhoneNumberJson' => $mobile,
                    'SignNameJson' =>$signname,
                    'TemplateCode' => $conf['template_id'],
                ];
        if (!empty($content)) {
        	$data['SmsUpExtendCodeJson'] = $smsextendcode;
        }
        if (!empty($content)) {
        	$data['TemplateParamJson'] = $content;
        }
        if ($time > 0) $data['time'] = $time;
        return $this->doRequest('SendBatchSms', $data);
    }
    /**
     * @Author   Cindy
     * @DateTime 2020-11-17T17:26:19+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    int                        $page     [分页查看发送记录，指定发送记录的的当前页码]
     * @param    int                        $limit    [分页查看发送记录，指定每页显示的短信记录数量。]
     * @param    string                     $mobile   [接收短信的手机号码]
     * @param    string                     $datatime [短信发送日期，支持查询最近30天的记录。]
     * @param    string                     $bizid    [发送回执ID，即发送流水号]
     * @return   [type]                               [查看短信发送记录和发送状态]
     */
    public function querySend(int $page, int $limit, string $mobile, string $datatime, string $bizid = ''): array
    {
    	$conf = $this->config['actions'][$name];
        $data = [ 
                    'RegionId' => $this->config['region_id'],
                    'CurrentPage' => $page,
                    'PageSize' => $limit,
                    'PhoneNumber' => $mobile,
                    'SendDate' =>$data,
                ];
        if (!empty($bizid)) {
        	$data['BizId'] = $bizid;
        }
        if ($time > 0) $data['time'] = $time;
        return $this->doRequest('QuerySendDetails', $data);
    }
}