<?php

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

        $miner_instance = intval(trim(`ps uax | grep ethminer | grep -v defunct | grep -v grep | wc -l`));
        $defunct = intval(trim(`ps uax | grep ethminer | grep defunct | grep -v grep | wc -l`));
        $allowed = intval(trim(file_get_contents("/opt/ethos/etc/allow.file")));
        $overheated = intval(trim(file_get_contents("/var/run/overheat.file")));

        return array($miner_instance,$defunct,$allowed,$overheated);
}

function echo_log($string){

        $date = trim(`date`);
	$log_write = $date." ".$string."\n";
	echo $log_write;
        file_put_contents("/var/run/log.file",$log_write,FILE_APPEND);
}

function get_http_response_code($url) {

        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
}

function get_reboot_number_from_conf(){

	$reboot_number = trim(`/opt/ethos/sbin/ethos-readconf reboots`);
	return $reboot_number;
}

function get_pid(){

	$pid = trim(`pgrep ethminer`);
        return $pid;
}

function circuit_protect(){

        $sleep = mt_rand(15,50);
        echo "Sleeping for $sleep seconds before miner startup...\n";
        sleep($sleep);
}
function make_motd(){
        $message_loc = "http://ethosdistro.com/message";
        file_put_contents("/opt/ethos/etc/message",trim(file_get_contents($message_loc)));
}
function stratum_phoenix(){
        `ps uax| grep "python /opt/eth-proxy/eth-proxy.py" | grep -v grep | awk '{print $2}' | xargs kill -9`;
        `su - ethos -c '/opt/eth-proxy/eth-proxy.py >> /var/run/proxy.output 2>&1 &'`;
}

function start_miner(){

	list($miner_instance,$defunct,$allowed,$overheated) = miner_run_check();
	$stratumenabled = trim(`/opt/ethos/sbin/ethos-readconf stratumenabled`);
        $local = file("/home/ethos/local.conf",FILE_IGNORE_NEW_LINES);
        
        // old way list($crap,$pool) = explode(" ",$local[0]);
        $pool = trim(`/opt/ethos/sbin/ethos-readconf fullpool`);
        $hostname = trim(`cat /etc/hostname`);
        $flags = trim(`/opt/ethos/sbin/ethos-readconf flags`);

        if($miner_instance == 0 && $allowed == 1 && $defunct == 0 && $overheated == 0){

        if($allowed == 1 && $stratumenabled == "enabled") {
                stratum_phoenix();
                $pool = "http://127.0.0.1:8080/$hostname";
        } else {
                $pool = str_replace("WORKER",$hostname,$pool);
        }
        
                echo "no miner running, starting...\n";
                if (!empty($flags)) {
                    $com .= "su - ethos -c \"/usr/local/bin/ethminer -F ".$pool." -G ".$flags." >> /var/run/output.$i 2>&1 &\n\"";    
                } else {
                    $com .= "su - ethos -c \"/usr/local/bin/ethminer -F ".$pool." -G --cl-global-work 16384 --farm-recheck 200 >> /var/run/output.$i 2>&1 &\n\"";     
                }

                file_put_contents("/tmp/minercmd",$com);
                `/tmp/minercmd`;

		file_put_contents("/var/run/status.file","Miner commanded to start.\n");

                echo_log($com);

        } else {
		if($miner_instance == 1){
			$pid = get_pid();
			$hashrate = trim(file_get_contents("/var/run/hash.file"));
			file_put_contents("/var/run/status.file","Miner is hashing at $hashrate mh/s with PID $pid\n");
		} else { 
			file_put_contents("/var/run/status.file","A condition has prevented miner start.\n");
		}
	}
}

