#!/usr/bin/env php
<?php
define('READ_LEN', 4096);
			include("/opt/ethos/lib/functions.php");
                        `/usr/bin/dos2unix -q /home/ethos/remote.conf`;
			sleep(mt_rand(0,5)); //do not saturate webserver children with requests

	        	list($remote) = file("/home/ethos/remote.conf",FILE_IGNORE_NEW_LINES);

		        $remote = trim($remote);

                        if(strlen($remote) > 0){
                                echo_config_log("REMOTE CONFIG SERVER DEFINED, CHECKING URL FORMAT ...");

                                if(eregi("http://|https://",$remote) && substr($remote, 0, 1) != "#"){
                                        echo_config_log("FORMAT OK, CHECKING REACHABILITY ...");
                                        ini_set('default_socket_timeout', 3);
                                        $header = get_http_response_code($remote);

                                        if($header == "200"){
                                                echo_config_log("REMOTELY DEFINED CONFIG SERVER REACHABLE, RETRIEVING REMOTELY DEFINED CONFIG in /home/ethos/remote.conf ...");
                                                $remote = trim($remote);
                                                $global_conf = trim(file_get_contents($remote,FILE_IGNORE_NEW_LINES));
                                                file_put_contents("/home/ethos/local.conf.temp",$global_conf."\n");
                                                if(!files_identical('/home/ethos/local.conf', '/home/ethos/local.conf.temp')) {
                                                        file_put_contents("/home/ethos/local.conf",$global_conf."\n");
                                                        unlink('/home/ethos/local.conf.temp');
                                                        echo_log("REMOTE CONFIG UPDATED - IMPORTING REMOTE CONFIG INFO INTO /home/ethos/local.conf ...");
                                                        echo_config_log("REMOTE CONFIG UPDATED - IMPORTING REMOTE CONFIG INFO INTO /home/ethos/local.conf ...");
                                                } else {
                                                        unlink('/home/ethos/local.conf.temp');
                                                }
                                                echo_config_log("IMPORTING REMOTE CONFIG INFO /home/ethos/local.conf ...");
                                        } else {
                                                echo_config_log("REMOTELY DEFINED CONFIG UNREACHABLE, RETRIEVING DATA FROM LOCALLY DEFINED CONFIG in /home/ethos/local.conf ...");
                                        }

                                } else {
                                        echo_config_log("REMOTE CONFIG URL FORMAT IS NOT OK, RETRIEVING DATA FROM LOCALLY DEFINED CONFIG in /home/ethos/local.conf ...");
                                }
                        }  else {
                                echo_config_log("REMOTE CONFIG IS NOT DEFINED, RETRIEVING DATA FROM LOCALLY DEFINED CONFIG in /home/ethos/local.conf ...");
                        }
                        `/usr/bin/dos2unix -q /home/ethos/local.conf`;
                        `/opt/ethos/sbin/ethos-motd-generator`;

?>
