Firewalld Manager — DirectAdmin plugin (Admin only)

Install via UI:
  Admin Level → Plugin Manager → Add Plugin → upload firewalld_manager_v1.2.0.tgz

Install via CLI:
  cd /usr/local/directadmin/plugins
  tar -xzf /path/to/firewalld_manager_v1.2.0.tgz
  cd firewalld_manager && ./install.sh

Troubleshooting:
  - Ensure /etc/sudoers includes:  #includedir /etc/sudoers.d
  - Ensure /etc/sudoers.d/directadmin_firewalld_manager exists and passes: visudo -cf /etc/sudoers.d/directadmin_firewalld_manager
  - Test as diradmin:
      su -s /bin/bash -c '/usr/bin/sudo -n /usr/local/directadmin/plugins/firewalld_manager/scripts/fwctl.sh status-json' diradmin

If you get a white page in DA on a fresh install, make sure you have PHP installed properly:
php -v
