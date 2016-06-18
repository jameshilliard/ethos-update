<?php
/**
 * Ethos Conf class
 *
 * Gives easy access to read config files
 */
class EthosConf
{
    public static function get($name)
    {
        $nameEscaped = escapeshellarg($name);
        $command = "/opt/ethos/sbin/ethos-readconf $nameEscaped";

        exec($command, $result, $exitStatus);
        if ($exitStatus !== 0) {
            // ethos-readconf exited with a non-zero status code.
            // There was definitely an error, and we should know about it.

            $message = "WARNING: Undefined configuration item requested: '$name'";
            echo_log("$message\n");
            fwrite(STDERR, "$message\n");
            return null;
        }

        // Not sure if there would ever be multi-line config values, but just in case,
        // this will join any multiple lines back using \n
        $value = trim(implode("\n", $result));
        return $value;
    }
};
