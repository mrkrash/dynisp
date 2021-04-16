# Dynamic DNS for Ispconfig 3
I have a DNS server managed by ISPConfig and I want update a *dynamic entry*. ISPConfig can be managed by a remote user through a SOAP call, then this is a PHP script which once run, executes a soap call to the ISPconfig server with the received parameters to update the record in question.
As client, you can use [ez-ipupdate](http://www.ez-ip.net) and, in configuration, set as remote service "custom DynDNS".
I have tested with a Huawei Home Fiber Router, linux based, with ez-ipupdate as client for dynamic IP update.
# Tribute
I have discovered the initial on an italian's blog site: [Ettore Dreucci](https://ettore.dreucci.it/blog/ispconfig-ddns/)