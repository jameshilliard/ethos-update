<?php

require_once __DIR__ . '/EthosConf.php';


/**
 * Miner Process
 *
 * Keep track of the miner process
 *
 * @author xist
 */
class MinerProcess
{
    /**
     * The log file that ethminer's output is written to.
     *
     * We will read this to determine if the miner is stuck.  Other processes
     * will also read this to collect statistics like MH/s.
     *
     * @var string
     */
    const MINER_LOG_FILE = '/var/run/miner.output';

    /**
     * A file whose existance indicates that the miner is stuck.
     *
     * The ethos-miner-daemon, on calling $this->run(), will manage this file.
     * If we detect that the miner is stuck, we will create this file.
     * If we detect that the miner is running, we will ensure this file does NOT exist.
     * Thus other apps can check for this file, if it exists, they know that the
     * miner is currently stuck.
     *
     * @var string
     */
    const MINER_STUCK_FILE = '/var/run/miner.stuck';

    /**
     * Max number of seconds that ethminer might pause between writes to MINER_LOG_FILE.
     * If it takes longer than this number of seconds, we conclude that ethminer
     * has gotten stuck and is not mining.
     * @var int
     */
    const MINER_STUCK_TIME_SECONDS = 30;

    /**
     * Minimum number of seconds to wait for a stuck machine before executing
     * an automatic reboot.
     * @var int
     */
    const AUTO_REBOOT_FROZEN_TIME_DEFAULT = 90;

    /**
     * Amount of time (in seconds) that the machine has been frozen.
     *
     * This is computed as the current time minus the mtime of the MINER_LOG_FILE.
     * If zero, the miner is not frozen.
     * @var int
     */
    protected $nFrozenTime = 0;

    /**
     * Count number of non-defunct running ethminer instances
     * @return int
     */
    public static function countNumRunningInstances()
    {
        $count = trim(`ps uax | grep ethminer | grep -v defunct | grep -v grep | wc -l`);
        return intval($count);
    }

    /**
     * Determine if the current ethminer instance (if any) is defunct
     * @return bool
     */
    public static function isDefunct()
    {
        $defunct = trim(`ps uax | grep ethminer | grep defunct | grep -v grep | wc -l`);
        return intval($defunct) != 0;
    }
    /** Determine if kernel command line prohibits mining 
     */
         public static function isNoMine()
    {
        $nomine = trim(file_get_contents("/var/run/nomine.file"));
        return intval($nomine) != 1;
    }
    /** Sling00 - 7-27-16 - Determine which miner we are using, for near future use. **/
      public static function whichMiner() 
      {
      	 $globalminer = EthosConf::get('miner');
         $rigminer = EthosConf::get('rigminer');
        if (!$rigminer && $globalminer) {
             $miner = "$globalminer";
         } elseif ($rigminer) {
             $miner = "$rigminer";
         } else {
             $miner = "ethminer";
         }
         return $miner;
      }
    /**
     * Determine if this machine is allowed to start mining
     * @return bool
     */
    public static function isAllowedToRun()
    {
        $allowed = trim(file_get_contents("/opt/ethos/etc/allow.file"));
        return intval($allowed) != 0;
    }

    /**
     * Determine if the machine is overheating
     * @return bool
     */
    public static function isOverheating()
    {
        $overheated = trim(file_get_contents("/var/run/overheat.file"));
        return intval($overheated) == 1;
    }

    /**
     * Is the miner apparently stuck?
     * True if the miner log file is not actively being written to
     * @return bool
     */
    public static function isStuck()
    {
        clearstatcache();
        return file_exists(self::MINER_STUCK_FILE);
    }

    /**
     * Check to see if the ethminer process is writing to the log, or
     * if the log is frozen and not moving.
     * @return bool TRUE if machine is frozen, FALSE if not frozen
     */
    public function checkFrozen()
    {
        // clear stat cache before calling stat() so we never get cached results
        clearstatcache();
        $stat = stat(self::MINER_LOG_FILE);

        // Unless we have explicit evidence to the contrary,
        // assume the miner is stuck.
        $stuck = true;
        $mtime = 0; // zero means no log exists

        // If we previously checked the mtime, then we want to see if it
        // has changed since the last check
        if ($stat) {

            $mtime = $stat[9]; // file mtime

            // If the file has been modified in the last X seconds, then it is
            // currently being updated and is not suck.
            $t = time() - $mtime;
            if ($t < self::MINER_STUCK_TIME_SECONDS) {
                $this->nFrozenTime = 0;
                $stuck = false;
            } else {
                $this->nFrozenTime = $t;
            }
        }

        if ($stuck) {
            // Stuck. Create stuck file so other procs know we're stuck.
            if (! file_exists(self::MINER_STUCK_FILE)) {
                $fh = fopen(self::MINER_STUCK_FILE, "w");
                fwrite($fh, $mtime);
                fclose($fh);
            }
        } else {
            // Not stuck. Remove stuck file if it exists.
            if (file_exists(self::MINER_STUCK_FILE)) {
                unlink(self::MINER_STUCK_FILE);
            }
        }

        return $stuck;
    }

