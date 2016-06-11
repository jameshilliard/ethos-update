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
     * Note that sometimes ethminer runs when it's NOT in -F (farm) mode,
     * and we don't want to count that as a running instance.
     * @return int
     */
    public static function countNumRunningInstances()
    {
        $count = trim(`ps uax | grep ethminer\ \-F | grep -v defunct | grep -v grep | wc -l`);
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
            echo_log("$message\n"); // TODO- OO this

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

        $stratumenabled = EthosConf::get("stratumenabled");

        $pool = EthosConf::get("fullpool");
        $hostname = trim(`cat /etc/hostname`);
        $rigflags = EthosConf::get("rigflags");
        $globalflags = EthosConf::get("flags");
        $selectedgpus = EthosConf::get("selectedgpus");
        $rig_loc = EthosConf::get("loc");
        
        `/usr/local/bin/ethminer -G --list-devices > /var/run/checkigp.file`;

        if ($selectedgpus) {
            $extraflags = trim("--opencl-devices $selectedgpus");
        } else {
            $checkigp = trim(file_get_contents("/var/run/checkigp.file"));
            preg_match('#\b(Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo)\b#', $checkigp, $baddevices);
            if ($baddevices) {
                echo "non-mining device found, excluding from mining gpus.\n";
                $validdevices = `grep ']' /var/run/checkigp.file | grep -v FORMAT | grep -v OPENCL | egrep -iv 'Beavercreek|Sumo|Wrestler|Kabini|Mullins|Temash|Trinity|Richland|Carrizo' | sed 's/\[//g' | sed 's/\]//g' | awk '{print \$1}' | xargs`;
                $extraflags = trim("--opencl-devices $validdevices");
            }
        }

        if($stratumenabled == "enabled") {
            stratum_phoenix(); // TODO- this is dangerous, move it to a class
            if($rig_loc) {
                $pool = "http://127.0.0.1:8080/$rig_loc";
            } else { 
                $pool = "http://127.0.0.1:8080/$hostname";
            }
        } else {
            if($rig_loc) {
                $pool = str_replace("WORKER",$rig_loc,$pool);
            } else {
                $pool = str_replace("WORKER",$hostname,$pool);
            }
        }

        echo "no miner running, starting...\n";

        if ($globalflags) {
            $defaultFlags = $globalflags;

        } else if ($rigflags) {
            $defaultFlags = $rigflags;

        } else {
            $defaultFlags = "--cl-global-work 16384 --farm-recheck 200";
        }

        $unsafeCommand = "/usr/local/bin/ethminer -F ".$pool." -G ".$defaultFlags." ".$extraflags;

        $com = "su - ethos -c \"".escapeshellcmd($unsafeCommand)." >> /var/run/miner.output 2>&1 &\"\n";

        file_put_contents("/tmp/minercmd",$com);
        chmod("/tmp/minercmd", 0755);

        `/tmp/minercmd`;

        echo_log($com); // TODO- OO this

        return true;
    }
};
