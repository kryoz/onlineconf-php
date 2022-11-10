[Onlineconf](https://github.com/onlineconf) client library for PHP

Simple usage in a code:
````
$paramsBag = new ConfigBag('myproject');
$password = $paramsBag->get('level1.level2.secret'); 

````

Customization:
````
$paramsBag = new ConfigBag(
    'myproject.level1', 
    '.'
    '/',
    new Client($myPsrLogger, '/opt/onlineconf/TREE.cdb')
);
$password = $paramsBag->get('level2.secret'); 

````

where params in `ConfigBag` constructor are:

* `myproject.level1` - namespace your params tree in Onlineconf
* `.` - path delimiter for addressing param through the tree 
* `/` - path delimiter configured in your Onlineconf admin
* $myPsrLogger - your PSR-compliant application logger 
* `/opt/onlineconf/TREE.cdb` - the location of onlineconf database file, which was brought by `onlineconf-updater` onto 
your backend.

It's up to you to handle ConfigBag as single instance in your app. Use DependencyInjection or Singleton as a wrapper.