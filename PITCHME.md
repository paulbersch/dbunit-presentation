# DBUnit and PHPUnit in Hosted

#HSLIDE

This code is untestable!

It is too risky to change this code!

#HSLIDE

## NO!

Testing gives us the confidence to make changes in our architecture and is essential to allowing us to modernize key parts of our application (e.g. automations, contacts, segments).

#HSLIDE

### Reasons we "can't" test PHP code in Hosted

 - there isn't an easy entry point from an API call (or if there is, it's not isolated enough)
 - we depend on global state, especially weird stuff in `engine` and `prepend`
 - database interactions in a code path are complex
 - the code calls an external service or has a side-effect (e.g. sending an email)

#HSLIDE

A lot of our most important code is [difficult to test](https://github.com/ActiveCampaign/Hosted/blob/version-8.12/admin/functions/series.php#L1682).

#HSLIDE

## What is unit testing?

Testing your code in *isolated* chunks to verify that the code meets *tightly defined* requirements.

(Ideally)

In a perfect world, code is broken up into discrete functions with no side-effects and no global dependencies.

---?code=code/ideal.php

#HSLIDE

### PHPUnit / DBUnit

A new tool in our toolbox for testing Hosted code.

Allows you to write tests that interact with a real database and isolate code that previously couldn't be effectively touched by Behat tests.

#HSLIDE

### HostedTestCase

Bootstraps the application, including required global state, global functions, and services like memcache. Use if you need to use global functions but don't need to set up any fixtures.

### HostedDbTestCase

Includes everything HostedTestCase does, but also includes DBUnit test methods and provides a facility for loading fixtures with initial test data.

#HSLIDE

Tests using these classes aren't _really_ unit tests. They're not exactly integration tests, either. But they're a solid step towards defining behavior and giving us a path towards refactoring parts of our app we can't easily change.

*Endeavor to write code that can be unit tested without using either of these classes*

#HSLIDE

### Writing a DBUnit Test

1. Set up fixture (`getDataset`)
2. Exercise System Under Test
3. Verify outcome
4. Teardown

However, it won't actually truncate everything in the database for you, so make sure you define empty tables for anything you're expecting to be empty.

#HSLIDE

### Defining fixture data

YAML is your friend. No need to write any models.

---?code=code/fixture.yml

@[1](Table names on top level)
@[2-6](Omitted columns will be set to schema default)
@[32](Leave a table empty to truncate it)
@[46](Leave a field empty to set it to NULL)

#HSLIDE

### Test Fixtures for Initial State

Every test that extends `HostedDbTestCase` needs to set a `$fixturePath`. The test class will look for and automatically load either a fixture named after the test method currently being run (myTestMethod.yml) or after the test class (MyTestClass.yml).

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

@[1-2](Test cases!)
@[3-8](Tests follow the directory structure of the app)
@[9](Boo! Hiss!)
@[10-19](Fixtures also follow directory structure of the app)

#HSLIDE

### Making assertions against the database

```php
$actualDataset = $this->getConnection()->createQueryTable(
    'em_fb_audience',
    'SELECT id, externalid, accountid, name, description, approx_count, deleted, deleted_reason FROM em_fb_audience'
);

$expectedDataset = new PHPUnit_Extensions_Database_DataSet_YamlDataSet(
    $this->fixturePath . $expectedStateFixture
);

static::assertTablesEqual($expectedDataset->getTable("em_fb_audience"), $actualDataset);
```

@[1-4](You can query the database to create a dataset DBUnit understands)
@[6-9](You can also load fixtures to use for comparisons)
@[11](The test class includes functions for comparing these datasets)
@[1-11](This is extremely useful for testing state that normally isn't exposed in the API.)


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

#VSLIDE

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
