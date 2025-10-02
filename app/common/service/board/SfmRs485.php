<?php
declare(strict_types=1);

namespace app\common\service\board;

use app\common\model\manage\Parking;
use app\common\model\parking\ParkingBarrier;
use app\common\model\parking\ParkingPlate;
use app\common\model\parking\ParkingRecords;
use app\common\model\parking\ParkingRecordsPay;
use app\common\model\parking\ParkingRules;
use app\common\service\BoardService;

/**
 * 赛菲姆-RS485主板
 */
class SfmRs485 extends BoardService
{
    /**
     * 协议固定头部（十六进制）
     */
    private const FIXED_HEADER = 'A661';
    /**
     * 单包最大数据长度（字节）- 文档规定超过200字节分包
     */
    private const MAX_SINGLE_PACK_DATA_LEN = 200;

    //入场语音
    public static function entryVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //入场显示
    public static function entryDisplay(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //请缴费语音
    public static function payVoice(ParkingPlate $plate,ParkingRecordsPay $recordsPay){
        throw new \Exception('暂不支持此功能');
    }

    //免费离场语音
    public static function freeLeaveVoice(ParkingBarrier $barrier,ParkingPlate $plate,string $rulesType){
        throw new \Exception('暂不支持此功能');
    }

    //免费离场显示
    public static function freeLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType){
        $data=[
            'text'=>$plate->plate_number.'\n'.ParkingRules::RULESTYPE[$rulesType].'\n免费离场',
            'cmd'=>'customQRCode',
            'voice'=>'请通行',
            'same'=>'0',
            'time'=>'50'
        ];
        $parkId=rand(0,255);
        $dataStream = self::packData($data, $parkId);
        foreach ($dataStream as $dataPart)
        {
            return $dataPart;
        }
    }

    //请缴费显示
    public static function paidLeaveDisplay(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,ParkingRecordsPay $recordsPay,string $rulesType){
        $url=get_domain('api');
        $qrcode=$url.'/qrcode/exit?serialno='.$barrier->serialno;
        $fee=formatNumber($recordsPay->pay_price);
        $string=$fee.'元';
        $data=[
            'text'=>$plate->plate_number.'，请支付：'.$string,
            'cmd'=>'customQRCode',
            'voice'=>'请扫码付款',
            'same'=>'0',
            'time'=>'50',
            'qrcode'=>$qrcode
        ];
        $parkId=rand(0,255);
        $dataStream = self::packData($data, $parkId);
        foreach ($dataStream as $dataPart)
        {
            return $dataPart;
        }
    }

    //开闸异常显示
    public static function openGateExceptionDisplay(ParkingBarrier $barrier,string $message){
        throw new \Exception('暂不支持此功能');
    }

    //余额不足语音
    public static function insufficientBalanceVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //支付成功语音
    public static function paySuccessVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //支付成功显示
    public static function paySuccessScreen(ParkingBarrier $barrier,ParkingPlate $plate,ParkingRecords $records,string $rulesType){
        $data=[
            'text'=>'支付成功',
            'cmd'=>'customQRCode',
            'voice'=>'支付成功，请通行',
            'same'=>'0',
            'time'=>'50'
        ];
        $parkId=rand(0,255);
        $dataStream = self::packData($data, $parkId);
        foreach ($dataStream as $dataPart)
        {
            return $dataPart;
        }
    }

    //禁止通行语音
    public static function noEntryVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //人工确认语音
    public static function confirmVoice(string $plate_number){
        throw new \Exception('暂不支持此功能');
    }

    //人工确认显示
    public static function confirmDisplay(ParkingBarrier $barrier,string $plate_number){
        throw new \Exception('暂不支持此功能');
    }

    //设置广告
    public static function setAdvertisement(int $line,string $text){
        throw new \Exception('暂不支持此功能');
    }

