# SVXLink-Dashboard-V2
SVXLink Node dashboard repository inspired by pi star dashboard
Originally constructed by SP2ONG and SP0DZ, but suffered from out of date code in PHP and Javascript.
Brought up to date by Chris Jackson ON3URE, G4NAB with new code.

This installation requires that svxlink has been compiled on Debian 11 (raspberry pi OS bullseye), and php version 8.0 or greater, and Apache 2. After that follow the instructions below.

For the moment it is incomplete, a work in progress.


No installation script required, simply cd to /var/www and remove any existing html folder.
<p>
 sudo git clone https://github.com/f5vmr/SVXLink-Dashboard-V2 html
<p>chown svxlink -R html

<p>
To activate mDNS (host.local) use:
```
 sudo apt-get install avahi-daemon avahi-utils
```
The following is required if using an SA818 device.

RF configurator is based on sa818 programming library (https://github.com/0x9900/SA818)
```
sudo apt install python3
sudo apt install python3-pip
sudo pip3 install sa818
```


The svxlink dashboard has some ideas created by ON3URE, G4NAB, SP2ONG, SP0DZ
and added to by ON3URE, G4NAB
