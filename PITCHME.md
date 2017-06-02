## DBUnit and PHPUnit in Hosted
and more broadly
### Evolving the codebase with the aid of testing

#HSLIDE

### The vicious circle of uncertainty

We can't test this code until we change it!

It is too risky to change this code because we don't have tests!

#HSLIDE

## NO!

Testing gives us the confidence to make changes in our architecture and is essential to allowing us to modernize key parts of our application (e.g. automations, contacts, segments).

#HSLIDE

### Reasons we "can't" test PHP code in Hosted

 - there isn't an easy entry point from an API call (or if there is, it's not isolated enough)
 - we depend on global state, especially weird stuff in `engine` and `prepend`
 - database interactions in a code path are complex, or the queries are deeply tied into the business logic
 - the code calls an external service or has a side-effect (e.g. sending an email)

#HSLIDE

## What is unit testing?

Testing your code in *isolated* chunks to verify that the code meets *tightly defined* requirements.

(Ideally)

In a perfect world, code is broken up into discrete functions with no side-effects and no global dependencies, and unit testing creates a virtuous cycle where tests identify parts of your in-progress work that needs to be refactored.

---?code=code/ideal.php

#HSLIDE

A lot of our most important code is [difficult to test](https://github.com/ActiveCampaign/Hosted/blob/version-8.12/admin/functions/series.php#L1682).

#HSLIDE

### PHPUnit / DBUnit

A new tool in our toolbox for testing Hosted code.

Allows you to write tests that interact with a real database and isolate code that previously couldn't be effectively touched by Behat tests.

#HSLIDE

### HostedTestCase

Bootstraps the application, including required global state, global functions, and services like memcache. Use if you need to use global functions and the database but don't need DBUnit or fixtures.

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

** DBUnit won't actually truncate everything in the database for you, so make sure you define empty tables for anything you're expecting to be empty.

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

Every test that extends `HostedDbTestCase` needs to set a `$fixturePath`. The test class will look for and automatically load either:
 - `myTestMethod.yml`: fixture named after the test method currently being run
 - `MyTestClass.yml`: fixture after the test class

If for some reason you need DBUnit but don't need a fixture or want to do something weird, you can override `getDataset`.

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

$this->assertEquals(4, $this->getConnection()->getRowCount("em_fb_audience"));
```

@[1-4](You can query the database to create a dataset DBUnit understands)
@[6-8](You can also load fixtures to use for comparisons)
@[10-12](The test class includes functions for comparing these datasets)
@[1-12](This is extremely useful for testing state that normally isn't exposed in the API.)

#HSLIDE

### Running tests

1. Automatically with `composer test`
2. Manually with `vendor/bin/phpunit <filename>`
3. In PHPStorm (with xdebug support)

#HSLIDE

###How do we test global functions?

`¯\_(ツ)_/¯`

Let's look at an example!

#HSLIDE

### PHP-DI Service Container

Provides a path towards eliminating globally defined resources and testing code that is untestable 

#HSLIDE

Making untestable code testable

```php
function global_function_do_a_facebook_thing($myData) {
        $deepDataClient = new DeepDataClient();
        return $deepDataClient->doAFacebookThing($myData);
}

function global_function_do_a_facebook_thing($myData) {
        $deepDataClient = $container->get(DeepDataClient::class);
        return $deepDataClient->doAFacebookThing($myData);
}

[
        // technically this is redundant because of autowiring
        DeepDataClient::class => DI\Object(DeepDataClient::class)
]

$builder = new DI\ContainerBuilder();
$container = $builder->build();

$mockedDeepDataClient = $this->createMock(DeepDataClient::class);
$container->set(DeepDataClient::class, $mockedDeepDataClient);
```

@[1-4](Basically impossible to test!)
@[6-9](Still not great, but possible to test)
@[11-15](Config in production)
@[16-20](Now when the function runs, we'll get a mock instead of the real class)

#HSLIDE

Moving away from global resources

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

@[1](Account table isn't set up locally)
@[3-10](Workarounds and globals for different environments)
@[12-19](Using globals as a cache)

#HSLIDE

Just an example, don't @ me

```php
[
        Account::class => DI\Factory(function ($row) {
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

$account = $container->get(Account::class);
```

@[2-4](Factory method can be written to encapsulate how to build an object)
@[5-18](Can define data in the container as well)
@[21](By default this is a singleton, you'll always get the same instance)
@[1-21](By defining different configs in tests, we can very easily replace any of this with mocks)

#HSLIDE

### How should I test this?

1. For new code, write testable classes with injected dependencies, and unit test them.
2. For v3 API endpoints, write Behat tests and consider unit tests for business logic.
3. For everything else, there's HostedTestCase and HostedDbTestCase (tm)

#HSLIDE

### What can I do today

PHPUnit/DBUnit will be merged in as soon as someone clicks merge on my PR.

PHP-DI Container will be coming along with tests for automations.

#HSLIDE

Thanks!
