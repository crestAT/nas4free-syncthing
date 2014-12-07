Syncthing
---------

Extension to install / configure / backup / update / manage and remove Syncthing (STG) application on NAS4Free (N4F) servers.

The extension
- works on all plattforms
- does not need jail or pkg_add.
- add pages to NAS4Free WebGUI extensions
- features configuration, application update & backup management, scheduling and log view with filter / search capabilities

INSTALLATION
------------
1. Prior to the installation make a backup of the N4F configuration via SYSTEM | BACKUP/RESTORE | Download configuration.
2. Go to the N4F Webgui menu entry ADVANCED | COMMAND, copy the following line (change the path /mnt/DATA/extensions to 
    your needs - a persistant place where all extensions are/should be) paste it to the command field and push "Execute", this will copy the installer to your system:
        <pre>cd /mnt/DATA/extensions && \
fetch https://raw.github.com/crestAT/nas4free-syncthing/master/stg_install.php && \
fetch https://raw.github.com/crestAT/nas4free-syncthing/master/stg-install.php && \
chmod 770 bts*install.php && \
echo "fetch OK"
</pre>
3. After you see "fetch OK" execute the following line (changed the path /mnt/DATA/extensions to your persistant place), this will install the extension on your system: 
        <pre>/mnt/DATA/extensions/stg_install.php</pre>
4. After successful completion you can access the extension from the WebGUI menu entry EXTENSIONS | Syncthing.

<pre>
HISTORY
-------
Version Date        Description
0.1.0   2014.12.07  first public release

N: ...  new feature
C: ...  changes
F: ...  bug fix
</pre>
