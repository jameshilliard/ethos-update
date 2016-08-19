<?php

require_once __DIR__ . '/EthosConf.php';
require_once __DIR__ . '/EthosFarmId.php';
require_once __DIR__ . '/MinerProcess.php';


function get_cpu_temp(){
        preg_match("/(?<=\+)(.*)(?=\....C\s)/", trim(`/usr/bin/sensors`),$matches);
        return $matches[0];
}

function strip_whitespace($string){

        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);
        return $string;
}

function miner_run_check(){

        $miner_instance = MinerProcess::countNumRunningInstances();
        $defunct = MinerProcess::isDefunct() ? 1 : 0;
        $allowed = MinerProcess::isAllowedToRun() ? 1 : 0;
        $overheated = MinerProcess::isOverheating() ? 1 : 0;

        return array($miner_instance,$defunct,$allowed,$overheated);
}

function NOTICE_log($string) {
    $date = trim(`date -u`);
    $log_write = $date." ".$string."\n";
    file_put_contents("/var/log/ethos-notice.log", $log_write, FILE_APPEND);
    $arg = escapeshellarg($string);
    `sudo wall "ETHOS NOTICE: $date $arg"`;
}

function echo_log($string){

        $date = trim(`date -u`);
        $log_write = $date." ".$string."\n";
        file_put_contents("/var/run/ethos-log.file",$log_write,FILE_APPEND);
}

function echo_config_log($string){

        $date = trim(`date -u`);
        $log_write = $date." ".$string."\n";
        file_put_contents("/var/log/ethos-config.log",$log_write,FILE_APPEND);
}

function get_http_response_code($url) {

        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
}

function get_reboot_number_from_conf(){

	$reboot_number = EthosConf::get("reboots");
	return $reboot_number;
}

function circuit_protect(){
        $uptime = trim(`cut -d " " -f1 /proc/uptime | cut -d "." -f 1`);
        if($uptime < 300 ){
        $sleep = mt_rand(15,50);
        echo "Sleeping for $sleep seconds before miner startup...\n";
        echo_log("Sleeping for $sleep seconds before miner startup...\n");
        sleep($sleep);
        }
}
function hash_status(){
    $hash_stuck = MinerProcess::isStuck() ? 1 : 0;
    list($miner_instance,$defunct,$allowed,$overheated) = miner_run_check();
    $hash = trim(`cat /var/run/hash.file`);
    $miner_hashes = trim(`cat /var/run/miner_hashes.file`);
    if($hash_stuck == 0 && $defunct == 0 && $miner_instance > 0 && $overheated == 0){
        `echo -n "Miner active: $hash (mhs)" > /var/run/status.file`;
    }
}
function make_motd(){
        $message_loc = "http://ethosdistro.com/message";
        file_put_contents("/opt/ethos/etc/message",trim(file_get_contents($message_loc)));
}
function putconf($interactive = "0"){                     

	`/usr/bin/dos2unix -q /home/ethos/remote.conf`;
	sleep(mt_rand(0,5)); //do not saturate webserver children with requests

	list($remote) = file("/home/ethos/remote.conf",FILE_IGNORE_NEW_LINES);
	$remote = trim($remote);

	$send_remote = substr($remote,0,150);

	file_put_contents("/var/run/send_remote.file",$send_remote);

	if(strlen($remote) == 0 || !eregi("http://|https://",$remote) || substr($remote, 0, 1) == "#"){
		$message = "REMOTE CONFIG DOES NOT EXIST OR IS FORMATTED INCORRECTLY. USING LOCAL CONFIG.";
                echo $message."\n";

		if(!$remote){
		`echo "notexist" > /var/run/config_mode.file`;
		} else {
		`echo "badformat" > /var/run/config_mode.file`;
		}

                echo_config_log($message);
		return;
	} else {
		ini_set('default_socket_timeout', 3);
		$header = get_http_response_code($remote);
	} 
	if($header != "200"){
		$message = "REMOTELY DEFINED CONFIG SERVER IS UNREACHABLE. USING LOCAL CONFIG.";
		echo $message."\n";
		`echo "unreachable" > /var/run/config_mode.file`;
		echo_config_log($message);
		return;
	} else {
     		$remote = trim($remote);
		$remote_contents = trim(file_get_contents($remote,FILE_IGNORE_NEW_LINES));
	}

	if(preg_match("/<[^<]+>/",$remote_contents,$m) != 0){
		$message = "REMOTE CONFIG CONTAINS HTML OR XML TAGS. USING LOCAL CONFIG.";
		echo $message."\n";
		`echo "invalid" > /var/run/config_mode.file`;
		echo_config_log($message);
		return;
	}

	if(md5($remote_contents) != md5(file_get_contents("/home/ethos/local.conf"))){
                $message = "IMPORTED REMOTE CONFIG INTO LOCAL CONFIG.";
                echo $message."\n";
                echo_config_log($message);
		file_put_contents("/home/ethos/local.conf",$remote_contents."\n");
	}
  `sudo /usr/bin/dos2unix -q /home/ethos/local.conf`;
  
 }

