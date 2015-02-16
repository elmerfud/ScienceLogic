# EM7 API Library

This is an early alpha release of an PHP library for accessing the EM7 API.  

It works by using chaining method calls together for your query.  Currently there's many functions missing or not working properly, but some of the basic gets should work. 

## Examples
Setting up the class.
```php
$cfg['uri'] = 'https://mysite.com/api';
$cfg['username'] = 'api_user';
$cfg['password'] = 'api_pass';

$em7 = new EM7($cfg);
```
Get all devices, returns and arrary of arrays (URI & description)
```php
$all_devices = $em7->device()->get(true);
```
Get a filtered device list
```php
$devices = $em7->device()->filter('ip','192.168')->limit(10)->get();
```
Get device id 102
```php
$device = $em7->device(102)->get();
```
Update a device
```php
$new_data['name'] = 'new device name';
$em7->device(102)->post($new_data);
```
Filter returned data through a call back function.  This examples returns an array where $k = DID & $v = Name
```php
$devices = $em7->device()->filter('ip','','.not')->callback('process_device_list')->get(true);

function process_device_list($input)
{
   $out = array();
    while ($entry = array_pop($input)) {
      $id = preg_replace('/\D/','',$entry['URI']);
      $out[$id] = $entry['description'];
    }
    return $out;
}
```

License
----
Beerware - buy me a beer if you like it.

