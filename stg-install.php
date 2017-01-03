<?php
/* 
    stg-install.php
     
    Copyright (c) 2014 - 2017 Andreas Schmidhuber
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
$version = "v0.2";			// extension version
$v = "v0.14.8";				// application version
$appname = "Syncthing";
$config_name = strtolower($appname);
$version_striped = str_replace(".", "", $version);

require_once("config.inc");

$arch = $g['arch'];
$platform = $g['platform'];
// no check necessary since the extension is for all archictectures/platforms/releases
//if (($arch != "i386" && $arch != "amd64") && ($arch != "x86" && $arch != "x64" && $arch != "rpi" && $arch != "rpi2")) { echo "\f{$arch} is an unsupported architecture!\n"; exit(1);  }
//if ($platform != "embedded" && $platform != "full" && $platform != "livecd" && $platform != "liveusb") { echo "\funsupported platform!\n";  exit(1); }

// install extension
global $input_errors;
global $savemsg;

$install_dir = dirname(__FILE__)."/";                           // get directory where the installer script resides
if (!is_dir("{$install_dir}syncthing/backup")) { mkdir("{$install_dir}syncthing/backup", 0775, true); }
if (!is_dir("{$install_dir}syncthing/update")) { mkdir("{$install_dir}syncthing/update", 0775, true); }

// check FreeBSD release for fetch options >= 9.3
$release = explode("-", exec("uname -r"));
if ($release[0] >= 9.3) $verify_hostname = "--no-verify-hostname";
else $verify_hostname = "";

$return_val = mwexec("fetch {$verify_hostname} -vo {$install_dir}master.zip 'https://github.com/crestAT/nas4free-syncthing/releases/download/{$version}/syncthing-{$version_striped}.zip'", true);
if ($return_val == 0) {
    $return_val = mwexec("tar -xf {$install_dir}master.zip -C {$install_dir} --exclude='.git*' --strip-components 1", true);
    if ($return_val == 0) {
        exec("rm {$install_dir}master.zip");
        exec("chmod -R 775 {$install_dir}syncthing");
        require_once("{$install_dir}syncthing/ext/extension-lib.inc");
        $config_file = "{$install_dir}syncthing/ext/{$config_name}.conf";
        if (is_file("{$install_dir}syncthing/version.txt")) { $file_version = exec("cat {$install_dir}syncthing/version.txt"); }
        else { $file_version = "n/a"; }
        $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip corrupt /"); return;}
}
else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "master.zip"); return;}

// install / update application
if (($configuration = ext_load_config($config_file)) === false) {
    $configuration = array();             // new installation or first time with json config
    $new_installation = true;
}
else $new_installation = false;

// check for $config['syncthing'] entry in config.xml, convert it to new config file and remove it
if (isset($config[$config_name]) && is_array($config[$config_name])) {
    $configuration = $config[$config_name];								// load config
    unset($config[$config_name]);										// remove old config
}

$configuration['appname'] = $appname;
$configuration['rootfolder'] = "{$install_dir}syncthing/";
$configuration['backupfolder'] = $configuration['rootfolder']."backup/";
$configuration['updatefolder'] = $configuration['rootfolder']."update/";
$configuration['version'] = exec("cat {$configuration['rootfolder']}version.txt");
$configuration['postinit'] = "/usr/local/bin/php-cgi -f {$configuration['rootfolder']}syncthing-start.php";
$configuration['shutdown'] = "killall syncthing";
if ($arch == "i386" || $arch == "x86") { $configuration['architecture'] = "386"; }
else { $configuration['architecture'] = "amd64"; }
$configuration['download_url'] = "https://github.com/syncthing/syncthing/releases/download/{$v}/syncthing-freebsd-{$configuration['architecture']}-{$v}.tar.gz";
$configuration['previous_url'] = $configuration['download_url'];
mwexec ("fetch -o {$configuration['rootfolder']}stable {$configuration['download_url']}", true);
exec ("cd {$configuration['rootfolder']} && tar -xzf stable --strip-components 1");
exec ("rm {$configuration['rootfolder']}stable");
if ( !is_file ($configuration['rootfolder'].'syncthing') ) echo 'Executable file "syncthing" not found!';
$configuration['product_version'] = $v;
if (!is_dir ($configuration['rootfolder'].'config')) { exec ("mkdir -p ".$configuration['rootfolder'].'config'); }
if (!is_dir ($configuration['backupfolder'])) { exec ("mkdir -p ".$configuration['backupfolder']); }
if (!is_dir ($configuration['updatefolder'])) { exec ("mkdir -p ".$configuration['updatefolder']); }
exec ("cp ".$configuration['rootfolder']."syncthing ".$configuration['backupfolder']."syncthing-".$configuration['product_version']);
if ($configuration['product_version'] == '') { $configuration['product_version'] = 'n/a'; }

ext_remove_rc_commands($config_name);
$configuration['rc_uuid_start'] = $configuration['postinit'];
$configuration['rc_uuid_stop'] = $configuration['shutdown'];
ext_create_rc_commands($appname, $configuration['rc_uuid_start'], $configuration['rc_uuid_stop']);
write_config();
ext_save_config($config_file, $configuration);

if ($new_installation) echo "\nInstallation completed, use WebGUI | Extensions | ".$appname." to configure the application!\n";
else $savemsg = sprintf(gettext("Update to version %s completed!"), $file_version);
require_once("{$configuration['rootfolder']}syncthing-start.php");
?>