function check_proxy(){
  `sudo /opt/ethos/sbin/ethos-motd-generator`;
  $incorrect_result = trim(`tail -100 /var/run/miner.output | grep 'FAILURE: GPU gave incorrect result!' | wc -l`);
  if($incorrect_result > 0 ){
    `echo -n "1" > /var/run/ethos/incorrect_result.file`;
    echo_log("Warning: GPU gave incorrect result");
  }
  $requested_restart = trim(`tail -100 /var/run/proxy.output | grep 'Please restart proxy' | wc -l`);
  if($requested_restart > 0 ){
    `echo -n "restart" > /var/run/proxy_error.file`;
    echo_log("Warning: Proxy timed out attempting to get new work.  Restarting proxy...");
  }
  $primary_pool_offline = trim(`tail -100 /var/run/proxy.output | grep 'must be online' | wc -l`);
  if($primary_pool_offline > 0 ){
    `echo -n "primary_down" > /var/run/proxy_error.file`;
    echo_log("Warning: Primary pool is offline.  Restarting proxy... If this continues check your connection to your pool.");
  }
  $rejected_shares = trim(`tail -100 /var/run/proxy.output | grep -c "REJECTED"`);
  if($rejected_shares > 2 ){
    `echo -n "rejected" > /var/run/proxy_error.file`;
    echo_log("Warning: Proxy is generating rejected shares, Restarting proxy...");
  }
  if($requested_restart > 0 || $primary_pool_offline > 0 || $rejected_shares > 2 ){
    `echo -n "" > /var/run/proxy.output`;
    `killall -9 python`;
    `su - ethos -c '/opt/eth-proxy/eth-proxy.py >> /var/run/proxy.output 2>&1 &'`;
  }
   $nextboot = EthosConf::get("nextboottype");
   $driverless = EthosConf::get("driverless");
   $manualdriverless = trim(file_get_contents("/var/run/ethos/driverless.file"));
   if ($driverless == "enabled" && $nextboot == 0 ) {
       `/usr/sbin/grub-reboot 4`;
       echo_log("Machine now set to boot driverless, reboot to enter driverless mode.");
   if($interactive == "1") echo "Machine now set to boot driverless, reboot to enter driverless mode.\n";
   }
   if (!$driverless && $nextboot == 4 && !$manualdriverless) {
    `/usr/sbin/grub-reboot 0`;
    echo_log("Machine now set to normal boot, reboot to resume normal operation.");
   if($interactive =="1") echo "Machine now set to normal boot, reboot to resume normal operation.\n";
   }
}
function stratum_phoenix(){
        `ps uax| grep "python /opt/eth-proxy/eth-proxy.py" | grep -v grep | awk '{print $2}' | xargs kill -9 2> /dev/null`;
        `su - ethos -c '/opt/eth-proxy/eth-proxy.py >> /var/run/proxy.output 2>&1 &'`;
}