function send_data(){

	list($hash,$public_hash) = get_hash();

	$hook = "http://ethosdistro.com/get.php";
	$url = "http://".$public_hash.".ethosdistro.com/";

	list($miner_instance,$defunct,$allowed,$overheated) = miner_run_check();

	$gpus = trim(`/usr/bin/aticonfig --lsa | grep AMD | wc -l`);

	$hash_stuck = trim(`/usr/bin/md5sum /var/run/output.temp /var/run/output. 2>/dev/null | cut -d" " -f1  | uniq -d | wc -l`);

	//miner check info
        $send['miner_instance'] = $miner_instance;
        $send['defunct'] = $defunct;
        $send['allowed'] = $allowed;
        $send['overheat'] = $overheated;
	$send['hash_stuck'] = $hash_stuck;

	//system related info
        $send['uptime'] = trim(`cat /proc/uptime | cut -d"." -f1`);
        $send['mac'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g'`);
        $send['hostname'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g' | tail -c 7`);
        $send['ip'] = trim(`/sbin/ifconfig | grep inet | head -1 | cut -d":" -f2 | cut -d" " -f1`);
    	$send['mobo'] = trim(file_get_contents("/tmp/motherboard.file"));
        $send['load'] = trim(`cat /proc/loadavg | cut -d" " -f2`);
        $send['ram'] = trim(`/usr/bin/free | head -2 | tail -1 | awk '{print \$2/1000/1000}' OFMT="%3.0f" | awk '{print \$1}'`);
        $send['cpu_temp'] = trim(file_get_contents("/var/run/cputemp.file"));
        $send['freespace'] = trim(`df -h | grep sda | awk '{print $4}' | sed 's/G//g'`);
        $send['temp'] = trim(file_get_contents("/var/run/temp.file"));
	$send['version'] = trim(file_get_contents("/opt/ethos/etc/version"));
    	$send['miner_secs'] = 0 + trim(`ps -eo pid,comm,etime | grep ethminer | awk '{print \$NF}' |  /opt/ethos/bin/convert_time.awk`);

	if($miner_instance == 1 && $defunct == 0 && $overheated == 0 && $hash_stuck != 1){
	        $send['alive'] = $gpus;
	} else {
            $send['alive'] = 0;
	}

	//eth related info
        $send['dag_count'] = trim(`ls /home/ethos/.ethash| wc -l`);
        $send['dag_size'] = trim(`du -cs /home/ethos/.ethash/ | tail -1 | awk '{print \$1}'`);

        //gpu related info
        $send['gpus'] = $gpus;
        $send['fanrpm'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf fanrpm`));
        $send['hash'] = trim(file_get_contents("/var/run/hash.file"));
	$send['models'] = trim(`exec /usr/bin/lspci | grep VGA | cut -d":" -f1,3`);
	$send['voltage'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf voltage`));
        $send['mem'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf mem`));
        $send['core'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf core`));
        


        $results = print_r($send, TRUE);
        $json = json_encode($send);

        $log = "";

        foreach($send as $key => $data){
                $log .= "$key:$data\n";
        }

        file_put_contents("/var/run/url.file",$url);
        $array = json_decode($json,TRUE);
        $url_style = urlencode($json);
        $hostname = $array['hostname'];

	list($hash,$public_hash) = get_hash();

        file("$hook?hostname=$hostname&url_style=$url_style&hash=$hash");

	return $log;
}

function get_hash(){
        $ip = trim(file_get_contents("https://api.ipify.org"));
        $hash = substr(hash("sha256",$ip),0,12);
        $public_hash = substr(hash("sha256",$ip),0,6);
        return array($hash,$public_hash);
}

function prevent_overheat(){
        $max_temp = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf maxtemp`));
        if(empty($max_temp)) {
                $max_temp = "85";
        }
        $temps = trim(file_get_contents("/var/run/temp.file"));
        $temp_array = explode(" ",$temps);

        $c = 0;

        foreach($temp_array as $temp){
                if($temp > $max_temp && $temp < 500){
                        `killall -9 ethminer`;
                        echo_log("Ethminer has caused GPU$c to overheat! GPU$c has overheated to $temp C (over $max_temp C), Miner has been killed\n");
                        file_put_contents("/var/run/overheat.file","1");
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
