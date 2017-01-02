Follow the author: @pressplayplease

# aws-acl-fail2ban
This package includes a script and fail2ban configuration that allows you to use fail2ban when utilizing AWS elastic load balancer (ELB) and an apache webserver. It is useful to protect your site against DoS and brute force attacks when behind a reverse proxy load balancer like ELB. Special consideration is required when using ELB with fail2ban because ELB only forwards the client IP to the server in an X-Forwarded-For header. Following this guide will enable you to use ELB, Apache webservers and AWS ACL together with fail2ban for an dynamic firewall solution.

Dependencies
------
* AWS CLI must be installed and your access credentials must be setup as specified in AWS CLI docs (either through a ~/.aws/config or through an environment variable). ** IF someone would like to update the code to use AWS composer package, I'm sure that would make many people's lives easier **
* An ACL must be created and associated with your load balancer and webservers in AWS
* Make sure that the credentials you've configured in AWS for the AWS CLI allow read/write to ACL resources.
* Your apache logs must log the X-Forwarded-For header instead of the ELB IP address. Instructions on how to do so are found below.

Installation
-----
1. The recommended method of installation is by using composer to install: `composer require anthonymartin/aws_acl_fail2ban` - alternatively, you can clone or download this repository.
2. Ensure that your apache configuration and your fail2ban configuration is correct. Some help has been provided below.

Apache Configuration
------
1. Enable RemoteIP mod
2. Update apache configuration - the configuration below is what my configuration found at /etc/apache2/apache2.conf looks like. Be sure to include RemoteIPHeader and replace LogFormat with the lines found below.
  
  ```
    RemoteIPHeader X-Forwarded-For
    LogFormat "%v:%p %h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" vhost_combined
    LogFormat "%a %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined
    LogFormat "%h %l %u %t \"%r\" %>s %O" common
    LogFormat "%{Referer}i -> %U" referer
    LogFormat "%{User-agent}i" agent
    ```

3. run `sudo service apache2 reload`
 

fail2ban Configuration
-----
1. Copy `fail2ban/action.d/aws-acl.conf` in `/etc/fail2ban/action.d/` directory
2. Copy `fail2ban/filter.d/aws-acl-example-filter.conf` to `/etc/fail2ban/filter.d/` directory
2. Update `actionban` and `actionunban` definitions in `/etc/fail2ban/action.d/aws-acl.conf`. You need tos replace both instances of `/path/to/aws-acl-fail2ban` to the location of `aws-acl-fail2ban` on your server. If you've installed with composer, the location is `vendor/bin/aws-acl-fail2ban`, otherwise the location is in `bin/aws-acl-fail2ban`. You should use the absolute path when updating `actionban` and `actionunban`.
3. Replace both instances of `ACL_ID_GOES_HERE` in `/etc/fail2ban/action.d/aws-acl.conf` with the acl-id of the ACL that you would like to use.
3. Create or update your jail.local configuration. Replace the filter definition below with your own filter if you have one. The example filter configuration included in this package will match all POST and GET requests that are not images, css or javascript (note this doesn't include font files as of this time, but it probably should). The filter together with the jail.local configuration here will be useful for stopping crawl attempts and certain types of HTTP Flood DoS or brute force attacks. Here's an example jail.local configuration:
  
  ```
  [aws-acl-example]
  enabled = true
  filter = aws-acl-example-filter
  action = aws-acl
    sendmail-whois[name=LoginDetect, dest=youremail@example.com, sender=youremail@local.hostname, sendername="Fail2Ban"]
  logpath = /var/log/apache2/access.log
  maxretry = 60
  findtime = 60
  bantime = 14400
  ```
  
