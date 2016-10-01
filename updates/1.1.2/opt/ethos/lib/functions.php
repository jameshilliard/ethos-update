
<?php
require_once ('/opt/ethos/lib/ethosfarmid.php');

require_once ('/opt/ethos/lib/minerprocess.php');

function get_cpu_temp()
{
	preg_match("/(?<=\+)(.*)(?=\....C\s)/", trim(`/usr/bin/sensors`) , $matches);
	return $matches[0];
}

function strip_whitespace($string)
{
	$string = preg_replace('/\s+/', ' ', $string);
	$string = trim($string);
	return $string;
}

function echo_log($string)
{
	$date = trim(`date -u`);
	$log_write = $date . " " . $string . "\n";
	file_put_contents("/var/run/ethos/ethos-log.file", $log_write, FILE_APPEND);
}

function get_http_response_code($url)
{
	$headers = get_headers($url);
	return substr($headers[0], 9, 3);
}

function get_reboot_number_from_conf()
{
	$reboot_number = trim(`/opt/ethos/sbin/ethos-readconf reboots`);
	return $reboot_number;
}

function circuit_protect()
{
	$uptime = trim(`cut -d " " -f1 /proc/uptime | cut -d "." -f 1`);
	if ($uptime < 300) {
		$sleep = mt_rand(5, 59);
		file_put_contents("/var/run/ethos/sleep.file", $sleep);
		sleep($sleep);
		file_put_contents("/var/run/ethos/sleep.file", "0");
	}
}

function make_motd()
{
	$message_loc = "http://ethosdistro.com/message";
	file_put_contents("/opt/ethos/etc/message", trim(file_get_contents($message_loc)));
}

function putconf($interactive = "0")
{
	`sudo /opt/ethos/sbin/ethos-motd-generator`;
	`/usr/bin/dos2unix -q /home/ethos/remote.conf`;
	if ($interactive != "1") {
		sleep(mt_rand(0, 5)); //do not saturate webserver children with requests if run automatically
	}

	list($remote) = file("/home/ethos/remote.conf", FILE_IGNORE_NEW_LINES);
	$remote = trim($remote);
	$send_remote = substr($remote, 0, 150);
	file_put_contents("/var/run/ethos/send_remote.file", $send_remote);
	if (strlen($remote) == 0 || !eregi("http://|https://", $remote) || substr($remote, 0, 1) == "#") {
		$message = "REMOTE CONFIG DOES NOT EXIST OR IS FORMATTED INCORRECTLY. USING LOCAL CONFIG.";
		echo $message . "\n";
		if (!$remote) {
			file_put_contents("/var/run/ethos/config_mode.file", "singlerig");
		}
		else {
			file_put_contents("/var/run/ethos/config_mode.file", "badformat");
		}

		return;
	}
	else {
		ini_set('default_socket_timeout', 3);
		$header = get_http_response_code($remote);
		if ($interactive == "1") {
			echo " ...";
		}
	}

	if ($header != "200") {
		$message = "REMOTELY DEFINED CONFIG SERVER IS UNREACHABLE. USING LOCAL CONFIG.";
		echo $message . "\n";
		file_put_contents("/var/run/ethos/config_mode.file", "unreachable");
		return;
	}
	else {
		$remote = trim($remote);
		$remote_contents = trim(file_get_contents($remote, FILE_IGNORE_NEW_LINES));
		if ($interactive == "1") {
			echo "...";
		}
	}

	if (preg_match("/<[^<]+>/", $remote_contents, $m) != 0) {
		$message = "REMOTE CONFIG CONTAINS HTML OR XML TAGS. USING LOCAL CONFIG.";
		echo $message . "\n";
		file_put_contents("/var/run/ethos/config_mode.file", "invalid");
		return;
	}
	else {
		if ($interactive == "1") {
			echo "...";
		}
	}

	if (strlen($remote_contents) < 15) {
		$message = "REMOTE CONFIG APPEARS TO BE TOO SHORT. USING LOCAL CONFIG.";
		echo $message . "\n";
		file_put_contents("/var/run/ethos/config_mode.file", "tooshort");
		return;
	}
	else {
		if ($interactive == "1") {
			echo "...";
		}
	}

	if (md5($remote_contents) != md5(file_get_contents("/home/ethos/local.conf"))) {
		$message = "IMPORTED REMOTE CONFIG INTO LOCAL CONFIG.";
		echo $message . "\n";
		file_put_contents("/home/ethos/local.conf", $remote_contents . "\n");
	}
	else {
		if ($interactive == "1") {
			echo "...";
		}
	}

	`sudo /usr/bin/dos2unix -q /home/ethos/local.conf`;
}

