<?php
/*
 * 基于文件的访问量统计系统
 * 系统分为三部分
   第一部分是访问日志存放文件，每分钟一个文件，每次访问一行，目前的格式为“日期 时间 ip"，
   为保证安全，使用3个文件来保存访问日志，每分钟一个，循环使用，文件名为:stat.[0,1,2].list}。
   使用方法:
   require_once "Stat.php"
   Stat::add('/tmp/stat','191.168.1.1');
   
   第二部分是统计部分，统计使用定期执行的方式，linux使用crontab执行，每分钟一次
   统计将前一分钟的访问信息取出来，统计行数，放入统计文件，统计文件使用json格式保存。格式为分钟，小时，日期方式保存，详细讲initdata
   使用3个文件来保存访问日志，每分钟一个，循环使用，文件名为
   使用方法：
   crontab 中用php执行如下代码:
   require_once "Stat.php"
   Stat::stat('/workdir/vistor.json','/tmp/stat');
   第三部分是展现部分，展现使用echarts做为展现系统，将后台json数据通过曲线的方式展现出来
 */
 /*
 *接口
 */
interface IHandler{
        public function write($ip);
}
/*
 *接口实现
 */
class FileHandler implements IHandler{
        private $handle = null;

        public function __construct($file = '') {
                $this->handle = fopen($file,'a');
        }

        public function write($ip) {
                fwrite($this->handle, $ip, 4096);
        }

        public function __destruct() {
                fclose($this->handle);
        }
}
/*
 * 功能实现
 */
class Stat{
        private $handler = null;
        private static $instance = null;
        private function __construct(){}
        private function __clone(){}

        public static function Init($listfilepath){
				if(empty($listfilepath)){
					return;
				}
                if(!self::$instance instanceof self){
                        self::$instance = new self();
						//计算当前文件
						$a = date('i');
						$num = intval($a)%3;
						$handler = new FileHandler($listfilepath.'/stat.$num.list');
                        self::$instance->__setHandle($handler);
                }
                return self::$instance;
        }

		/*
		 *  新增一个访问量
		 */
        public static function add($listFilePath,$ip){
                $a = date('i');
                $iNum = intval($a);
                $num = intval($a)%3;
				$lstnum = ($iNum>0?($iNum-1):59)%3;

				self::Init($listFilePath);
                if(empty(self::$instance->handler)){
                        return;
                }
                self::$instance->write($ip);
        }

        private function __setHandle($handler){
                $this->handler = $handler;
        }

		/*
		* 初始化数据
		*/
        private static function initdata(){
                $day = Date('d');
                $hh = Date('H');
                $mm = Date('i');
                $ss = Date('s');
                return array("pos"=>array($hh,$mm,$ss,$day),"last"=>0,
                        "mm"=>array(
                        "00"=>0,"01"=>0,"02"=>0,"03"=>0,"04"=>0,"05"=>0,"06"=>0,"07"=>0,"08"=>0,"09"=>0,
                        "10"=>0,"11"=>0,"12"=>0,"13"=>0,"14"=>0,"15"=>0,"16"=>0,"17"=>0,"18"=>0,"19"=>0,
                        "20"=>0,"21"=>0,"22"=>0,"23"=>0,"24"=>0,"25"=>0,"26"=>0,"27"=>0,"28"=>0,"29"=>0,
                        "30"=>0,"31"=>0,"32"=>0,"33"=>0,"34"=>0,"35"=>0,"36"=>0,"37"=>0,"38"=>0,"39"=>0,
                        "40"=>0,"41"=>0,"42"=>0,"43"=>0,"44"=>0,"45"=>0,"46"=>0,"47"=>0,"48"=>0,"49"=>0,
                        "50"=>0,"51"=>0,"52"=>0,"53"=>0,"54"=>0,"55"=>0,"56"=>0,"57"=>0,"58"=>0,"59"=>0),
                        "hh"=>array(
                        "00"=>0,"01"=>0,"02"=>0,"03"=>0,"04"=>0,"05"=>0,"06"=>0,"07"=>0,"08"=>0,"09"=>0,
                        "10"=>0,"11"=>0,"12"=>0,"13"=>0,"14"=>0,"15"=>0,"16"=>0,"17"=>0,"18"=>0,"19"=>0,
                        "20"=>0,"21"=>0,"22"=>0,"23"=>0),
                        "days"=>array(
                        "00"=>0,"01"=>0,"02"=>0,"03"=>0,"04"=>0,"05"=>0,"06"=>0,"07"=>0,"08"=>0,"09"=>0,
                        "10"=>0,"11"=>0,"12"=>0,"13"=>0,"14"=>0,"15"=>0,"16"=>0,"17"=>0,"18"=>0,"19"=>0,
                        "20"=>0,"21"=>0,"22"=>0,"23"=>0,"24"=>0,"25"=>0,"26"=>0,"27"=>0,"28"=>0,"29"=>0,
                        "30"=>0,"31"=>0)
                );
        }
		
