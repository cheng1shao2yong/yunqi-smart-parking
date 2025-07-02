<?php
declare(strict_types=1);

namespace app\common\library;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingInvoice;
use app\common\model\PayUnion;

class Invoice
{
    const PRIVATE_KEY="MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALIu6u+yDJoYNv+p0KfY7EhCe2cSHClIvGUPf/GdPILDeiSvP4Zu9roeev9SHcmkUXY3TcF1AM9IsrkEV7sYxs0dRsa4/nLrp4ryC0Ljs6XJ5pWV5oft9jwKGVvrXhvT4vAg82/V71fI3UYJiAxXAPqKc/XbUv+Ikbj+yNhX7yr7AgMBAAECgYAooaNIOmk+QFRWU4J1q/UheyuJsusCDQiM4Q5zacARL4MsV1wcizQu9+FjVHXfc91xoCXWfhhLrE+4+gEJdJpsbOfQBmrXleH2XRStmx/NWpM6U5Cf9D8SGf8LDUAfxQRO4OifgnufKWmVw9tvfy1rW8BDcW+qLlK5TYzxHZlpfQJBAMfNFkYLKUf2y5WYN2WAl4YB8fwSaKw7BIHxng5gLzgzuYw44vadOdhmEgjD8L0BrKDR7aUk0lNrm43DZ5lvIoUCQQDkTTZXlCxKsvHKj4U48+osntyLcuIP1vHLVjkkpwXuuJOaG4Zeozmp6Ut2rWMm3h9xx8zuAEZNp7FujOKFbk9/AkALbrrFi0HzcjW3+5u/3RnOezIDgHIo2L+rR84JeIt7JcqKMX8bKWfz7/M8OSn/RqdAFIMKng7C4zdlZzb4WpmZAkEAiOAyAHf5xh9bD9LUUL0MXQwxtfl+x3hbJ0neoLbyu5k2BWMTQJIj9FbGw7RYiekKeTENEHRhshUkL9+Pv+fPCQJBAJ3ybAdqnOgtTcXFIe4Ikr8Ma5F0H1ngP0a7/9Vx0QQrMv7ZNAojXtDcO32qKVz8a2QdaAFWDOXCEIMGkgdO2+A=";
    //const PUBLIC_KEY="MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCyLurvsgyaGDb/qdCn2OxIQntnEhwpSLxlD3/xnTyCw3okrz+Gbva6Hnr/Uh3JpFF2N03BdQDPSLK5BFe7GMbNHUbGuP5y66eK8gtC47OlyeaVleaH7fY8Chlb614b0+LwIPNv1e9XyN1GCYgMVwD6inP121L/iJG4/sjYV+8q+wIDAQAB";
    const PUBLIC_KEY="MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCJkx3HelhEm/U7jOCor29oHsIjCMSTyKbX5rpoAY8KDIs9mmr5Y9r+jvNJH8pK3u5gNnvleT6rQgJQW1mk0zHuPO00vy62tSA53fkSjtM+n0oC1Fkm4DRFd5qJgoP7uFQHR5OEffMjy2qIuxChY4Au0kq+6RruEgIttb7wUxy8TwIDAQAB";
    //测试地址
    const URL='http://fpkj.testnw.vpiaotong.cn/';
    //正式地址
    //const URL='https://fpkj.vpiaotong.com/';
    //测试平台编码
    const PLATFORMCODE='yn3bR980';
    const PLATFORMSHORT='GKZB';
    const DES_KEY='4jM5Oes4EcUOn44E31sMWhL2';

