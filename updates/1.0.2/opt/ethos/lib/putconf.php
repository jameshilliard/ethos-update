#!/usr/bin/env php
<?php

			include("/opt/ethos/lib/functions.php");
                        `/usr/bin/dos2unix -q /home/ethos/remote.conf`;
			sleep(mt_rand(0,5)); //do not saturate webserver children with requests

	        	list($remote) = file("/home/ethos/remote.conf",FILE_IGNORE_NEW_LINES);

		        $remote = trim($remote);

                        if(strlen($remote) > 0){
                                echo_log("REMOTE POOL DEFINED, CHECKING URL FORMAT ...");

                                if(eregi("http://|https://",$remote) && substr($remote, 0, 1) != "#"){
                                        echo_log("FORMAT OK, CHECKING REACHABILITY ...");
                                        ini_set('default_socket_timeout', 3);
                                        $header = get_http_response_code($remote);

                                        if($header == "200"){
                                                echo_log("REMOTELY DEFINED POOL REACHABLE, RETRIEVING DATA FROM REMOTELY DEFINED POOL in /home/ethos/remote.conf ...");
                                                $remote = trim($remote);
                                                $global_conf = trim(file_get_contents($remote,FILE_IGNORE_NEW_LINES));
                                                file_put_contents("/home/ethos/local.conf",$global_conf."\n");
                                                echo_log("IMPORTING REMOTE GLOBAL CONF INFO /home/ethos/local.conf ...");
                                        } else {
                                                echo_log("REMOTELY DEFINED POOL UNREACHABLE, RETRIEVING DATA FROM LOCALLY DEFINED POOL in /home/ethos/local.conf ...");
                                        }

                                } else {
                                        echo_log("URL FORMAT IS NOT OK, RETRIEVING DATA FROM LOCALLY DEFINED POOL in /home/ethos/local.conf ...");
                                }
                        }  else {
                                echo_log("REMOTE POOL IS NOT DEFINED, RETRIEVING DATA FROM LOCALLY DEFINED POOL in /home/ethos/local.conf ...");
                        }
                        `/usr/bin/dos2unix -q /home/ethos/local.conf`;
                        `/opt/ethos/sbin/ethos-motd-generator`;

?>
