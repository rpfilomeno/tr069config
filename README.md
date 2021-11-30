tr069config
===========

Configures Huawei eSpace devices via CLI and TR069

Examples
========

Scan the IP range 10.1.60.15 - 10.1.60.22 for the device and try to login using admin/admin123 via non-https then result to ip-list.txt:

```
php tr069config.php scan --insecure --hash-password --write ip-list.txt 10.1.60.15 10.1.60.22 admin admin123
```
Configure the devices listed in file ip-list.txt by login in using admin/admin123, use the template configuration xml Config-eSpace7950.xml then override the values per device based on whats defined on CSV file accounts-list.csv:
```
php tr069config.php --debug autoconfig --insecure --hash-password admin admin123 Config-eSpace7950.xml accounts-list.csv ip-list.txt
```
Configure a single device and providing different xml template
```
php tr069config.php --debug configure --insecure --hash-password --read accounts-list.csv --write Config-Save-10.1.60.15.xml --yes 10.1.60.15 admin admin123 Config-eSpace7950-EP11MPUA-VER.C.xml
```
```
php tr069config.php --debug configure --insecure --hash-password --read accounts-list.csv --write Config-Save-10.1.60.22.xml --yes 10.1.60.22 admin admin123 Config-eSpace7950-EP11MPUA-VER.B.xml
```
How to build from scratch
=========================

```
git pull https://github.com/rpfilomeno/tr069config.git
```

```
cd tr069config
```

```
curl -sS https://getcomposer.org/installer | php 
```

```
mv composer.phar /usr/local/bin/composer
```

```
composer install
```
```
composer dumpautoload -optimize
```
```
php tr069config.php
```
