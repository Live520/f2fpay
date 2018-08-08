<?php
/**
 * Created by aimer.
 * User: aimer
 * Date: 2018/8/8
 * Time: 下午2:53
 */
namespace F2fPay;
use AlipayTradePrecreateContentBuilder;
use AlipayTradeService;
use ExtendParams;
use GoodsDetail;
use AopClient;

class QrPay
{
    private $config;
    public $debug = false;

    /**
     * QrPay constructor.
     * @param $config
     * @throws \Exception
     */
    public function __construct($config)
    {
        //Open DEBUG
        if ($this->debug) {
            if ($config == null)
                throw new \Exception('Can not find f2fpay config');
            foreach ($config as $k => $v) {
                if ($v == null) {
                    throw new \Exception('Lack of' . $k);
                }
            }

        }

        $this->config = $config;

    }

    /**
     * 返回一个状态&&二维码类
     * @param $subject
     * @param $amount
     * @param null $outTradeNo
     * @param null $body
     * @param null $operatorId
     * @param null $storeId
     * @param $qrPay
     * @return \AlipayF2FPrecreateResult
     * @throws \Exception
     */
    public function get_qrcode($subject, $amount, $outTradeNo = null, $body = null, $operatorId = null, $storeId = null, &$qrPay)
    {
        //获取支付宝接口配置
        $config = $this->config;

        //$timestamp
        /**************************请求参数**************************/
        // (必填) 商户网站订单系统中唯一订单号，64个字符以内，只能包含字母、数字、下划线，
        // 需保证商户系统端不能重复，建议通过数据库sequence生成，
        //$outTradeNo TODO://变为可选
        if (!$outTradeNo)
            $outTradeNo = mt_rand(100, 9999) . "F2fpay" . date('Ymdhis') . mt_rand(100, 1000);

        // (必填) 订单标题，粗略描述用户的支付目的。如“xxx品牌xxx门店当面付扫码消费”
        //TODO:$subject
        if (!$subject)
            $subject = '充值' . $amount . '元';

        // (必填) 订单总金额，单位为元，不能超过1亿元
        // 如果同时传入了【打折金额】,【不可打折金额】,【订单总金额】三者,则必须满足如下条件:【订单总金额】=【打折金额】+【不可打折金额】
        //充值金额
        $totalAmount = $amount;

        // (不推荐使用) 订单可打折金额，可以配合商家平台配置折扣活动，如果订单部分商品参与打折，可以将部分商品总价填写至此字段，默认全部商品可打折
        // 如果该值未传入,但传入了【订单总金额】,【不可打折金额】 则该值默认为【订单总金额】- 【不可打折金额】
        //String discountableAmount = "1.00"; //

        // (可选) 订单不可打折金额，可以配合商家平台配置折扣活动，如果酒水不参与打折，则将对应金额填写至此字段
        // 如果该值未传入,但传入了【订单总金额】,【打折金额】,则该值默认为【订单总金额】-【打折金额】
        $undiscountableAmount = "0.01";

        // 卖家支付宝账号ID，用于支持一个签约账号下支持打款到不同的收款账号，(打款到sellerId对应的支付宝账号)
        // 如果该字段为空，则默认为与支付宝签约的商户的PID，也就是appid对应的PID
        //$sellerId = "";

        // 订单描述，可以对交易或商品进行一个详细地描述，比如填写"购买商品2件共15.00元"
        if (!$body)
            $body = "充值" . $totalAmount . "元";

        //商户操作员编号，添加此参数可以为商户操作员做销售统计
        if (!$operatorId)
            $operatorId = "";

        // (可选) 商户门店编号，通过门店号和商家后台可以配置精准到门店的折扣信息，详询支付宝技术支持
        if (!$storeId)
            $storeId = "";

        // 业务扩展参数，目前可添加由支付宝分配的系统商编号(通过setSysServiceProviderId方法)，系统商开发使用,详情请咨询支付宝技术支持
        $providerId = ""; //系统商pid,作为系统商返佣数据提取的依据
        $extendParams = new \ExtendParams();
        $extendParams->setSysServiceProviderId($providerId);
        $extendParamsArr = $extendParams->getExtendParams();

        // 支付超时，线下扫码交易定义为5分钟
        $timeExpress = "5m";

        // 商品明细列表，需填写购买商品详细信息，
        $goodsDetailList = array();

        // 创建一个商品信息，参数含义分别为商品id（使用国标）、名称、单价（单位为分）、数量，如果需要添加商品类别，详见GoodsDetail
        $goods = new GoodsDetail();
        $goods->setGoodsId($totalAmount);
        $goods->setGoodsName("充值");
        $goods->setPrice($totalAmount);
        //数量为1
        $goods->setQuantity(1);
        //得到商品明细数组
        $goodsArr = $goods->getGoodsDetail();
        $goodsDetailList = array($goodsArr);

        //第三方应用授权令牌,商户授权系统商开发模式下使用
        $appAuthToken = "";//根据真实值填写

        // 创建请求builder，设置请求参数
        $qrPayRequestBuilder = new AlipayTradePrecreateContentBuilder();
        $qrPayRequestBuilder->setOutTradeNo($outTradeNo);
        $qrPayRequestBuilder->setTotalAmount($totalAmount);
        $qrPayRequestBuilder->setTimeExpress($timeExpress);
        $qrPayRequestBuilder->setSubject($subject);
        $qrPayRequestBuilder->setBody($body);
        $qrPayRequestBuilder->setUndiscountableAmount($undiscountableAmount);
        $qrPayRequestBuilder->setExtendParams($extendParamsArr);
        $qrPayRequestBuilder->setGoodsDetailList($goodsDetailList);
        $qrPayRequestBuilder->setStoreId($storeId);
        $qrPayRequestBuilder->setOperatorId($operatorId);

        $qrPayRequestBuilder->setAppAuthToken($appAuthToken);

        // 调用qrPay方法获取当面付应答
        $qrPay = new AlipayTradeService($config);

        $qrPayResult = $qrPay->qrPay($qrPayRequestBuilder);
        //其实只要提交上去就已经开始了轮询
        return $qrPayResult;
    }