    /**
     * Run the miner
     *
     * This is the main loop of ethos-miner-daemon.
     *
     * The purpose of this is to ensure that the miner is running.  It will be
     * called many times, roughly every 30 seconds.
     *
     * If the miner should be running, but isn't, then start it.
     *
     * If the miner is stuck, restart it.
     *
     * If the miner is running normally, return back to the daemon to sleep
     * some more until it's time to check again.
     */
    public function run()
    {
        $miner_instance = static::countNumRunningInstances();
        if (!$miner_instance) {
            // No miner instance is running, try to start it.

            $started = $this->tryStart();
            if (!$started) {
                // Unable to start for some reason

                return false;
            }

            // We tried to start the miner (hopefully it does start)
            file_put_contents("/var/run/status.file", "Miner commanded to start.\n");

            return true;
        }

        // The miner is running.  Check it to make sure it didn't freeze up.

        $frozen = $this->checkFrozen();
        if ($frozen) {
            // The machine is frozen. Need to reboot it.

            $message = "Machine frozen for {$this->nFrozenTime} seconds.";
            echo_log("$message"); 

            $autoReboot = EthosConf::get('autoreboot');
            if (intval($autoReboot) == 1 || strtolower($autoReboot) === 'true') {
                // Auto reboot is enabled when we detect frozen GPU.

                // See if it has been frozen long enough to reboot
                $min = self::AUTO_REBOOT_FROZEN_TIME_DEFAULT;
                if ($this->nFrozenTime >= $min) {

                    // The machine has been frozen for at least the minimum amount of time
                    // required to conclude that it is a hard freeze.  Reboot.

                    $file = self::MINER_LOG_FILE;
                    $lastLogLines = `tail -n 5 "$file" 2>&1`;
                    $lastLogLines = str_replace("\n", "\n    ", $lastLogLines);
		    `echo "$time 1" > /opt/ethos/etc/autorebooted.file`;
                    NOTICE_log("Auto-reboot: machine frozen for {$this->nFrozenTime} seconds. Log ends with:\n".
                               "    $lastLogLines");

                    `sudo reboot &`;

                    // Wait until reboot is successful, we don't want to process more stuff
                    // This is better than exit(0) because that would just cause the daemon
                    // to restart and actually do more stuff.  This waits until the reboot.
                    sleep(300);
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Try to start the miner
     *
     * We will not start it if it is not allowed, or if there is a defunct process
     * already, or if the machine is overheating.
     *
     * If it's OK to start, then we will actually start.
     *
     * @return bool
     */
    protected function tryStart()
    {
        $defunct = static::isDefunct();
        if ($defunct) {
            // TODO- Add optional automatic rebooting if config allows it.
            file_put_contents("/var/run/status.file", "Miner is defunct. Please reboot.\n");
            return false;
        }

        $overheated = static::isOverheating();
        if ($overheated) {
            // TODO- Surely we can do something better than just complain.
            file_put_contents("/var/run/status.file", "Miner is overheating. What to do?\n");
            return false;
        }
        $nomine = static::isNoMine();
        if (!$nomine) {
            file_put_contents("/var/run/status.file", "Cannot mine because driver is not loaded.\n");
            return false;
        }
        $allowed = static::isAllowedToRun();
        if (!$allowed) {
            file_put_contents("/var/run/status.file", "Use 'allow' command to allow miner to start.\n");
            return false;
        }

        // If we've gotten here, then there is no reason NOT to start the
        // miner, so go ahead and start it.

        $started = $this->start();
        return $started;
    }

    /**
     * Start the miner
     */
    protected function start()
    {
        $extraflags = ""; // no extra flags by default

        $stratumtype = EthosConf::get("stratumenabled");
	$driver = EthosConf::get("driver");
        $gpumode = trim(EthosConf::get("gpumode"));
        $pool = EthosConf::get("fullpool");
        $hostname = trim(`cat /etc/hostname`);
        $rigflags = EthosConf::get("rigflags");
	$proxywallet = EthosConf::get("proxywallet");
	$proxypool1 = EthosConf::get("proxypool1");
	$proxypool2 = EthosConf::get("proxypool2");
        $globalflags = EthosConf::get("flags");
        $selectedgpus = trim(`/opt/ethos/sbin/ethos-readconf selectedgpus`);
        
        $rig_loc = EthosConf::get("loc");
        if ( $gpumode != "-G" || $gpumode != "-U" ) {
           if ($driver == "nvidia") { 
           	$gpumode  = "-U";
           }
           if ($driver == "fglrx" || $driver == "amdgpu" ) {
           	$gpumode = "-G";
           }
        }
        if ($driver == "nvidia" && $gpumode == "-U" ) {
            $selecteddevicetype = "--cuda-devices";
        } else {
          	$selecteddevicetype = "--opencl-devices";
          	`/usr/local/bin/ethminer -G --list-devices > /var/run/checkigp.file`;
          	// This is broken becauseo f our use of --opencl-devices below.
            $checkigp = trim(file_get_contents("/var/run/checkigp.file"));
            preg_match('#\b(Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo)\b#', $checkigp, $baddevices);
            if ($baddevices) {
                echo "non-mining device found, excluding from mining gpus.\n";
                $validdevices = `grep ']' /var/run/checkigp.file | grep -v FORMAT | grep -v OPENCL | egrep -iv 'Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo' | sed 's/\[//g' | sed 's/\]//g' | awk '{print \$1}' | xargs`;
                $extraflags = trim("--opencl-devices $validdevices");
            }
          
        }
	$minermode = "-F";
	
	//getwork
        if($stratumtype != "enabled" && $stratumtype != "miner"){
            if($rig_loc) {
                $pool = str_replace("WORKER",$rig_loc,$pool);
            } else {
                $pool = str_replace("WORKER",$hostname,$pool);
            }
	}

	//parallel proxy
        if($stratumtype == "enabled") {
            stratum_phoenix();
            if($rig_loc) {
                $pool = "http://127.0.0.1:8080/$rig_loc";
            } else { 
                $pool = "http://127.0.0.1:8080/$hostname";
            }
	
	}
        
	//genoil proxy
	if ($stratumtype == "miner") {
	   $minermode = "-S";
            if($rig_loc) {
                $pool = $proxypool1;
		$extraflags .= "-O $proxywallet.$rig_loc";
		if ($proxypool2) { 
			$extraflags .=" -FS $proxypool2 -FO $proxywallet.$rig_loc"; 
		}
            } else {
                $pool = $proxypool1;
		$extraflags .= "-O $proxywallet.$hostname";
		if ($proxypool2) { 
			$extraflags .=" -FS $proxypool2 -FO $proxywallet.$hostname"; 
		}
            }

	}
	//genoil proxy
	if ($stratumtype == "nicehash") {
	   $minermode = "-SP 2 -S";
            if($rig_loc) {
                $pool = $proxypool1;
		$extraflags .= "-O $proxywallet.$rig_loc";
		if ($proxypool2) { 
			$extraflags .=" -FS $proxypool2 -FO $proxywallet.$rig_loc"; 
		}
            } else {
                $pool = $proxypool1;
		$extraflags .= "-O $proxywallet.$hostname";
		if ($proxypool2) { 
			$extraflags .=" -FS $proxypool2 -FO $proxywallet.$hostname"; 
		}
            }


	}

	$flags = "--cl-global-work 16384 --farm-recheck 200";
	
        if($globalflags){
                $flags = $globalflags;
        }

	if($rigflags){
		$flags = $rigflags;
	}

	$gpus = trim(file_get_contents("/tmp/gpucount.file"));


	if($selectedgpus == "0" || $selectedgpus){

		if(eregi(" ",$selectedgpus)){
			$start_miners = explode(" ",$selectedgpus);
		} else {
			$start_miners[] = $selectedgpus;
		}
	} else {

		$i = 0;

		for($i = 0; $i < $gpus; $i++){
			$start_miners[] = $i;
		}

	}

	foreach($start_miners as $start_miner){

	        $unsafeCommand = "/usr/local/bin/ethminer $minermode ".$pool." ".$gpumode." --dag-load-mode sequential ".$flags." ".$extraflags." ".$selecteddevicetype." $start_miner";

	        $com = "su - ethos -c \"".escapeshellcmd($unsafeCommand)." 2>&1 | /usr/bin/tee -a /var/run/miner.output >> /var/run/miner.$start_miner.output &\"";

	        file_put_contents("/tmp/minercmd",$com."\n");
	        chmod("/tmp/minercmd", 0755);

	        echo "GPU $start_miner no miner running, starting miner process for GPU$start_miner\n";
	        echo_log("GPU $start_miner no miner running, starting miner process for GPU$start_miner\n");
	        `/tmp/minercmd`;

	        echo_log($com);

		sleep(10);

	}

       return true;

    }
};
