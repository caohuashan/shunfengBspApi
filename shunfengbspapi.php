<?php
class Service_Shunfengbsp {
    //域名配置
    protected $url;

    //秘钥
    protected $keyword;

    //接入码
    protected $apicode;

    //月结CUSTID
    protected $cust_id = '';

    public function __construct()
    {
        parent::__construct();
        $sfbsp = array(
          'url'=> 'http://bsp-ois.sit.sf-express.com:9080/bsp-ois/sfexpressService',//接口请求的http协议URL
          'keyword'=> 'j8DzkIFgmlomPt0aLuwU',//开发环境用于测试的秘钥
          'apicode'=>'BSPdevelop',//开发环境用于测试的code
          'custid' => ''//顺丰提供的月结卡号
        );

        if(!$sfbsp){
            exit('shunfengbsp config error');
        }
        $this->url      = $sfbsp['url'];
        $this->keyword  = $sfbsp['keyword'];
        $this->apicode  = $sfbsp['apicode'];
        $this->cust_id  = $sfbsp['custid'];
    }

    /**
     * 顺丰BSP下订单（含筛选）接口
     * @param $info 下单相关信息
     * @return bool
     */
    public function placeOrder($info){
        //必传参数校验
        $params = array(
            'orderid',//客户订单号，唯一不能重复
            'insurePrice',

            'j_contact',//寄件方联系人 选填
            'j_tel',//寄件方联系电话 选填
            'j_province',//寄件方地址 选填
            'j_city',//寄件方地址 选填
            'j_county',//寄件方地址 选填
            'j_address',//寄件方地址 选填

            'd_company',//到件方公司名称 必填
            'd_contact',//到件方联系人 必填
            'd_tel',//到件方联系电话 必填
            'd_province',//到件方省份 选填
            'd_city',//到件方城市 选填
            'd_county',//到件方地区 选填
            'd_address',//到件方地址 必填

            'pay_method',//付款方式 1寄方付、2收方付、3第三方付
            'express_type',//快件类型:1.标准 2.顺丰隔日3.电商特惠5顺丰次晨6即日件7电商平邮
            'sendstarttime',//上门取件时间 选填 格式 YYYY-MM-DD
            'is_docall',//是否要求通过手持端通知顺丰收派员收件
            'remark',//订单备注信息
        );

        //参数校验 end
        if(!is_array($info)){
            return false;
        }
        $list['custid'] = $this->cust_id;//月结卡号需要配置
        $list['is_gen_bill_no'] = 1;
        //参数值校验
        foreach($params as $v){
            if(array_key_exists($v,$info)){
                if($info[$v]){
                    $list[$v] = $info[$v];
                }
            }
        }
        //TODO 如何封装保价到数据中去

        $serviceName = 'Order';
        //将数组转化为XML
        $bodyXml = '<'.$serviceName.' ';
        foreach($list as $k=>$v){
            $bodyXml .= ' '.$k.'="'.$v.'"';
        }
        $bodyXml .= '/></'.$serviceName.'>';
        $result = $this->doRequest($serviceName, $bodyXml);
        if(isset($result['mailno'])){
            return $result;
        }
        return false;
    }

    /**
     * 订单结果查询接口
     * @param $orderid 客户订单号
     * @return bool
     */
    public function orderResultQuery($orderid){
        $serviceName = 'OrderSearch';
        $bodyXml = '<OrderSearch orderid="'.$orderid.'"/>';
        $result = $this->doRequest($serviceName, $bodyXml);
        return $result;
    }

    /**
     * 路由查询接口 TODO 查不出来数据
     * @param $expressNo 顺丰运单号
     * @return bool
     * @internal param
     */
    public function routerQuery($expressNo){
        $serviceName = 'Route';
        $bodyXml = '<RouteRequest tracking_type="1" method_type="1" tracking_number="'.$expressNo.'"/>';
        $result = $this->doRequest($serviceName, $bodyXml);
        $list = array();
        if(!$result){
            return $list;
        }
        foreach($result as $k=>$v){
            $list[$k]['accept_time'] = isset($v['attributes']['accept_time'])?$v['attributes']['accept_time']:'';
            $list[$k]['remark']      = isset($v['attributes']['remark'])?$v['attributes']['remark']:'';
        }
        return $list;
    }

    /**
     * 路由推送接口,接收传递过来的数据并解析成数据并返回
     * @param $xml
     * @return bool
     */
    public function routePush($xml){
        $res = $this->LoadXml($xml);
        if(!is_array($res)){
            return false;
        }
        if(isset($res['Body']['WaybillRoute']['attributes'])){
            return $res['Body']['WaybillRoute']['attributes'];
        }
        return false;
    }

    /**
     * 路由推送响应
     * @param string $state success 或 fail
     * @return string xml格式
     */
    public function responsePush($state = 'fail'){
        $header = '<?xml version="1.0" encoding="UTF-8" ?>';
        $success = $header.'<Response service="RoutePushService"><Head>OK</Head></Response>';
        $fail    = $header.'<Response service="RoutePushService"><Head>ERR</Head><ERROR code="4001">系统发生数据错误或运行时异常</ERROR></Response>';
        if($state == 'success'){
            exit($success);
        }
        exit($fail);
    }

