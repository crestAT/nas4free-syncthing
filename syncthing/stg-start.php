<?php
/* 
    syncthing_start.php

    Copyright (c) 2013 - 2016 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

    The views and conclusions contained in the software and documentation are those
    of the authors and should not be interpreted as representing official policies,
    either expressed or implied, of the FreeBSD Project.
 */
require_once("config.inc");

if (is_file("{$config['syncthing']['rootfolder']}version.txt")) {
    $file_version = exec("cat {$config['syncthing']['rootfolder']}version.txt");
    if ($config['syncthing']['version'] != $file_version) {
        $config['syncthing']['version'] = $file_version;
        write_config();
    }
}

// check for ca_root_certificate and create link if not exists (Syncthing needs it now ...)
if (!is_file("/usr/local/share/certs/ca-root-nss.crt")) {
    mwexec("mkdir -p /usr/local/share/certs", false);
    if (is_file("/usr/local/etc/ssl/cert.pem")) { 
        mwexec("ln -s /usr/local/etc/ssl/cert.pem /usr/local/share/certs/ca-root-nss.crt", false); 
        exec("logger syncthing: cert.pem exists, create link ...");
    }
    else { 
        mwexec("ln -s {$config['syncthing']['rootfolder']}files/cert.pem /usr/local/share/certs/ca-root-nss.crt", false); 
        exec("logger syncthing: cert.pem doesn't exist, use own certificate ...");
    }
}
    
// save backup from auto-upgrade to backup folder and renew product_version
if (is_file("{$config['syncthing']['rootfolder']}syncthing.old")) {
    $version_old = exec("{$config['syncthing']['rootfolder']}syncthing.old -version | awk '{print $2}'");
    mwexec("mv -v {$config['syncthing']['rootfolder']}syncthing.old {$config['syncthing']['backupfolder']}syncthing-{$version_old}", true);
    exec("logger syncthing: Syncthing version {$version_old} has been backuped!");
    $config['syncthing']['product_version'] = exec("{$config['syncthing']['rootfolder']}syncthing -version");
    write_config();
}

if ( !is_dir ( '/usr/local/www/ext/syncthing')) { exec ("mkdir -p /usr/local/www/ext/syncthing"); }
mwexec ("cp {$config['syncthing']['rootfolder']}ext/* /usr/local/www/ext/syncthing/", true);
mwexec ("rm -R /usr/local/share/locale-stg");
exec("ln -s {$config['syncthing']['rootfolder']}locale-stg /usr/local/share/");
if ( !is_link ( "/usr/local/www/syncthing.php")) { exec ("ln -s /usr/local/www/ext/syncthing/syncthing.php /usr/local/www/syncthing.php"); }
if ( !is_link ( "/usr/local/www/syncthing_log.php")) { exec ("ln -s /usr/local/www/ext/syncthing/syncthing_log.php /usr/local/www/syncthing_log.php"); }
if ( !is_link ( "/usr/local/www/syncthing_log.inc")) { exec ("ln -s /usr/local/www/ext/syncthing/syncthing_log.inc /usr/local/www/syncthing_log.inc"); }
if ( !is_link ( "/usr/local/www/syncthing_update.php")) { exec ("ln -s /usr/local/www/ext/syncthing/syncthing_update.php /usr/local/www/syncthing_update.php"); }
if ( !is_link ( "/usr/local/www/syncthing_update_extension.php")) { exec ("ln -s /usr/local/www/ext/syncthing/syncthing_update_extension.php /usr/local/www/syncthing_update_extension.php"); }
if (isset($config['syncthing']['enable'])) { 
    exec("killall -15 syncthing");
    exec("logger syncthing: enabled, start syncthing ...");
    exec($config['syncthing']['command']);
    sleep(5);                                                           // give time to startup
    if (exec('ps acx | grep syncthing')) { exec("logger syncthing: startup OK"); }    
    else { exec("logger syncthing: startup NOT ok" ); } 
}
?>
