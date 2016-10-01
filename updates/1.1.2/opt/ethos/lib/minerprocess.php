<?php

function check_igp()
{
	$checkigp = trim(`/usr/local/bin/ethminer -G --list-devices`);
	preg_match('#\b(Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo)\b#', $checkigp, $baddevices);

	if ($baddevices) {
		echo "non-mining device found, excluding from mining gpus.\n";
		$validdevices = `grep ']' /var/run/ethos/checkigp.file | grep -v FORMAT | grep -v OPENCL | egrep -iv 'Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo' | sed 's/\[//g' | sed 's/\]//g' | awk '{print \$1}' | xargs`;
		$extraflags = trim("--opencl-devices $validdevices");
		return $extraflags;
	}
}

function decide_miner()
{
	$miner = trim(`/opt/ethos/sbin/ethos-readconf miner`);
	if (!$miner) {
		$miner = "ethminer";
	}

	return $miner;
}

function check_status()
{
	$miner = decide_miner();
	$status['booting']['value'] = intval(trim(file_get_contents("/var/run/ethos/prelogin.file")));
	$status['adl_error']['value'] = intval(trim(file_get_contents("/var/run/ethos/adl_error.file")));
	$status['wrong_driver']['value'] = intval(trim(file_get_contents("/var/run/ethos/wrong_driver.file")));
	$status['nomine']['value'] = intval(trim(file_get_contents("/var/run/ethos/nomine.file")));
	$status['nowatchdog']['value'] = intval(trim(file_get_contents("/var/run/ethos/nowatchdog.file")));
	$status['allow']['value'] = intval(trim(file_get_contents("/opt/ethos/etc/allow.file")));
	$status['sleep']['value'] = intval(trim(file_get_contents("/var/run/ethos/sleep.file")));
	$status['defunct']['value'] = intval(trim(`ps uax | grep $miner | grep defunct | grep -v grep | wc -l`));
	$status['overheat']['value'] = intval(trim(file_get_contents("/var/run/ethos/overheat.file")));
	$status['instances']['value'] = intval(trim(`ps uax | grep $miner | grep -v defunct | grep -v grep | wc -l`));
	$status['hash']['value'] = trim(file_get_contents("/var/run/ethos/hash.file"));
	$status['booting']['message'] = "miner started: finishing boot process";
	$status['adl_error']['message'] = "driver error: possible gpu/riser/hardware failure";
	$status['wrong_driver']['message'] = "wrong driver: incorrect driver in config";
	$status['nomine']['message'] = "driver failed: graphics driver did not load";
	$status['nowatchdog']['message'] = "no overheat protection: overheat protection not running";
	$status['allow']['message'] = "miner disallowed: use 'allow' command";
	$status['sleep']['message'] = "miner started: starting miner in " . $status['sleep']['value'] . " secs"; //intentionally "miner started", i.e. user connects monitor and needs to see "miner started" instead of "miner sleeping", because his terminal does not update
	$status['defunct']['message'] = "gpu crashed: reboot required";
	$status['overheat']['message'] = "overheat: one or more gpus overheated";
	$status['instances']['message'] = "miner started: miner commanded to start";
	$status['hash']['message'] = "hashing at " . $status['hash']['value'] . " (mhs): miner active";

	if ($status['booting']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['booting']['message'] . "\n");
		return false;
	}

	if ($status['adl_error']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['adl_error']['message'] . "\n");
		return false;
	}

	if ($status['wrong_driver']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['wrong_driver']['message'] . "\n");
		return false;
	}

	if ($status['nomine']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['nomine']['message'] . "\n");
		return false;
	}

	if ($status['nowatchdog']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['nowatchdog']['message'] . "\n");
		return false;
	}

	if ($status['allow']['value'] == 0) {
		file_put_contents("/var/run/ethos/status.file", $status['allow']['message'] . "\n");
		return false;
	}

	if ($status['sleep']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['sleep']['message'] . "\n");
		return false;
	}

	if ($status['defunct']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['defunct']['message'] . "\n");
		file_put_contents("/var/run/ethos/defunct.file", $status['defunct']['value']);
		return false;
	}

	if ($status['overheat']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['overheat']['message'] . "\n");
		return false;
	}

	if ($status['instances']['value'] == 0) {
		file_put_contents("/var/run/ethos/status.file", $status['instances']['message'] . "\n");
		file_put_contents("/var/run/ethos/instances.file", $status['instances']['value']);
		return true;
	}

	if ($status['hash']['value'] > 0) {
		file_put_contents("/var/run/ethos/status.file", $status['hash']['message'] . "\n");
		return false;
	}
}

