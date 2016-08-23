Xero API for Yii2
=================
An extension for using the Xero API from within Yii2.

This extension is based on Xero's XeroOAuth-PHP code at https://github.com/XeroAPI/XeroOAuth-PHP

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist slightlydiff/yii2-xero "*"
```

or add

```
"slightlydiff/yii2-xero": "*"
```

to the require section of your `composer.json` file.


Configuration
-------------

Add the following to your application conmfiguration file:

```php
        'xeroApi' => [
            'class' => 'slightlydiff\xero\XeroApi',
            'rsa_public_key' => '@app/config/certs/xero_publickey.cer',
            'rsa_private_key' => '@app/config/certs/xero_privatekey.pem',
            'consumer_key' => 'yourconsumerkey',
            'shared_secret' => 'yoursharedsecret',
            'useragent' => 'XeroOAuth-PHP'
        ],
```
modifying the above for your own Xero consumer key, shared secret and the path to you public / private key pair.

Usage
-----

>For a GET request:
>- The first parameter is the only required parameter and must be 'GET', 'POST', 'PUT' or 'DELETE'
>- The second parameter can be FALSE or a string ID if you want to get a single record by ID
>- The third parameter can be a date/time, in any format, if you want to fetch all records modified since that date
>- The fourth parameter can be an array of filters as described at https://developer.xero.com/documentation/getting-started/http-requests-and-responses/. This allows you to filter the query and order the returned data.
>
> All parameters are optional except the first.  If not parameters are passed then all records of the requested type will be returned
>
>For a POST or PUT request:
>- The first parameter must be the method, as above, and the second param must be a multidimensional array of the data being passed as in the examples below.

To create a contact see https://developer.xero.com/documentation/api/contacts/ for the format and apply as follows:
```php
$new_contact = array(
    array(
        "Name" => "Joe Bloggs",
        "FirstName" => "Joe",
        "LastName" => "Bloggs",
        "Addresses" => array(
            "Address" => array(
                array(
                    "AddressType" => "POSTAL",
                    "AddressLine1" => "123 Anystreet",
                    "City" => "Anytown",
                    "PostalCode" => "1234"
                ),
                array(
                    "AddressType" => "STREET",
                    "AddressLine1" => "123 Anystreet",
                    "City" => "Anytown",
                    "PostalCode" => "1234"
                )
            )
        )
    )
);
$result = $xero->Contacts('POST', $new_invoice);
```

To create a invoice or credit note see https://developer.xero.com/documentation/api/invoices/ for the format and apply as follows:
```php
$new_invoice = array(
    array(
        "Type"=>"ACCREC",
        "Contact" => array(
            "ContactID" => "[contact id]"
        ),
        "Date" => "2016-08-01",
        "DueDate" => "2016-08-30",
        "Status" => "SUBMITTED",
        "LineAmountTypes" => "Exclusive",
        "LineItems"=> array(
            "LineItem" => array(
                array(
                    "Description" => "Some product description",
                    "Quantity" => "1.0000",
                    "UnitAmount" => "123.00",
                    "AccountCode" => "200"
                )
            )
        )
    )
);
$result = $xero->Invoices('POST', $new_invoice);
```

To create a payment see https://developer.xero.com/documentation/api/payments/ for the format and apply as follows:
```php
$new_payment = array(
    array(
        "Invoice" => array(
            "InvoiceNumber" => "INV-1234"
        ),
        "Account" => array(
            "Code" => "[account code]"
        ),
        "Date" => "2016-08-30",
        "Amount"=>"123.00",
    )
);
$result = $xero->Payments('POST', $new_payment);
```

To get details of an account with the name "Joe Bloggs"
```php
$result = $xero->Accounts('GET', false, false, array("Name"=>"Joe Bloggs") );
```
See https://developer.xero.com/documentation/api/accounts/ for a list of all parameters. 



To get details of all contacts
```php
$result = $xero->Contacts;
```

To get details of all contacts modified in the last 24 hours
```php
$result = $xero->Contacts('GET', false, gmdate("M d Y H:i:s", (time() - (1 * 24 * 60 * 60))), false);
```

To get details of a contact by ID
```php
$result = $xero->Contacts('GET', 'contact id here', false, false);
```

To get details of all contacts whos name contains "Bloggs" and order the results by Name
```php
$result = $xero->Contacts('GET', false, false, ['where' => 'Name.Contains("Bloggs")', 'order' => 'Name DESC']);
```