function check_proxy()
{
	$miner = decide_miner(); 
	$stratumtype = trim(`/opt/ethos/sbin/ethos-readconf stratumenabled`);

		file_put_contents("/var/run/ethos/proxy_error.file","working");
		$proxy_error = 0;

	if ($miner == "ethminer" && $stratumtype == "enabled") {
		$requested_restart = trim(`tail -100 /var/run/ethos/proxy.output | grep 'Please restart proxy' | wc -l`);
		if ($requested_restart > 0) {
			file_put_contents("/var/run/ethos/proxy_error.file","restart");
			echo_log("Warning: Proxy timed out attempting to get new work. Restarting proxy.");
			$proxy_error = 2;
		}

		$primary_pool_offline = trim(`tail -100 /var/run/ethos/proxy.output | grep 'must be online' | wc -l`);
		if ($primary_pool_offline > 0) {
			file_put_contents("/var/run/ethos/proxy_error.file","primary_down");
			echo_log("Warning: Primary pool is offline. Restarting proxy.");
			$proxy_error = 3;
		}

		$proxy_getting_job = trim(`tail -5 /var/run/proxy.output | grep 'NEW_JOB MAIN_POOL' | wc -l`);
		$rpc_problems = trim(`tail -240 /var/run/miner.0.output | grep 'JSON-RPC problem' | wc -l`);
		if ($rpc_problems >= 30 && $proxy_getting_job > 2) {
			file_put_contents("/var/run/ethos/proxy_error.file","failure");
			echo_log("Warning: Proxy is not accepting miner connection. Restarting proxy.");
			$proxy_error = 4;
		}

		$rejected_shares = trim(`tail -100 /var/run/ethos/proxy.output | grep -c "REJECTED"`);
		if ($rejected_shares > 2) {
			file_put_contents("/var/run/ethos/proxy_error.file","rejected");
			echo_log("Warning: Proxy is generating rejected shares. Restarting proxy.");
			$proxy_error = 5;
		}

		if ($proxy_error > 0) {
			`echo -n "" > /var/run/ethos/proxy.output`;
			`killall -9 python`;
			`su - ethos -c '/opt/eth-proxy/eth-proxy.py >> /var/run/ethos/proxy.output 2>&1 &'`;
		}
	}
}

function stratum_phoenix()
{
	`ps uax| grep "python /opt/eth-proxy/eth-proxy.py" | grep -v grep | awk '{print $2}' | xargs kill -9 2> /dev/null`;
	`su - ethos -c '/opt/eth-proxy/eth-proxy.py >> /var/run/ethos/proxy.output 2>&1 &'`;
}