    //开票
    public static function doInvoice(
        Parking $parking,
        ParkingInvoice $invoice,
        $buyerName,
        $buyerTaxpayerNum,
        $buyerAddress='',
        $buyerTel='',
        $buyerBankName='',
        $buyerBankAccount='',
        $naturalPerson=false,
        $invoiceType='82'){
        $invoiceTypes=[
            '81'=>'数电增值税专用发票',
            '82'=>'数电增值税普通发票',
        ];
        if(!isset($invoiceTypes[$invoiceType])){
            throw new \Exception('发票类型错误');
        }
        $uri='tp/openapi/invoiceBlue.pt';
        $pay=PayUnion::whereIn('id',explode(',',$invoice->pay_id))->select();
        $itemList=[];
        $realEstateRentalList=[];
        $invoiceItemAmount=0;
        foreach ($pay as $p){
            $itemList[]=[
                'goodsName'=>$p->detail,
                'taxClassificationCode'=> '3040502020200000000',
                'invoiceAmount'=> $p->pay_price,
                'quantity'=> '1',
                'unitPrice'=> $p->pay_price,
                'taxRateValue'=> '0.05'
            ];
            $realEstateRentalList[]=[
                'region'=>str_replace(',','',$parking->area->mergename),
                'detailedAddress'=>$parking->address,
                'areaUnit'=>'平方米',
                'crossCitySign'=>'0',
                'leaseTerm'=>date('Y-01-01').' '.date('Y-12-31'),
            ];
            $invoiceItemAmount+=(float)$p->pay_price;
        }
        $invoiceItemAmount=number_format($invoiceItemAmount,2,'.','');
        $data=[
            'taxpayerNum'=>'500102201007206608',
            'invoiceReqSerialNo'=>self::PLATFORMSHORT.uniqid().str_rand(3),
            'invoiceIssueKindCode'=>$invoiceType,
            'buyerName'=>$buyerName,
            'buyerTaxpayerNum'=>$buyerTaxpayerNum,
            'naturalPersonFlag'=>$naturalPerson?'1':'0',
            'buyerAddress'=>$buyerAddress,
            'buyerTel'=>$buyerTel,
            'buyerBankName'=>$buyerBankName,
            'buyerBankAccount'=>$buyerBankAccount,
            'invoiceItemAmount'=>$invoiceItemAmount,
            'itemList'=>$itemList,
            'realEstateRentalList'=>$realEstateRentalList
        ];
        $package=self::package($data);
        $response=Http::post(self::URL.$uri,$package,'',['Content-Type: application/json','Content-Length: '.strlen($package)]);
        if($response->isSuccess()){
            if($response->content['code']=='0000'){
                $result=json_decode(self::decrypt($response->content['content']),true);
                $result['qrCodePath']=base64_decode($result['qrCodePath']);
                return $result;
            }
            throw new \Exception($response->content['msg']);
        }else{
            throw new \Exception($response->errorMsg);
        }
    }

    private static function package($data){
        $result=[
            'platformCode'=>self::PLATFORMCODE,
            'signType'=>'RSA',
            'format'=>'JSON',
            'timestamp'=>date('Y-m-d H:i:s',time()),
            'version'=>'1.0',
            'serialNo'=>self::PLATFORMSHORT.date('YmdHis').strtoupper(str_rand(8)),
            'content'=>self::encrypt(json_encode($data,JSON_UNESCAPED_UNICODE)),
        ];
        $result['sign']=self::sign($result);
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    private static function sign($data){
        ksort($data);
        $str='';
        foreach ($data as $k=>$v){
            $str.=$k.'='.$v.'&';
        }
        //去掉最后一个&
        $str=substr($str,0,-1);
        $privateString=self::PRIVATE_KEY;
        $private_key = <<<EOT
-----BEGIN RSA PRIVATE KEY-----
{$privateString}
-----END RSA PRIVATE KEY-----
EOT;
        $signature = '';
        if (!openssl_sign($str, $signature, $private_key, 'SHA1')) {
            throw new \Exception('Signature failed');
        }
        $r=base64_encode($signature);
        return $r;
    }

    //3重des加密，加密模式为ECB，编码为utf-8
    private static function encrypt($data)
    {
        $encrypted = openssl_encrypt($data, 'DES-EDE3', self::DES_KEY, OPENSSL_RAW_DATA);
        return base64_encode($encrypted);
    }
    //3重des解密，解密模式为ECB，编码为utf-8
    private static function decrypt($data)
    {
        $decrypted = openssl_decrypt(base64_decode($data), 'DES-EDE3', self::DES_KEY, OPENSSL_RAW_DATA);
        return $decrypted;
    }
}