    /**
     * 标准运费查询接口
     * @param $queryList 查询的字段信息
     * @return bool
     */
    public function standardFreightQuery($queryList){
        $serviceName = 'QueryFreight';
        $bodyXml = '<QueryFreightObj ';
        if(!is_array($queryList)){
            return false;
        }
        foreach($queryList as $k=>$v){
            $bodyXml .= ' '.$k.'="'.$v.'"';
        }
        $bodyXml .= ' />';
        $result = $this->doRequest($serviceName, $bodyXml);
        return $result;
    }

    //处理请求并封装返回值
    public function doRequest($serviceName, $bodyXml){
        $xml = $this->buildXml($serviceName, $bodyXml);
        $verifyCode = $this->bulidVerifyCode($xml,$this->keyword);

        //数据请求,并校验数据结果返回
        $resXml = $this->curlpost($this->url, $xml, $verifyCode);
        $res = $this->LoadXml($resXml);
        if(!is_array($res)){
            return false;
        }
        if( !isset($res['Head']) ){
            return false;
        }
        if($res['Head'] != 'OK'){
            return false;
        }
        $list = array(
            'OrderResponse','RouteResponse','ReturnFreightResponse'
        );
        foreach($list as $k=>$v){
            if(array_key_exists($v,$res['Body'])){
                if($v == 'RouteResponse'){
                    return  isset($res['Body'][$v]['Route'])?$res['Body'][$v]['Route']:array();
                }else{
                    return  $res['Body'][$v]['attributes'];
                }
            }
        }
    }

    //CURL请求接口
    public function curlpost($url, $xml, $verifyCode){

        $content = '[start][data]['.date('Y-m-d H:i:s').']shufengbsp num:【'.$xml.'】[end]'.PHP_EOL;
        file_put_contents('shufengbsp.error.log', $content , FILE_APPEND );

        $params = array(
            'xml'        => $xml,
            'verifyCode' => $verifyCode
        );
        $parambody =  http_build_query($params, '', '&');
        $curlObj = curl_init();
        curl_setopt($curlObj, CURLOPT_URL, $url); // 设置访问的url
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1); //curl_exec将结果返回,而不是执行
        curl_setopt($curlObj, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded;charset=UTF-8"));
        curl_setopt($curlObj, CURLOPT_URL, $url);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curlObj, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);

        curl_setopt($curlObj, CURLOPT_CUSTOMREQUEST, 'POST');

        curl_setopt($curlObj, CURLOPT_POST, true);
        curl_setopt($curlObj, CURLOPT_POSTFIELDS, $parambody);
        curl_setopt($curlObj, CURLOPT_ENCODING, 'gzip');

        $res = @curl_exec($curlObj);
        curl_close($curlObj);

        if ($res === false) {
            $errno = curl_errno($curlObj);
            if ($errno == CURLE_OPERATION_TIMEOUTED) {
                $msg = "Request Timeout:   seconds exceeded";
            } else {
                $msg = curl_error($curlObj);
            }
            echo $msg;
            $e = new XN_TimeoutException($msg);
            throw $e;
        }
        return $res;
    }

    //xml数据封装
    public function buildXml($serviceName, $bodyData){
        $xml =  '<Request service="'. $serviceName .'Service" lang="zh-CN">' .
                '<Head>' . $this->apicode .'</Head>'.
                '<Body>' . $bodyData . '</Body>' .
                '</Request>';
        return $xml;
    }

    //计算校验码
    public function bulidVerifyCode($xml, $keyword){
        $string = trim($xml).trim($keyword);
        $md5 = md5(mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string)), true);
        $sign = base64_encode($md5);
        return $sign;
    }

    protected function LoadXml($xml) {
        $obj = new DOMDocument();
        $obj->loadXML($xml);
        $ret = $this->xmlToArray($obj->documentElement);
        return $ret;
    }

    //xml转到array
    public function xmlToArray($root){
        $result = array();

        if ($root->hasAttributes()) {
            $attrs = $root->attributes;
            foreach ($attrs as $attr) {
                $result['attributes'][$attr->name] = $attr->value;
            }
        }

        if ($root->hasChildNodes()) {
            $children = $root->childNodes;
            if ($children->length == 1) {
                $child = $children->item(0);
                if ($child->nodeType == XML_TEXT_NODE) {
                    $result['_value'] = $child->nodeValue;
                    return count($result) == 1
                        ? $result['_value']
                        : $result;
                }
            }
            $groups = array();
            foreach ($children as $child) {
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = self::xmlToArray($child);
                } else {
                    if (!isset($groups[$child->nodeName])) {
                        $result[$child->nodeName] = array($result[$child->nodeName]);
                        $groups[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = self::xmlToArray($child);
                }
            }
        }

        return $result;
    }

}