    //设置音量
    public static function setVolume(int $step,int $voice){
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行显示
    public static function noEntryRecordDisplay(ParkingBarrier $barrier){
        throw new \Exception('暂不支持此功能');
    }

    //无入场记录放行语音
    public static function noEntryRecordVoice(){
        throw new \Exception('暂不支持此功能');
    }

    //内场放行显示
    public static function insidePassDisplay(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示出场付款码
    public static function showPayQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示无牌车入场二维码
    public static function showEntryQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //显示无牌车出场二维码
    public static function showExitQRCode(ParkingBarrier $barrier)
    {
        throw new \Exception('暂不支持此功能');
    }

    //无牌车语音
    public static function noPlateVoice()
    {
        throw new \Exception('暂不支持此功能');
    }

    //无牌车显示
    public static function noPlateDisplay(ParkingBarrier $barrier,string $type)
    {
        throw new \Exception('暂不支持此功能');
    }

    /**
     * 打包485协议数据
     *
     * @param array $params 协议参数，需包含以下键（除cmd外均为可选）：
     *        - cmd: string 命令标识（必填，固定为"customQRCode"）
     *        - text: string 屏幕显示文字（可选，支持\n换行）
     *        - voice: string 语音播报内容（可选）
     *        - same: string 语音与文字是否相同（可选，1=相同，0=不同）
     *        - time: string 界面倒计时（可选，如"50"）
     *        - qrcode: string 二维码内容（可选，如URL）
     * @param int $packId 分包标识（1字节，范围0-255，同一组分包需相同）
     * @return array 打包后的协议数据包数组（每个元素为1个完整数据包的十六进制字符串）
     * @throws InvalidArgumentException 当参数不合法时抛出异常
     */
    public static function packData(array $params, int $packId): array
    {
        // 2. 过滤空参数，组装JSON数据（保留有值的字段）
        $jsonData = self::buildJsonData($params);
        // 3. 将JSON字符串转换为GB2312编码（协议要求）
        $gb2312Data = mb_convert_encoding($jsonData, 'GB2312', 'UTF-8');

        // 4. 分包处理（超过200字节拆分）
        $dataParts = self::splitDataIntoParts($gb2312Data);
        $totalNum = count($dataParts); // 分包总数
        $packets = [];

        // 5. 为每个分包组装完整协议包
        foreach ($dataParts as $eachSn => $dataPart) {
            // 分包序号从0开始（文档规定）
            $currentEachSn = $eachSn;
            // 当前分包数据长度（1字节，范围0-255）
            $dataLength = strlen($dataPart);
            // 计算当前分包数据的异或校验
            $crc = self::calculateXorCrc($dataPart);

            // 组装协议头（十六进制）：固定头部 + packId + totalNum + eachSn + dataLength
            $headerHex = self::buildHeaderHex($packId, $totalNum, $currentEachSn, $dataLength);

            // 组装完整数据包：头部 + 数据（十六进制） + CRC（十六进制）
            $dataHex = bin2hex($dataPart); // 将GB2312二进制数据转为十六进制
            $fullPacketHex = $headerHex . $dataHex . $crc;
            $packets[] = self::stringTohex($fullPacketHex);
        }
        return  $packets;
    }

    private static function stringTohex(string $hexString): string
    {
        // 每两个字符分割一次，得到每个十六进制字节
        $bytes = str_split($hexString, 2);

        // 将每个十六进制字符串转换为十进制，并加上 0x 前缀表示法
        $result = [];
        foreach ($bytes as $byte) {
            $result[] = hexdec($byte);
        }
        $dataStream = pack('C*', ...$result);
        return $dataStream;
    }

    /**
     * 组装JSON数据（过滤空值，保留有效字段）
     *
     * @param array $params 输入参数
     * @return string 格式化后的JSON字符串
     */
    private static function buildJsonData(array $params): string
    {
        $validParams = array_filter($params, function ($value) {
            return $value !== null && $value !== '';
        });
        return json_encode($validParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 数据分包处理（按200字节拆分）
     *
     * @param string $gb2312Data GB2312编码的二进制数据
     * @return array 分包后的数组（每个元素为单个分包的二进制数据）
     */
    private static function splitDataIntoParts(string $gb2312Data): array
    {
        $dataLen = strlen($gb2312Data);
        $parts = [];

        // 无需分包：直接返回单个包
        if ($dataLen <= self::MAX_SINGLE_PACK_DATA_LEN) {
            $parts[] = $gb2312Data;
            return $parts;
        }

        // 需要分包：按200字节拆分
        $offset = 0;
        while ($offset < $dataLen) {
            $part = substr($gb2312Data, $offset, self::MAX_SINGLE_PACK_DATA_LEN);
            $parts[] = $part;
            $offset += self::MAX_SINGLE_PACK_DATA_LEN;
        }

        return $parts;
    }

    /**
     * 计算数据的异或校验（CRC）
     * 规则：对data字段的每个字节进行异或运算，结果转为2位十六进制（大写）
     *
     * @param string $data GB2312编码的二进制数据
     * @return string 2位十六进制的CRC校验值（大写）
     */
    private static function calculateXorCrc(string $data): string
    {
        $crc = 0;
        $dataLen = strlen($data);

        // 遍历每个字节进行异或运算
        for ($i = 0; $i < $dataLen; $i++) {
            $byte = ord($data[$i]); // 获取当前字节的ASCII值
            $crc ^= $byte; // 异或运算
        }

        // 转为2位十六进制（不足2位补0，大写）
        return str_pad(dechex($crc), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 组装协议头（十六进制格式）
     * 格式：固定头部（A661） + packId（1字节） + totalNum（1字节） + eachSn（1字节） + dataLength（1字节）
     *
     * @param int $packId 分包标识（0-255）
     * @param int $totalNum 分包总数（0-255）
     * @param int $eachSn 分包序号（0-255）
     * @param int $dataLength 当前分包数据长度（0-255）
     * @return string 十六进制格式的协议头（大写）
     * @throws InvalidArgumentException 当参数超出1字节范围时抛出异常
     */
    private static function buildHeaderHex(int $packId, int $totalNum, int $eachSn, int $dataLength): string
    {
        // 验证参数是否在1字节范围内（0-255）
        $validParams = [
            'packId' => $packId,
            'totalNum' => $totalNum,
            'eachSn' => $eachSn,
            'dataLength' => $dataLength
        ];

        foreach ($validParams as $name => $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidArgumentException("{$name}必须为0-255的整数，当前值：{$value}");
            }
        }

        // 将每个参数转为2位十六进制（不足补0，大写）
        $packIdHex = str_pad(dechex($packId), 2, '0', STR_PAD_LEFT);
        $totalNumHex = str_pad(dechex($totalNum), 2, '0', STR_PAD_LEFT);
        $eachSnHex = str_pad(dechex($eachSn), 2, '0', STR_PAD_LEFT);
        $dataLengthHex = str_pad(dechex($dataLength), 2, '0', STR_PAD_LEFT);

        // 组装并返回头部（固定头部 + 各参数十六进制）
        return strtoupper(
            self::FIXED_HEADER . $packIdHex . $totalNumHex . $eachSnHex . $dataLengthHex
        );
    }
}