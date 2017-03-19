<?php
/*
    syncthing_update_extension.php
    
    Copyright (c) 2013 - 2017 Andreas Schmidhuber <info@a3s.at>
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
 */
require("auth.inc");
require("guiconfig.inc");

bindtextdomain("nas4free", "/usr/local/share/locale-stg");

$config_file = "ext/syncthing/syncthing.conf";
require_once("ext/syncthing/extension-lib.inc");
if (($configuration = ext_load_config($config_file)) === false) $input_errors[] = sprintf(gettext("Configuration file %s not found!"), "syncthing.conf");
if (!isset($configuration['rootfolder']) && !is_dir($configuration['rootfolder'] )) $input_errors[] = gettext("Extension installed with fault");

$pgtitle = array(gettext("Extensions"), $configuration['appname']." ".$configuration['version'], gettext("Extension Maintenance"));

if (is_file("{$configuration['updatefolder']}oneload")) {
    require_once("{$configuration['updatefolder']}oneload");
}

$return_val = mwexec("fetch -o {$configuration['updatefolder']}version.txt https://raw.github.com/crestAT/nas4free-syncthing/master/syncthing/version.txt", false);
if ($return_val == 0) {
    $server_version = exec("cat {$configuration['updatefolder']}version.txt");
    if ($server_version != $configuration['version']) { $savemsg = sprintf(gettext("New extension version %s available, push '%s' button to install the new version!"), $server_version, gettext("Update Extension")); }
    mwexec("fetch -o {$configuration['rootfolder']}release_notes.txt https://raw.github.com/crestAT/nas4free-syncthing/master/syncthing/release_notes.txt", false);
}
else { $server_version = gettext("Unable to retrieve version from server!"); }

function cronjob_process_updatenotification($mode, $data) {
	global $config;
	$retval = 0;
	switch ($mode) {
		case UPDATENOTIFY_MODE_NEW:
		case UPDATENOTIFY_MODE_MODIFIED:
			break;
		case UPDATENOTIFY_MODE_DIRTY:
			if (is_array($config['cron']) && is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) {
					unset($config['cron']['job'][$index]);
					write_config();
				}
			}
			break;
	}
	return $retval;
}

if (isset($_POST['ext_remove']) && $_POST['ext_remove']) {
    $install_dir = dirname($configuration['rootfolder']);
// kill running process
    exec("killall syncthing");
// remove start/stop commands
	ext_remove_rc_commands("syncthing");
// unlink created  links
	if (is_dir ("/usr/local/www/ext/syncthing")) {
		foreach ( glob( "{$configuration['rootfolder']}ext/*.php" ) as $file ) {
		$file = str_replace("{$configuration['rootfolder']}ext/", "/usr/local/www", $file);
		if ( is_link( $file ) ) { unlink( $file ); } else {} }
		mwexec("rm -rf /usr/local/www/ext/syncthing");
		mwexec("rmdir -p /usr/local/www/ext");    // to prevent empty extensions menu entry in top GUI menu if there are no other extensions installed
	}
// remove cronjobs
    if (isset($configuration['enable_schedule'])) {
		if (is_array($config['cron']) && is_array($config['cron']['job'])) {
        	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $configuration['schedule_uuid_startup']);
			if (is_array($config['cron']) && is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) {
					unset($config['cron']['job'][$index]);
				}
			}
        	write_config();
	    	updatenotify_set("cronjob", UPDATENOTIFY_MODE_DIRTY, $configuration['schedule_uuid_closedown']);
			if (is_array($config['cron']) && is_array($config['cron']['job'])) {
				$index = array_search_ex($data, $config['cron']['job'], "uuid");
				if (false !== $index) {
					unset($config['cron']['job'][$index]);
				}
			}
        	write_config();
            $retval = 0;
            if (!file_exists($d_sysrebootreqd_path)) {
            	$retval |= updatenotify_process("cronjob", "cronjob_process_updatenotification");
            	config_lock();
            	$retval |= rc_update_service("cron");
            	config_unlock();
            }
            $savemsg = get_std_save_message($retval);
            if ($retval == 0) {
            	updatenotify_delete("cronjob");
            }
        }
    }
