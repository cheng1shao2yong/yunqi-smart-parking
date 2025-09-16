<?php
declare(strict_types=1);

namespace app\common\service\diymode;

use app\common\model\parking\ParkingMode;

class Youleyuan extends ParkingDiyMode
{
    public string $title='黔东南铠鑫豪游乐园';

    public int $free_time=30;

    public float $day_top_fee=20;

    public function account(ParkingMode $mode,int $key,int $starttime,int $endtime,int &$rangetime)
    {
        if($rangetime){
            $starttime=$starttime+$rangetime;
            $rangetime=0;
        }
        $parking_time=$this->calculateParkingDuration($starttime, $endtime);
        $fee=0;
        if($key===0){
            if($parking_time<=60*60){
                $fee=5;
            }
            if($parking_time>1*60*60 && $parking_time<=2*60*60){
                $fee=8;
            }
            if($parking_time>2*60*60 && $parking_time<=3*60*60){
                $fee=10;
            }
            if($parking_time>3*60*60 && $parking_time<=4*60*60){
                $fee=12;
            }
            if($parking_time>4*60*60 && $parking_time<=5*60*60){
                $fee=14;
            }
            if($parking_time>5*60*60 && $parking_time<=6*60*60){
                $fee=16;
            }
            if($parking_time>6*60*60 && $parking_time<=7*60*60){
                $fee=18;
            }
            if($parking_time>7*60*60){
                $fee=20;
            }
        }else{
            $num=ceil($parking_time/60/60);
            $fee=$num*2;
        }
        $this->detail[]=['start_time' => $starttime, 'end_time' =>$endtime, 'fee' => $fee, 'mode' => $mode->title];
    }

    /**
     * 计算二十四小时内的停车时长（排除9:00-22:00时间段）
     *
     * @param int $entryTime 入场时间戳
     * @param int $exitTime 出场时间戳
     * @return int 停车时长（秒）
     */
    private function calculateParkingDuration($entryTime, $exitTime) {
        $totalDuration = 0;
        $currentTime = $entryTime;
        while ($currentTime < $exitTime) {
            // 获取当前时间的日期部分和时间部分
            $currentDate = date('Y-m-d', $currentTime);
            $nonParkingStart = strtotime($currentDate . ' 09:00:00');
            $nonParkingEnd = strtotime($currentDate . ' 22:00:00');
            // 如果当前时间在非计费时间段之前
            if ($currentTime < $nonParkingStart) {
                // 计算到非计费时间段开始或出场时间的时长
                $segmentEnd = min($nonParkingStart, $exitTime);
                $totalDuration += $segmentEnd - $currentTime;
                $currentTime = $segmentEnd;
            }
            // 如果当前时间在非计费时间段内
            elseif ($currentTime >= $nonParkingStart && $currentTime < $nonParkingEnd) {
                // 跳过非计费时间段
                $currentTime = min($nonParkingEnd, $exitTime);
            }
            // 如果当前时间在非计费时间段之后
            else {
                // 计算到第二天非计费时间段开始或出场时间的时长
                $nextNonParkingStart = strtotime(date('Y-m-d', $currentTime + 86400) . ' 09:00:00');
                $segmentEnd = min($nextNonParkingStart, $exitTime);
                $totalDuration += $segmentEnd - $currentTime;
                $currentTime = $segmentEnd;
            }
        }
        return $totalDuration;
    }
}