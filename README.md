**#Still under construction**

# directadmin-firewalld-plugin
A plugin for DirectAdmin to control firewalld on Almalinux 9 and other DirectAdmin supported OS's

#Requirements:
- **JQ**
    yum/dnf install jq
- 


#How to install



#How to use

Firewalld uses groups of rules called zones. Each zone includes a set of Services, ports, IPs, or "rich rules" that dictate how traffic will flow.


After a fresh install of Directadmin, you can open port 2222 with the following commands:

firewall-cmd --permanent --zone=public --add-port=2222/tcp

systemctl restart firewalld.service