function start_miner()
{
	$miner = decide_miner();
	$status = check_status();

	if (!$status) {
		return false;
	}

	$driver = trim(`/opt/ethos/sbin/ethos-readconf driver`);
	$extraflags = ""; // no extra flags by default
	$hostname = trim(`cat /etc/hostname`);
	$proxywallet = trim(`/opt/ethos/sbin/ethos-readconf proxywallet`);
	$proxypool1 = trim(`/opt/ethos/sbin/ethos-readconf proxypool1`);
	$proxypool2 = trim(`/opt/ethos/sbin/ethos-readconf proxypool2`);
	$selectedgpus = trim(`/opt/ethos/sbin/ethos-readconf selectedgpus`);
	$gpus = trim(file_get_contents("/var/run/ethos/gpucount.file"));
	$rig_loc = trim(`/opt/ethos/sbin/ethos-readconf loc`);
	$rig_loc = trim(preg_replace("/[^a-zA-Z0-9]+/", "", $rig_loc));

	if ($miner == "ethminer") {
		$stratumtype = trim(`/opt/ethos/sbin/ethos-readconf stratumenabled`);
		$gpumode = trim(`/opt/ethos/sbin/ethos-readconf gpumode`);
		$pool = trim(`/opt/ethos/sbin/ethos-readconf fullpool`);
		$flags = trim(`/opt/ethos/sbin/ethos-readconf flags`);
		if (!$flags) {
			$flags = "--cl-global-work 8192--farm-recheck 200";
		}

		if ($gpumode != "-G" || $gpumode != "-U") {
			if ($driver == "nvidia") {
				$gpumode = "-U";
			}

			if ($driver == "fglrx" || $driver == "amdgpu") {
				$gpumode = "-G";
			}
		}

		if ($driver == "nvidia" && $gpumode == "-U") {
			$selecteddevicetype = "--cuda-devices";
		}
		else {
			$selecteddevicetype = "--opencl-devices";
			$extraflags = check_igp();
		}

		$minermode = "-F";

		// getwork

		if ($stratumtype != "enabled" && $stratumtype != "miner") {
			if ($rig_loc) {
				$pool = str_replace("WORKER", $rig_loc, $pool);
			}
			else {
				$pool = str_replace("WORKER", $hostname, $pool);
			}
		}

		// parallel proxy

		if ($stratumtype == "enabled") {
			stratum_phoenix();
			if ($rig_loc) {
				$pool = "http://127.0.0.1:8080/$rig_loc";
			}
			else {
				$pool = "http://127.0.0.1:8080/$hostname";
			}
		}

		// genoil proxy

		if ($stratumtype == "miner") {
			$minermode = "-S";
			if ($rig_loc) {
				$pool = $proxypool1;
				$extraflags.= "-O $proxywallet.$rig_loc";
				if ($proxypool2) {
					$extraflags.= " -FS $proxypool2 -FO $proxywallet.$rig_loc";
				}
			}
			else {
				$pool = $proxypool1;
				$extraflags.= "-O $proxywallet.$hostname";
				if ($proxypool2) {
					$extraflags.= " -FS $proxypool2 -FO $proxywallet.$hostname";
				}
			}
		}

		// genoil proxy

		if ($stratumtype == "nicehash") {
			$minermode = "-SP 2 -S";
			if ($rig_loc) {
				$pool = $proxypool1;
				$extraflags.= "-O $proxywallet.$rig_loc";
				if ($proxypool2) {
					$extraflags.= " -FS $proxypool2 -FO $proxywallet.$rig_loc";
				}
			}
			else {
				$pool = $proxypool1;
				$extraflags.= "-O $proxywallet.$hostname";
				if ($proxypool2) {
					$extraflags.= " -FS $proxypool2 -FO $proxywallet.$hostname";
				}
			}
		}
	}
	if ($miner == "sgminer-gm") {

		if ($selectedgpus == "0" || $selectedgpus) { 
			$devices = (`/opt/ethos/sbin/ethos-readconf selectedgpus |sed 's/ /,/g'`);
			$extraflags = ("-d $devices");
		}

		$selectedgpus = ""; // unset this to prevent potential issues with sgminer.

		$config_string = file_get_contents("/opt/ethos/sgminer.stub.conf");

		$worker = $rig_loc;
		if (!$worker) { 
			$worker = $hostname; 
		}

		$config_string = str_replace("WORKER",$worker,$config_string);
		$config_string = str_replace("POOL1",$proxypool1,$config_string);
		$config_string = str_replace("POOL2",$proxypool2,$config_string);

		file_put_contents("/var/run/ethos/sgminer.conf",$config_string);

	}

	if ($selectedgpus == "0" || $selectedgpus) {
		if (eregi(" ", $selectedgpus)) {
			$start_miners = explode(" ", $selectedgpus);
		}
		else {
			$start_miners[] = $selectedgpus;
		}
	}
	else {
		$i = 0;
		for ($i = 0; $i < $gpus; $i++) {
			$start_miners[] = $i;
		}
	}

	$miner_path['ethminer'] = "/usr/local/bin/ethminer";
	$miner_path['sgminer-gm'] = "/usr/bin/screen -c /opt/ethos/etc/screenrc -dmS sgminer /opt/miners/sgminer-gm/sgminer-gm";
	foreach($start_miners as $start_miner) {
		$miner_params['ethminer'] = "$minermode " . $pool . " " . $gpumode . " --dag-load-mode sequential " . $flags . " " . $extraflags . " " . $selecteddevicetype . " $start_miner";
		$miner_params['sgminer-gm'] = "-c /var/run/ethos/sgminer.conf";
		$miner_suffix['ethminer'] = "2>&1 | /usr/bin/tee -a /var/run/miner.output >> /var/run/miner.$start_miner.output &";
		$miner_suffix['sgminer-gm'] = "$extraflags";
		$command = "su - ethos -c \"" . escapeshellcmd($miner_path[$miner] . " " . $miner_params[$miner]) . " $miner_suffix[$miner]\"";
		file_put_contents("/tmp/minercmd", $command . "\n");
		chmod("/tmp/minercmd", 0755);
		`/tmp/minercmd`;
		echo_log($command);
		if ($miner == "sgminer-gm") {
			break;
		}

		sleep(10);
	}

	return true;
}

?>
