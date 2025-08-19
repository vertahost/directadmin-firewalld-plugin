**#Still under construction**

# directadmin-firewalld-plugin
A plugin for DirectAdmin to control firewalld on Almalinux 9 and other DirectAdmin supported OS's

#Requirements:
- **JQ**
    yum/dnf install jq
- 


After a fresh install of Directadmin, you can open port 2222 with the following commands:

firewall-cmd --permanent --zone=public --add-port=2222/tcp

systemctl restart firewalld.service


#How to install as root on a fresh install of directadmin with no CSF installed

cd /usr/local/directadmin/plugins/

git clone https://github.com/vertahost/directadmin-firewalld-plugin.git

cd /usr/local/directadmin/plugins/directadmin-firewalld-plugin

mv firewalld_manager ..

cd ../firewalld_manager

./install.sh

If you do not have PHP installed yet, install that, for example:

da build set php1_mode php-fpm
da build set php1_release 8.3


#How to use

Firewalld uses groups of rules called zones. Each zone includes a set of Services, ports, IPs, or "rich rules" that dictate how traffic will flow.


