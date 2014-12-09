<?php
/* 
    stg-install.php
     
    Copyright (c) 2014 Andreas Schmidhuber
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
// Version Date        Description
// 0.1.1   2014.12.09  F: renew product_version on auto-upgrade
// 0.1.0   2014.12.07  first public release
// 0.0.2   2014.12.06  new installer
// 0.0.1   2014.12.02  first beta

$appname = "Syncthing";

require_once("config.inc");

$arch = $g['arch'];
$platform = $g['platform'];
if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64")) { echo "unsupported architecture!\n"; exit(1);  }
if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "unsupported platform!\n";  exit(1); }

// install extension
global $input_errors;
global $savemsg;

$install_dir = "./";
if (isset($config['syncthing']['rootfolder'])) { $install_dir = dirname($config['syncthing']['rootfolder'])."/"; }

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}master.zip 'https://www.a3s.at/_blog/_NAS4FREE/fp-plugins/downloadctr/res/download.php?x=syncthing/syncthing-v011.zip'", true);
if ($return_val == 0) {
    $return_val = mwexec("tar -xf {$install_dir}master.zip -C {$install_dir} --exclude='.git*' --strip-components 1", true);
    if ($return_val == 0) {
        exec("rm {$install_dir}master.zip");
        exec("chmod -R 775 {$install_dir}syncthing");
        if (is_file("{$install_dir}syncthing/version.txt")) { $file_version = exec("cat {$install_dir}syncthing/version.txt"); }
        else { $file_version = "n/a"; }
        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /"); }
}
else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip"); }

// install application on server
if ( !isset($config['syncthing']) || !is_array($config['syncthing'])) {
	$cwdir = getcwd();
    $config['syncthing'] = array();
	$path1 = pathinfo($cwdir);
	$config['syncthing']['appname'] = $appname;
	$config['syncthing']['rootfolder'] = $path1['dirname']."/".$path1['basename']."/syncthing/";
	$config['syncthing']['backupfolder'] = $config['syncthing']['rootfolder']."backup/";
	$config['syncthing']['updatefolder'] = $config['syncthing']['rootfolder']."update/";
    $config['syncthing']['version'] = exec("cat {$config['syncthing']['rootfolder']}version.txt");
	$cwdir = $config['syncthing']['rootfolder'];
    $i = 0;
    if ( is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
        for ($i; $i < count($config['rc']['postinit']['cmd']);) {
            if (preg_match('/syncthing/', $config['rc']['postinit']['cmd'][$i])) break;
            ++$i;
        }
    }
    $config['rc']['postinit']['cmd'][$i] = $config['syncthing']['rootfolder']."syncthing_start.php";
    if ($arch == "i386" || $arch == "x86") { $config['syncthing']['architecture'] = "386"; }
    else { $config['syncthing']['architecture'] = "amd64"; }
	$config['syncthing']['download_url'] = "https://github.com/syncthing/syncthing/releases/download/v0.10.6/syncthing-freebsd-".$config['syncthing']['architecture']."-v0.10.6.tar.gz";
	$config['syncthing']['previous_url'] = $config['syncthing']['download_url'];
    mwexec ("fetch -o {$config['syncthing']['rootfolder']}stable {$config['syncthing']['download_url']}", true);
    exec ("cd {$config['syncthing']['rootfolder']} && tar -xzvf stable --strip-components 1");
    exec ("rm {$config['syncthing']['rootfolder']}stable");
    if ( !is_file ($cwdir.'syncthing') ) { echo 'Executable file "syncthing" not found, installation aborted!'; exit (3); }
    $config['syncthing']['product_version'] = exec("su root -c '{$config['syncthing']['rootfolder']}syncthing -version'");
    if (!is_dir ($config['syncthing']['rootfolder'].'config')) { exec ("mkdir -p ".$config['syncthing']['rootfolder'].'config'); }
    if (!is_dir ($config['syncthing']['backupfolder'])) { exec ("mkdir -p ".$config['syncthing']['backupfolder']); }
    if (!is_dir ($config['syncthing']['updatefolder'])) { exec ("mkdir -p ".$config['syncthing']['updatefolder']); }
   	exec ("cp ".$cwdir."syncthing ".$config['syncthing']['backupfolder']."syncthing-".$config['syncthing']['product_version']);
    if ($config['syncthing']['product_version'] == '') { $config['syncthing']['product_version'] = 'n/a'; }
    write_config();
    require_once("{$config['syncthing']['rootfolder']}stg-start.php");
    echo "\n".$appname." Version ".$config['syncthing']['product_version']." installed";
    echo "\n\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure \nthe application (don't forget to refresh the WebGUI before use)!\n";
}
else { 
    require_once("{$config['syncthing']['rootfolder']}stg-start.php");
}
?>