    private function f2fpay_callback($call_back)
    {
        $aop = new AopClient();
        $alipayrsaPublicKey = $this->config;
        $aop->alipayrsaPublicKey = $alipayrsaPublicKey;

        //获取支付宝返回参数
        $arr = $_POST;
//        $j = json_encode($arr);
//        file_put_contents('../../storage/logs/post.log',$j);
        /**
         * {
         * "gmt_create": "2018-08-08 13:43:02",
         * "charset": "UTF-8",
         * "seller_email": "",
         * "subject": "￥0.1 - sspanel - aimer(aimerforreimu@gmail.com)",
         * "sign": "返回的签名",
         * "body": "$body",
         * "buyer_id": "支付宝返回的订单号",
         * "invoice_amount": "0.10",
         * "notify_id": "支付宝返回的通知好",
         * "fund_bill_list": "[{\"amount\":\"支付金额\",\"fundChannel\":\"ALIPAYACCOUNT\"}]",
         * "notify_type": "trade_status_sync",
         * "trade_status": "TRADE_SUCCESS",
         * "receipt_amount": "0.10",
         * "app_id": "2017070107620570",
         * "buyer_pay_amount": "0.10",
         * "sign_type": "RSA2",
         * "seller_id": "",
         * "gmt_payment": "2018-08-08 13:43:06",
         * "notify_time": "2018-08-08 13:43:06",
         * "version": "1.0",
         * "out_trade_no": "生成的订单号",
         * "total_amount": "0.10",
         * "trade_no": "",
         * "auth_app_id": "",
         * "buyer_logon_id": "",
         * "point_amount": ""
         * }
         */
        //调用验签的方法
        $result = $aop->rsaCheckV1($arr, $alipayrsaPublicKey, $_POST['sign_type']);
        if ($result) {//验证成功
            //系统订单号
            $out_trade_no = $_POST['out_trade_no'];
            //支付宝交易号
            $trade_no = $_POST['trade_no'];
            //交易状态
            $trade_status = $_POST['trade_status'];

            // 查询系统订单

            $alipayPID = $this->config['f2fpay_p_id'];
            if ($_POST['seller_id'] != $alipayPID) {
                return false;
            }

            //订单查询到，处理业务
            if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                return true;
            }
        }
    }


}