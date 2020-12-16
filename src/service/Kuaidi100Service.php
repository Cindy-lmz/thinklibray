<?php


declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\CodeExtend;
use think\admin\extend\HttpExtend;
use think\admin\Service;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx; 

/**
 * 快递100物流查询
 * Class Kuaidi100Service
 * @package think\admin\service
 */
class Kuaidi100Service extends Service
{

    /**
     * 网络请求参数
     * @var array
     */
    protected $customer;

    /**
     *  当前COOKIE文件
     * @var string
     */
    protected $key;

    /**
     * 快递服务初始化
     * @return $this
     */
    protected function initialize()
    {
        $this->customer = sysconf('kuaidi100.customer') ?: '';
        $this->key      = sysconf('kuaidi100.key') ?: '';
    }

    /**
     * 账号初始化
     * @Author   Cindy
     * @DateTime 2020-11-20T12:04:42+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    string                     $customer [description]
     * @param    string                     $key      [description]
     */
    public function setAuth(string $customer, string $key): Kuaidi100Service
    {
        $this->customer = $customer;
        $this->key = $key;
        return $this;
    }

    /**
     * 生成签名
     * @Author   Cindy
     * @DateTime 2020-11-20T12:05:21+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     */
    private function getSign(array $data,string $buff = '')
    {
        ksort($data);
        if (isset($data['sign'])) unset($data['sign']);
        $buff = json_encode($data).$this->key.$this->customer;
        return strtoupper(md5($buff));
    }

    /**
     * 实时快递查询接口
     * @Author   Cindy
     * @DateTime 2020-11-20T12:05:47+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @return   [type]                           [description]
     */
    public function pollyQuery(array $data):array
    {   
        $url = "https://poll.kuaidi100.com/poll/query.do";
        $post_data['param'] = json_encode($data);
        $post_data['customer'] = $this->customer;
        $post_data['sign'] = $this->getSign($data);
        return $this->doRequest($url,$post_data);
    }

    /**
     * 物流信息订阅
     * @Author   Cindy
     * @DateTime 2020-11-20T16:51:22+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    array                      $data               [description]
     * @param    array                      $parameters         [附加参数信息]
     * @param    array                      $departureCountry   [出发国家编码]
     * @param    array                      $destinationCountry [目的国家编码]
     */
    public function poll(array $data, array $parameters, array $departureCountry = [], array $destinationCountry = []):array
    {
        $url = "https://poll.kuaidi100.com/poll";
        $data['key'] = $this->key;
        $data['parameters'] = $parameters;
        !empty($departureCountry) && $data['departureCountry'] = $departureCountry;
        !empty($destinationCountry) && $data['departureCountry'] = $destinationCountry;
        $post_data['schema'] = 'json';
        $post_data['param'] = json_encode($data);
        return $this->doRequest($url,$post_data);
    }

    /**
     * 获取订阅物流推送
     * @Author   Cindy
     * @DateTime 2020-11-20T18:11:09+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    string                     $param [推送参数]
     */
    public function getNotify(string $param)
    {
        $result = json_decode(stripcslashes($param),true);
        p($result,false,'kuaidi100');
        if ($result['status'] == 'shutdown') {
            return ['code'=> 1, 'status'=> $result['status'],'data' => ['lastResult'=>$result['lastResult']['data'],'destResult'=>$result['destResult']['data']], 'message' => $this->error($result['returnCode'])];
        }else{
            return ['code'=> 0, 'status'=> $result['status'],'data' => $result['lastResult']['data'], 'message' => $this->error( $result['lastResult']['status'])];
        }

    }

    /**
     * 获取物流通知回复内容
     * @Author   Cindy
     * @DateTime 2020-11-20T18:11:39+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    bool|boolean               $isSuccess [是否继续推送消息]
     */
    public function getNotifySuccessReply(bool $isSuccess = true)
    {
        if ($isSuccess) {
            return json(['result'=>true,'returnCode'=>200,'message'=>"成功"]);
        }else{
            return json(['result'=>false,'returnCode'=>500,'message'=>"失败"]);
        }
    }