// remove application section from config.xml
	if ( is_array($configuration ) ) { unset( $configuration ); write_config();}
	header("Location:index.php");
}

if (isset($_POST['ext_update']) && $_POST['ext_update']) {
    $install_dir = dirname($configuration['rootfolder']);
// download installer
    $return_val = mwexec("fetch -vo {$install_dir}/stg-install.php https://raw.github.com/crestAT/nas4free-syncthing/master/stg-install.php", true);
    if ($return_val == 0) {
        require_once("{$install_dir}/stg-install.php"); 
        header("Refresh:8");;
//        $savemsg = sprintf(gettext("Update to version %s completed!"), $configuration['version']);
    }
    else { $input_errors[] = sprintf(gettext("Archive file %s not found, installation aborted!"), "{$install_dir}/stg-install.php"); }
}

bindtextdomain("nas4free", "/usr/local/share/locale");
include("fbegin.inc");?>
<!-- The Spinner Elements -->
<?php include("ext/syncthing/spinner.inc");?>
<script src="ext/syncthing/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->

<form action="syncthing_update_extension.php" method="post" name="iform" id="iform" onsubmit="spinner()">
<?php bindtextdomain("nas4free", "/usr/local/share/locale-stg"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
		<ul id="tabnav">
			<li class="tabinact"><a href="syncthing.php"><span><?=gettext("Configuration");?></span></a></li>
			<li class="tabinact"><a href="syncthing_update.php"><span><?=gettext("Maintenance");?></span></a></li>
			<li class="tabact"><a href="syncthing_update_extension.php"><span><?=gettext("Extension Maintenance");?></span></a></li>
			<li class="tabinact"><a href="syncthing_log.php"><span><?=gettext("Log");?></span></a></li>
		</ul>
	</td></tr>
	<tr><td class="tabcont">
        <?php if (!empty($input_errors)) print_input_errors($input_errors);?>
        <?php if (!empty($savemsg)) print_info_box($savemsg);?>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php html_titleline(gettext("Extension Update"));?>
			<?php html_text("ext_version_current", gettext("Installed version"), $configuration['version']);?>
			<?php html_text("ext_version_server", gettext("Latest version"), $server_version);?>
			<?php html_separator();?>
        </table>
        <div id="update_remarks">
            <?php html_remark("note_remove", gettext("Note"), gettext("Removing Syncthing integration from NAS4Free will leave the installation folder untouched - remove the files using Windows Explorer, FTP or some other tool of your choice. <br /><b>Please note: this page will no longer be available.</b> You'll have to re-run Syncthing extension installation to get it back on your NAS4Free."));?>
            <br />
            <input id="ext_update" name="ext_update" type="submit" class="formbtn" value="<?=gettext("Update Extension");?>" onclick="return confirm('<?=gettext("The selected operation will be completed. Please do not click any other buttons!");?>')" />
            <input id="ext_remove" name="ext_remove" type="submit" class="formbtn" value="<?=gettext("Remove Extension");?>" onclick="return confirm('<?=gettext("Do you really want to remove the extension from the system?");?>')" />
        </div>
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
			<?php html_separator();?>
			<?php html_separator();?>
			<?php html_titleline(gettext("Extension")." ".gettext("Release Notes"));?>
			<tr>
                <td class="listt">
                    <div>
                        <textarea style="width: 98%;" id="content" name="content" class="listcontent" cols="1" rows="25" readonly="readonly"><?php unset($lines); exec("/bin/cat {$configuration['rootfolder']}release_notes.txt", $lines); foreach ($lines as $line) { echo $line."\n"; }?></textarea>
                    </div>
                </td>
			</tr>
        </table>
        <?php include("formend.inc");?>
    </td></tr>
</table>
</form>
<?php include("fend.inc");?>
