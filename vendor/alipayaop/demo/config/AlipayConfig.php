<?php

class AlipayConfig {

	//支付宝网关地址(默认沙箱环境，线上网关：https://openapi.alipay.com/gateway.do)
	public $gatewayUrl = "https://openapi.alipaydev.com/gateway.do";

	//应用id（默认沙箱通用appId，如需调试线上环境请换成自己线上的appId）
	public $appId = "2016051900098985";

	//支付宝公钥地址（默认沙箱通用公钥，如需调试线上环境请换成支付宝线上的公钥：https://docs.open.alipay.com/291/106103/）
	public $alipayrsaPublicKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0GqgS2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyqrXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/65zi3eof7TcZ32oWpwIDAQAB";

	//商户私钥地址（默认沙箱通用私钥，如需调试线上环境请换成线上的私钥：https://docs.open.alipay.com/291/106103/）
	public $rsaPrivateKey = "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAJlJ7tgZ4vI2Nnxt7DzznbhVwGN8INQ1s/ZnXYgtRMmvbNKLTHZ1SbRmiAKixn3TDbzkHMvo0jY7ldb7puqUJUKZuKfVwaRcAYgI6NamflqtTDWhSq+hPZ5ZrB36lx7N7AxlMD038WvJC5pHbld06DDxhlUslS3pJCGrB9P6HO0RAgMBAAECgYArrFTQXQ+70pZTfT4BX6dgDY5yybrQuzw6x9huI/elPsXSdr2iQmhtbYjyt022K5uOZa+OqRa7PN7EEY7M5sh2cFRX5P77o2vN61Gwklc11iaJIpPgUOZUmAG8jHnj3lf40+YtMwdPxQfbiZ36UOebQYPc8iuJczUNoVtSPP3IwQJBAMZzCSV7pjTQ4mp2MNT/h3/5ZhaQnqlO4wm0etekKDDTrpvUlSN8MjRjhyJhRvulKd0zUdfrjASEUjZhsZydEAMCQQDFviiKquR0TgYK0eircDwR89XHUBKoblPLYi/GSdPXSL92AzyvDIyNF/GPwHOkc1c+BA/4ocuW/T+u4KfYWBRbAkEAvV/Rfp98gDJFnmqjNt+SIqGQtj/T6KWLKxu7jkTsxYt7uOEoYPCHyE6iCkDiSAnY5Wmv1GjG+Rh8i8C2iUmomQJBAIaeVmtQvAaRt3tWO9e6qKpwHXF7Cbiwo0sqpOuRBy7gz7c/rOhe2rCTRFhg5FloTFRj35ucSkWYUupy9tFJ5VECQDyK++bn0ZpJG/HRNJHvuKOSUM8U6LSvQrTGpyvKlj5wLcOniDAYEcCzkapY/wHAUMshD0eoFY2X3F/3PcuIgW4=";

	//签名类型
	public $signType =  "RSA";

	//编码格式
	public $postCharset = "UTF-8";

	//返回数据格式
	public $format = "json";

	//异步通知回调地址
	public $notifyUrl;

	public $ALIPAY_DEMO = "alipay_demo";

    public $ALIPAY_DEMO_VERSION = "alipay_demo_PHP_20180828162221";

	public $token = NULL;

}
    