function get_stats()
{
	$gpus = trim(file_get_contents("/var/run/ethos/gpucount.file"));
	$driver = trim(`/opt/ethos/sbin/ethos-readconf runningdriver`);

	// miner check info

	$send['miner_instance'] = intval(trim(file_get_contents("/var/run/ethos/instances.file")));
	$send['defunct'] = intval(trim(file_get_contents("/var/run/ethos/defunct.file")));
	$send['allowed'] = intval(trim(file_get_contents("/opt/ethos/etc/allow.file")));
	$send['overheat'] = intval(trim(file_get_contents("/var/run/ethos/overheat.file")));
	$send['outofmemory'] = trim(`tail -30 /var/log/kern.log | grep 'Out of memory' | wc -l`);
	$send['pool_info'] = trim(`cat /home/ethos/local.conf | grep -v '^#' | egrep -i 'pool|wallet|proxy'`);

	// system related info

	$send['kernel'] = trim(`/bin/uname -r`);
	$send['uptime'] = trim(`cat /proc/uptime | cut -d"." -f1`);
	$send['mac'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g'`);
	$send['hostname'] = trim(`/sbin/ifconfig | grep HW | awk '{print \$NF}' | sed 's/://g' | tail -c 7`);
	$send['rack_loc'] = trim(`/opt/ethos/sbin/ethos-readconf loc`);
	$send['ip'] = trim(`/sbin/ifconfig | grep inet | head -1 | cut -d":" -f2 | cut -d" " -f1`);
	$send['mobo'] = trim(file_get_contents("/var/run/ethos/motherboard.file"));
	$send['lan_chip'] = trim(`/usr/bin/lspci -v | grep -Poi "(?<=Ethernet\scontroller\:\s)(.*)"`);
	$send['load'] = trim(`cat /proc/loadavg | cut -d" " -f3`);
	$send['ram'] = trim(`/usr/bin/free | head -2 | tail -1 | awk '{print \$2/1024/1024}' OFMT="%3.0f" | awk '{print \$1}'`);
	$send['cpu_temp'] = trim(file_get_contents("/var/run/ethos/cputemp.file"));
	$send['cpu_name'] = trim(`cat /proc/cpuinfo | grep 'model name' | awk -F": " '{print \$2}'`);
	$send['rofs'] = time() - trim(file_get_contents("/opt/ethos/etc/check-ro.file"));
	$send['drive_name'] = trim(`/opt/ethos/sbin/ethos-readconf driveinfo`);
	$send['freespace'] = round(trim(`/bin/df | grep '/dev/' | head -1 | awk '{print $4}'`) / 1024 / 1024, 1);
	$send['temp'] = trim(file_get_contents("/var/run/ethos/temp.file"));
	$send['version'] = trim(file_get_contents("/opt/ethos/etc/version"));
	$send['miner_secs'] = 0 + trim(`ps -eo pid,comm,etime | grep $miner | head -1 | awk '{print \$NF}' |  /opt/ethos/bin/convert_time.awk`);
	$send['adl_error'] = trim(file_get_contents("/var/run/ethos/adl_error.file"));
	$send['proxy_problem'] = trim(file_get_contents("/var/run/ethos/proxy_error.file"));
	$send['updating'] = trim(file_get_contents("/var/run/ethos/updating.file"));
	$send['connected_displays'] = trim(`/opt/ethos/sbin/ethos-readconf connecteddisplays`);
	$send['resolution'] = trim(`timeout -s KILL 10 /usr/bin/xrandr | grep current | cut -d" " -f 8,10 | cut -d"," -f1 | xargs`);
	$send['gethelp'] = trim(`tail -1 /var/log/gethelp.log`);
	$send['config_error'] = trim(`cat /var/run/ethos/config_mode.file`);
	$send['send_remote'] = trim(`cat /var/run/ethos/send_remote.file`);
	if ($send['miner_instance'] == 1 && $send['defunct'] == 0 && $send['overheat'] == 0) {
		$send['alive'] = $gpus;
	}
	else {
		$send['alive'] = 0;
	}

	// gpu related info

	$send['driver'] = trim(`/opt/ethos/sbin/ethos-readconf driver`);
	$send['wrong_driver'] = trim(file_get_contents("/var/run/ethos/wrong_driver.file"));
	$send['gpus'] = $gpus;
	$send['fanrpm'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf fanrpm`));
	$send['fanpercent'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf fan`));
	$send['hash'] = trim(file_get_contents("/var/run/ethos/hash.file"));
	$send['miner'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf miner`));
	$send['miner_hashes'] = trim(file_get_contents("/var/run/ethos/miner_hashes.file"));
	$send['models'] = trim(file_get_contents("/var/run/ethos/gpulist.file"));
	$send['bioses'] = trim(trim(`/opt/ethos/sbin/ethos-readconf bios`));
	$send['default_core'] = trim(file_get_contents("/var/run/ethos/defaultcore.file"));
	$send['default_mem'] = trim(file_get_contents("/var/run/ethos/defaultmem.file"));
	$send['core'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf core`));
	$send['mem'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf mem`));
	$send['meminfo'] = trim(file_get_contents("/var/run/ethos/meminfo.file"));
	$send['voltage'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf voltage`));
	$send['overheatedgpu'] = trim(file_get_contents("/var/run/ethos/overheatedgpu.file"));
	$send['throttled'] = trim(file_get_contents("/var/run/ethos/throttled.file"));
	$send['powertune'] = strip_whitespace(trim(`/opt/ethos/sbin/ethos-readconf powertune`));
	if (file_exists("/home/ethos/asichang.happened")) {
		$send['hanghappened'] = "1";
	}

	return $send;
}

