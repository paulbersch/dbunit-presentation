# DBUnit and PHPUnit in Hosted

#HSLIDE

This code is untestable!

It is too risky to change this code!

#HSLIDE

## NO!

Testing gives us the confidence to make changes in our architecture.

#HSLIDE

## What is unit testing?

Testing your code function by function, in isolation, to verify that the code meets tightly defined requirements.

Ideally.

#HSLIDE

In a perfect world, code is broken up into discrete functions with no side-effects and no global dependencies.

---?code=code/ideal.php

#HSLIDE

But in real life we have [less testable code](https://github.com/ActiveCampaign/Hosted/blob/version-8.12/admin/functions/series.php#L1682).

#HSLIDE

### Writing a DBUnit Test

1. Set up fixture (`getDataset`)
2. Exercise System Under Test
3. Verify outcome
4. Teardown

However, it won't actually truncate everything in the database for you, so make sure you define empty tables for anything you're expecting to be empty.

#HSLIDE

### Defining fixture data

---?code=code/fixture.yml

#HSLIDE

### Test fixtures

If you're using

#HSLIDE

### Where do things go?

```
├── HostedDbTestCase.php
├── HostedTestCase.php
├── ac_global
│   └── functions
│       └── strTest.php
├── admin
│   └── classes
│       └── FbAudienceSyncTest.php
├── engine.inc.php
└── fixtures
    └── admin
        └── classes
            ├── FbAudienceSyncTest.yml
            ├── TestDeleteAudienceIfRemovedInFacebook-dd-response.json
            ├── TestDeleteAudienceIfRemovedInFacebook-expected.yml
            ├── TestSyncAccountFirstTime-dd-response.json
            ├── TestSyncAccountFirstTime-expected.yml
            ├── TestSyncAccountUpdateAudience-dd-response.json
            └── TestSyncAccountUpdateAudience-expected.yml
```

#HSLIDE

These aren't _really_ unit tests. They're not exactly integration tests, either. But they're a solid step towards defining behavior and giving us a path towards refactoring parts of our app we can't easily change.

#HSLIDE

###How do we test global functions?

`¯\_(ツ)_/¯`


#HSLIDE

### PHP-DI Service Container

Provides a path towards eliminating globally defined resources and testing code that is untestable 

```
function global_function_do_a_facebook_thing($myData) {
        $deepDataClient = new DeepDataClient();
        return $deepDataClient->doAFacebookThing($myData);
}
```

```
function global_function_do_a_facebook_thing($myData) {
        $deepDataClient = $container->get(DeepDataClient::class);
        return $deepDataClient->doAFacebookThing($myData);
}

// production-config.php
[
        // technically this is redundant because of autowiring
        DeepDataClient::class => DI\Object(DeepDataClient::class)
]

// in a test
$builder = new DI\ContainerBuilder();
$container = $builder->build();

$mockedDeepDataClient = $this->createMock(DeepDataClient::class);
$container->set(DeepDataClient::class, $mockedDeepDataClient);

// now when the function runs, it will get the mock!


```

#HSLIDE

Old code

```php
public static $_static_table  = "_account.accounts";

public function __construct($row = array()) {
        if (!$row) {
                $row = $_SESSION[$GLOBALS["domain"]];
        }

        parent::__construct($row);
        $this->useTable(static::$_static_table); // remove when we pull library everywhere
}

public static function myself() {
        if (isset($GLOBALS['_my_acct'])) {
                return $GLOBALS['_my_acct'];
        }

        $GLOBALS['_my_acct'] = new Account();
        return $GLOBALS['_my_acct'];
}
```

New code (maybe)

```php
[
        Account::class => DI\Factory(function ($row) {
                // you can easily change this config in a test context
                return new Account($row);
        })->parameter('row', DI\get('domain')),
        'domain' => [
            "id" => 5469,
            "account" => 'localhost/hosted',
            "redis" => 'localhost:6379',
            "plan_tier" => 3,
            "plan" => 0,
            "down4" => "nobody",
            "em_monthlycredits" => PHP_INT_MAX,
            "beta" => 1,
            "memcache" => 'localhost',
            "onaws" => 0,
            "cname" => 0,
            "mailerid" => 0,
        ]
]

// by default this is a singleton, you'll always get the same instance
$account = $container->get(Account::class);
```


#HSLIDE

### How should I test this?

1. For new code, write testable classes with injected dependencies, and unit test them.
2. For v3 API endpoints, write Behat tests and consider unit tests for business logic.
3. For everything else, there's HostedTestCase and HostedDbTestCase (tm)

#HSLIDE

Thanks!
