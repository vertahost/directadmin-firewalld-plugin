**Still under construction**

# directadmin-firewalld-plugin
A plugin for DirectAdmin to control firewalld on Almalinux 9 and other DirectAdmin supported OS's

#Requirements:
- **JQ**
    yum/dnf install jq
- PHP installed in DirectAdmin


After a fresh install of Directadmin, you can open port 2222 with the following commands:

```
firewall-cmd --permanent --zone=public --add-port=2222/tcp

systemctl restart firewalld.service
```

# How to install as root on a fresh install of directadmin with no CSF installed

```
cd /usr/local/directadmin/plugins/

git clone https://github.com/vertahost/directadmin-firewalld-plugin.git

cd /usr/local/directadmin/plugins/directadmin-firewalld-plugin

mv firewalld_manager ..

cd ../firewalld_manager

./install.sh
```

If you do not have PHP installed yet, install that, for example:

```
da build set php1_mode php-fpm

da build set php1_release 8.3
```


# How to use

Firewalld uses groups of rules called zones. Each zone includes a set of Services, ports, IPs, or "rich rules" that dictate how traffic will flow.


The firewall-cmd bash wrapper can be directly accessed via command line as well to do what you can do via the GUI
```
]# ./scripts/fwctl.sh
Usage:
  ./fwctl.sh status-json
  ./fwctl.sh zone-info-json <zone>
  ./fwctl.sh get-services-json
  ./fwctl.sh list-interfaces-json
  ./fwctl.sh add-service <zone> <service> [permanent: yes|no]
  ./fwctl.sh remove-service <zone> <service> [permanent: yes|no]
  ./fwctl.sh add-port <zone> <port/proto> [permanent: yes|no]
  ./fwctl.sh remove-port <zone> <port/proto> [permanent: yes|no]
  ./fwctl.sh add-source <zone> <cidr> [permanent: yes|no]
  ./fwctl.sh remove-source <zone> <cidr> [permanent: yes|no]
  ./fwctl.sh add-rich-rule <zone> <rule> [permanent: yes|no]
  ./fwctl.sh remove-rich-rule <zone> <rule> [permanent: yes|no]
  ./fwctl.sh add-interface <zone> <iface> [permanent: yes|no]
  ./fwctl.sh remove-interface <zone> <iface> [permanent: yes|no]
  ./fwctl.sh create-zone <zone>
  ./fwctl.sh delete-zone <zone>
  ./fwctl.sh set-default-zone <zone>
  ./fwctl.sh panic <on|off>
  ./fwctl.sh icmp-block <add|remove|list> [type]
  ./fwctl.sh service <start|stop|restart|reload|enable|disable|status>
```