function get_stats(){

	list($miner_instance,$defunct,$allowed,$overheated) = miner_run_check();

	$gpus = trim(file_get_contents("/tmp/gpucount.file"));

    $hash_stuck = MinerProcess::isStuck() ? 1 : 0;

	//miner check info
        $send['miner_instance'] = $miner_instance;
        $send['defunct'] = $defunct;
        $send['allowed'] = $allowed;
        $send['overheat'] = $overheated;
        $send['hash_stuck'] = $hash_stuck;
        $send['outofmemory'] = trim(`tail -30 /var/log/kern.log | grep 'Out of memory' | wc -l`);
        $send['pool_info'] = trim(`cat /home/ethos/local.conf | grep -v '^#' | egrep -i 'pool|wallet|proxy'`);

	//system related info
        $send['uptime'] = trim(`cat /proc/uptime | cut -d"." -f1`);
        $send['mac'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g'`);
        $send['hostname'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g' | tail -c 7`);
        $send['rack_loc'] = EthosConf::get("loc");
        $send['ip'] = trim(`/sbin/ifconfig | grep inet | head -1 | cut -d":" -f2 | cut -d" " -f1`);
    	$send['mobo'] = trim(file_get_contents("/tmp/motherboard.file"));
    	$send['lan_chip'] = trim(`/usr/bin/lspci -v | grep -Poi "(?<=Ethernet\scontroller\:\s)(.*)"`);
        $send['load'] = trim(`cat /proc/loadavg | cut -d" " -f2`);
        $send['ram'] = trim(`/usr/bin/free | head -2 | tail -1 | awk '{print \$2/1024/1024}' OFMT="%3.0f" | awk '{print \$1}'`);
        $send['cpu_temp'] = trim(file_get_contents("/var/run/cputemp.file"));
        $send['cpu_name'] = trim(`cat /proc/cpuinfo | grep 'model name' | awk -F": " '{print \$2}'`);
        $send['rofs'] = time()-trim(file_get_contents("/opt/ethos/etc/check-ro.file"));
        $send['drive_name'] = EthosConf::get("driveinfo");
        $send['freespace'] = round(trim(`/bin/df | grep '/dev/' | head -1 | awk '{print $4}'`)/1024/1024,1);
        $send['temp'] = trim(file_get_contents("/var/run/temp.file"));
        $send['version'] = trim(file_get_contents("/opt/ethos/etc/version"));
    	$send['miner_secs'] = 0 + trim(`ps -eo pid,comm,etime | grep ethminer | head -1 | awk '{print \$NF}' |  /opt/ethos/bin/convert_time.awk`);
	$send['adl_error'] = trim(file_get_contents("/var/run/adl_error.file"));
	$send['proxy_problem'] = trim(file_get_contents("/var/run/proxy_error.file"));
	$send['connected_displays'] = trim(EthosConf::get("connecteddisplays"));
	$send['resolution'] = trim(`timeout 10 /usr/bin/xrandr | grep current | cut -d" " -f 8,10 | cut -d"," -f1 | xargs`);
	$send['gethelp'] = trim(`tail -1 /var/log/gethelp.log`);
	$send['config_error'] = trim(`cat /var/run/config_mode.file`);
	$send['send_remote'] = trim(`cat /var/run/send_remote.file`);

	if($miner_instance == 1 && $defunct == 0 && $overheated == 0 && $hash_stuck != 1){
	        $send['alive'] = $gpus;
	} else {
            $send['alive'] = 0;
	}

    //gpu related info
    	$send['driver'] = trim(EthosConf::get("driver"));
        $send['wrong_driver'] = trim(file_get_contents("/var/run/ethos/wrong_driver.file"))
        $send['gpus'] = $gpus;
        $send['fanrpm'] = strip_whitespace(EthosConf::get("fanrpm"));
        $send['fanpercent'] = strip_whitespace(EthosConf::get("fan"));
        $send['hash'] = trim(file_get_contents("/var/run/hash.file"));
	$send['miner_hashes'] = trim(file_get_contents("/var/run/miner_hashes.file"));
        $send['models'] = trim(file_get_contents("/tmp/gpulist.file"));
        $send['bioses'] = trim(EthosConf::get("bios"));
        $send['default_core'] = trim(file_get_contents("/var/run/defaultcore.file"));
        $send['default_mem'] = trim(file_get_contents("/var/run/defaultmem.file"));
        $send['core'] = strip_whitespace(EthosConf::get("core"));
        $send['mem'] = strip_whitespace(EthosConf::get("mem"));
        $send['meminfo'] = trim(file_get_contents("/var/run/meminfo.file"));
        $send['voltage'] = strip_whitespace(EthosConf::get("voltage"));
        $send['overheatedgpu'] = trim(file_get_contents("/var/run/overheatedgpu.file"));
        $send['throttled'] = trim(file_get_contents("/var/run/throttled.file"));
        $send['powertune'] = strip_whitespace(EthosConf::get("powertune"));
       	if (file_exists("/home/ethos/asichang.happened")) {
		$send['hanghappened'] = "1";
	}

    return $send;
}


function send_data() {

    $farmid = new EthosFarmId();
    $farmid->generateId();

    $hash = $farmid->getPrivateId();
    $public_hash = $farmid->getPublicId();

    $send = get_stats();

    $hook = "http://ethosdistro.com/get.php";
    $url = "http://".$public_hash.".ethosdistro.com/";

    $json = json_encode($send);

    $log = "";

    foreach($send as $key => $data){
        $log .= "$key:$data\n";
    }
    file_put_contents("/var/run/panel.file",$public_hash);
    file_put_contents("/var/run/url.file",$url);
    $url_style = urlencode($json);
    $hostname = $send['hostname'];

    file("$hook?hostname=$hostname&url_style=$url_style&hash=$hash");

    return $log;
}


function prevent_overheat(){

        $max_temp = EthosConf::get("maxtemp");

        if(!$max_temp){
                $max_temp = EthosConf::get("globalmaxtemp");
        }

        if(!$max_temp){
                $max_temp = "85";
        }
        $throttle_temp = ($max_temp-5);
        $temps = trim(file_get_contents("/var/run/temp.file"));
        $temp_array = explode(" ",$temps);

        $c = 0;
	
        foreach($temp_array as $temp){
		
		$throttled[$c] = trim(file_get_contents("/var/run/throttled.gpu".$c));
	
  	      	if($temp > $throttle_temp && $temp < 500 && temp != 108 && $temp != 128 && !$throttled[$c]){

		 	echo_log("mining has almost caused GPU$c to overheat at $temp (over $throttle_temp C)! GPU$c has been throttled to 800mhz core clock, all fans set to 100%\n");
	         	`/opt/ethos/sbin/ethos-throttle $c`;
		 	file_put_contents("/var/run/throttled.file","1");
			file_put_contents("/var/run/throttled.gpu".$c,"1");
	    	}

		if($temp > $max_temp && $temp < 500 && $temp != 108 && $temp != 128){
			$pid = trim(`/opt/ethos/sbin/ethos-readconf pid $c`);
                        `kill -9 $pid 2> /dev/null`;
                        echo_log("mining has caused GPU$c to overheat at $temp (over $max_temp C)! Miner for GPU$c has been killed\n");
                        file_put_contents("/var/run/overheat.file","1");
                        file_put_contents("/var/run/overheatedgpu.file","$c");
                        break;
                }

                $c++;
        }

}
function check_conky(){
    $uptime = trim(`cut -d " " -f1 /proc/uptime | cut -d "." -f 1`);
    $conky_instance = intval(trim(`pgrep "conky" | wc -l`));
    if($conky_instance == 0 && $uptime > 120 ){
        `/opt/ethos/sbin/start-conky`;
    }
}
function start_xterm(){

        $xterm_instance = trim(`ps uax | grep xterm | grep -v grep | wc -l`);

        if($xterm_instance == 0){
                `su - ethos -c /opt/ethos/bin/ethos-terminal &`;
        }
}


?>