function send_data()
{
	$farmid = new EthosFarmId();
	$farmid->generateId();
	$hash = $farmid->getPrivateId();
	$public_hash = $farmid->getPublicId();
	$send = get_stats();
	$hook = "http://ethosdistro.com/get.php";
	$url = "http://" . $public_hash . ".ethosdistro.com/";
	$json = json_encode($send);
	$log = "";
	foreach($send as $key => $data) {
		$log.= "$key:$data\n";
	}

	file_put_contents("/var/run/ethos/panel.file", $public_hash);
	file_put_contents("/var/run/ethos/url.file", $url);
	$url_style = urlencode($json);
	$hostname = $send['hostname'];
	file("$hook?hostname=$hostname&url_style=$url_style&hash=$hash");
	return $log;
}

function prevent_overheat()
{
	$max_temp = trim(`/opt/ethos/sbin/ethos-readconf maxtemp`);
	if (!$max_temp) {
		$max_temp = trim(`/opt/ethos/sbin/ethos-readconf globalmaxtemp`);
	}

	if (!$max_temp) {
		$max_temp = "85";
	}

	$throttle_temp = ($max_temp - 5);
	$temps = trim(file_get_contents("/var/run/ethos/temp.file"));
	$temp_array = explode(" ", $temps);
	$c = 0;
	$bad_values = "108 115 128 135";
	$bad_array = explode(" ", $bad_values);
	foreach($temp_array as $temp) {
		$throttled[$c] = trim(file_get_contents("/var/run/ethos/throttled.gpu" . $c));
		if ($temp > $throttle_temp && $temp < 500 && !in_array($temp, $bad_array) && !$throttled[$c]) {
			echo_log("gpu$c reached $temp (C) and throttled (core = 800 powertune = 0, fan = 100), more info at http://ethosdistro.com/kb/#managing-temperature");
			`/opt/ethos/sbin/ethos-throttle $c`;
			file_put_contents("/var/run/ethos/throttled.file", "1");
			file_put_contents("/var/run/ethos/throttled.gpu" . $c, "1");
		}

		if ($temp > $max_temp && $temp < 500 && !in_array($temp, $bad_array)) {
			$pid = trim(`/opt/ethos/sbin/ethos-readconf pid $c`);
			echo_log("gpu$c reached $temp (C) and overheated (turned off miner), more info at http://ethosdistro.com/kb/#managing-temperature");
			`kill -9 $pid 2> /dev/null`;
			file_put_contents("/var/run/ethos/overheat.file", "1");
			file_put_contents("/var/run/ethos/overheatedgpu.file", "$c");
			break;
		}

		$c++;
	}
}

function check_conky()
{
	$uptime = trim(`cut -d " " -f1 /proc/uptime | cut -d "." -f 1`);
	$conky_instance = intval(trim(`pgrep "conky" | wc -l`));
	if ($conky_instance == 0 && $uptime > 120) {
		`/opt/ethos/sbin/start-conky`;
	}
}

?>