		/*
		 * 统计部分并写入json
		 */
		 public static function stat($jsonFile,$listFilePath){
			    //当前时间
                $time = time();
                $day = Date('d',$time);
                $hh = Date('H',$time);
                $mm = Date('i',$time);
                $ss = Date('s',$time);

                $iNum = intval($mm);
				//上一分钟的文件
                $lstnum = ($iNum>0?($iNum-1):59)%3;

                //统计行数
                $lines = count(file($listFilePath."/stat.$lstnum.list"));

                //打开统计文件
                if(file_exists($jsonFile)) {
                        $content = file_get_contents($jsonFile);
                }
                if(!empty($content)){
                        $data = json_decode($content,TRUE);
                }else{
                        $data = self::initdata();
                }

                if(empty($data)){
                        return false;
                }
				//最后一次处理时间
                $lsthh = $data['pos'][0];
                $lstmm = $data['pos'][1];
                $lstss = $data['pos'][2];
                $lstday = $data['pos'][3];
                $last = $data['last'];

				//时间差
                $dif = $time - $last;
				//更新上次到当前时间内的字段
                if($last>0 && $dif >= 31*24*3600){
                        foreach($data['days'] as $i=>$v){
                                $s = sprintf('%02d',$i);
                                $data['days'][$s] = 0;
                        }
                }

                if($last>0 && $dif >= 1*24*3600){
                        foreach($data['hh'] as $i=>$v){
                                $s = sprintf('%02d',$i);
                                $data['hh'][$s] = 0;
                        }
						        }
                }
                if($last>0 && $dif >= 1*3600){
                        foreach($data['mm'] as $i=>$v){
                                $s = sprintf('%02d',$i);
                                $data['mm'][$s] = 0;
                        }
                }
                if($last>0 && $dif >= 60){
                        $i = intval($lstmm);
                        while($i!=intval($mm)){
                                $i++; $i = $i%60;
                                $s = sprintf('%02d',$i);
                                $data['mm'][$s] = 0;
                        }
                        $data['mm'][$mm] = $lines;
                        if($lsthh != $hh || $dif > 1*3600){
                                $i = intval($lsthh);
                                while($i!=intval($hh)){
                                        $i++; $i = $i%24;
                                        $s = sprintf('%02d',$i);
                                        $data['hh'][$s] = 0;
                                }
                                $data['hh'][$hh] = $lines;
                        }else{
                                $num = $data['hh'][$hh];
                                $num = $num + $lines;
                                $data['hh'][$hh] = $num;
                        }
                        if($lstday != $day || $dif > 1*24*3600){
                                $i = intval($lstday);
                                while($i!=intval($day)){
                                        $i++; $i = $i%31;
                                        $s = sprintf('%02d',$i);
                                        $data['days'][$s] = 0;
                                }
                                $data['days'][$day] = $lines;
                        }else{
                                $num = $data['days'][$day];
                                $num = $num + $lines;
                                $data['days'][$day] = $num;
                        }
                }
				
				//第一次
                if($last==0){
                        $data['mm'][$mm] = $lines;
                        $data['hh'][$hh] = $lines;
                        $data['days'][$day] = $lines;
                }

                //设置最后更新时间
                $data['pos'][0] = $hh;
                $data['pos'][1] = $mm;
                $data['pos'][2] = $ss;
                $data['pos'][3] = $day;
                $data['last'] = $time;

                //保存到文件
                $str = json_encode($data);
                file_put_contents($jsonfile,$str);
                //清空list文件
                file_put_contents($listFilePath."/stat.$lstnum.list","");
				return true;
        }

		//格式化
        protected function write($ip){
                $msg = '['.date('Y-m-d H:i:s').']['.$ip.']'."\n";
                $this->handler->write($msg);
        }
}