    /**
     * 执行网络请求
     * @param string $url 接口请求地址
     * @param array $data 接口请求参数
     * @return array
     */
    public function doRequest(string $url, array $data): array
    {
        $o="";
        foreach ($data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";   //默认UTF-8编码格式
        }
        $data=substr($o,0,-1);
        $options = ['headers' => ['Content-Type:application/x-www-form-urlencoded']];
        $result = HttpExtend::post($url,$data,$options);
        $result =str_replace("\"",'"',$result);
        $result = json_decode($result,true);
        if (empty($result)) {
            return ['status'=> 0, 'data' => [], 'message' => '接口请求网络异常'];
        }elseif (isset($result['returnCode'])) {
            return ['status'=> $result['returnCode'], 'data' => [], 'message' => $this->error($result['returnCode'])];
        }elseif (isset($result['status']) && $result['status'] == '200') {
            $list = [];
            foreach ($result['data'] as $vo){
                $list[] = [
                'time' => format_datetime($vo['time']), 
                'ftime' => format_datetime($vo['ftime']), 
                'context' => $vo['context'],
                ];
            } 
            return [
                'status' => $result['status'],
                'message' => $result['message'], 
                'express' => $result['com'], 
                'number' => $result['nu'],
                'state'=>$this->geterror($result['state']), 
                'data' => $list
            ];
        }else{
            return $result;
        }
       
    }

    /**
     * 获取快递公司列表
     * @Author   Cindy
     * @DateTime 2020-11-20T15:26:32+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @return   [type]                     [description]
     */
    public function getExpressList()
    {
        $filePath = "{$this->app->getRootPath()}public/static/excel/kuaidi100express.xlsx";
        $inputFileType = IOFactory::identify($filePath); //传入Excel路径
        $excelReader   = IOFactory::createReader($inputFileType); //Xlsx
        $PHPExcel      = $excelReader->load($filePath); // 载入excel文件
        $sheet         = $PHPExcel->getSheet(0); // 读取第一個工作表
        $sheetdata = $sheet->toArray();
        $import_data = array(); //数组形式获取表格数据
        unset($sheetdata[0],$sheetdata[1]);
        foreach ($sheetdata as $key => $value) {
            $import_data[$value[1]] =$value[0];
        }
        if (empty($import_data)) {
            return ['status'=> 0, 'data' => [], 'message' => '暂无数据！'];
        }
        return ['status'=> 200, 'data' => $import_data, 'message' => 'ok'];
    }

    /**
     * [运单签收状态]
     * @Author   Cindy
     * @DateTime 2020-11-20T12:32:49+0800
     * @E-main   [cindyli@topichina.com.cn]
     * @version  [1.0]
     * @param    string                        $code [状态值]
     * @return   [type]                           [名称:含义]
     */
    private function geterror(string $code): string
    {
        $arrs = [
            0  => '在途:快件处于运输过程中',
            1  => '揽收:快件已由快递公司揽收',
            2  => '疑难:快递100无法解析的状态，或者是需要人工介入的状态， 比方说收件人电话错误。',
            3  => '签收:正常签收',
            4  => '退签:货物退回发货人并签收',
            5  => '派件:货物正在进行派件',
            6  => '退回:货物正处于返回发货人的途中',
            7  => '转单:货物转给其他快递公司邮寄',
            10 => '待清关:货物等待清关',
            11 => '清关中:货物正在清关流程中',
            12 => '已清关:货物已完成清关流程',
            13 => '清关异常:货物在清关过程中出现异常',
            14 => '拒签:收件人明确拒收',
        ];
        return $arrs[$code] ?? "{$code}";
    }

    /**
     * 获取状态描述
     * @param string $code 异常编号
     * @return string
     */
    private function error(string $code): string
    {
        $arrs = [
            200  => '查询成功',
            400 => '提交的数据不完整，或者贵公司没授权',
            500 => '表示查询失败，或没有POST提交',
            501 => '服务器错误，快递100服务器压力过大或需要升级，暂停服务',
            502 => '服务器繁忙或重复订阅（请格外注意，501表示这张单已经订阅成功且目前还在跟踪过程中（即单号的status=polling），快递100的服务器会因此忽略您最新的此次订阅请求，从而返回501。一个运单号只要提交一次订阅即可，若要提交多次订阅，请在收到单号的status=abort或shutdown后隔半小时再提交订阅',
            503 => '验证签名失败',
            701 => '拒绝订阅的快递公司 ',
            700 => '订阅方的订阅数据存在错误（如不支持的快递公司、单号为空、单号超长等）或错误的回调地址',
            702 => 'POLL:识别不到该单号对应的快递公司',
            600 => '您不是合法的订阅者（即授权Key出错）',
            601 => 'POLL:KEY已过期 500: 服务器错误（即快递100的服务器出理间隙或临时性异常，有时如果因为不按规范提交请求，比如快递公司参数写错等，也会报此错误）',
        ];
        return $arrs[$code] ?? "{$code}";
    }

    

}