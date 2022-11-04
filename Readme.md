eZ DB Integrity Extension for eZPublish / eZPlatform
====================================================

For eZPlatform 3.0 and later, please check out: https://github.com/tanoconsulting/ezdbintegritybundle

Goals:
------

Allow checking integrity of data in a database (the eZ Publish one, but any other as well).
Allow checking integrity of the eZPublish storage files (images and binary files from content).


Checks supported:
-----------------

1. Foreign Key integrity

    This applies to databases which have no FK enabled, but where parent-child relationships do in fact exist.
    The relationships to check are defined via configuration files.
    The standard configuration has FK definitions for the eZPublish/eZPlatform schema (versions 4.x => 5.x)

2. Data integrity

    Similar to FK checks, these are custom sql queries which can be executed to find out any type of data inconsistency.
    The queries to run are defined via configuration files.
    The standard configuration has many data integrity queries for the eZPublish/eZPlatform schema (versions 4.x => 5.x)

3. Content Objects integrity

    This checks eZPublish Content Objects, validating every attribute based on its datatype definition.
    F.e. it checks if attributes are null which should not be, or if image files are missing.
    NB: not all datatypes are supported for now, just a limited set.

4. Orphan storage files

    This checks all files found on disk in the known eZPublish storage directories,
    and lists any which are not found in the database, corresponding to the ezmedia, ezimage and ezbinaryfile
    attributes.

    *NOTE* be warned that:
    - image variations generated via the eZ5 stack are not stored in the ezimage table in the db. As such, they
        will be reported as orphans. If you delete them, eZ5 will regenerate them, so it's not a huge deal, but
        that might include many gigs of files which are actually in use

    The script can optionally delete the files as well. We assume no responsibility if you use this feature!


How to use it:
--------------

- run `php extension/ezdbintegrity/bin/php/checkschema.php --help`,
    `php extension/ezdbintegrity/bin/php/checkattributes.php --help` and
    `php extension/ezdbintegrity/bin/php/checkstorage.php --help` to get started

- you can define more FKs and attribute types to be checked, in ezdbintegrity.ini.append.php


DISCLAIMER
----------

!!! DO NOT BLINDLY DELETE ANY DATA IN THE DB WHICH IS REPORTED AS FOREIGN KEY VIOLATION !!!

!!! DO NOT BLINDLY DELETE ANY STORAGE FILE WHICH IS REPORTED AS ORPHAN !!!

We take no responsibility for consequences if you do.
You should carefully investigate the reason for such violations.
There is a good chance that the problem lies within this extension and not your data - the FK definitions provided
have been reverse-engineered from existing codebase and databases, and are not cast in stone.

## Running tests:

The bundle uses PHPUnit to run functional tests.

*NB* the tests do *not* mock interaction with the database, but create/modify/delete many types of data in it.
As such, there are good chances that running tests will leave stale/broken data.
It is recommended to run the tests suite using a dedicated eZPublish installation or at least a dedicated database.

#### Setting up a dedicated test environment for the bundle

A safe choice to run the tests of the extension is to set up a dedicated environment, similar to the one used when the
test suite is run on GitHub Actions.
The advantages are multiple: on one hand you can start with any version of eZPublish you want; on the other you will
be more confident that any tests you add or modify will also pass on GitHub.
The disadvantages are that you will need Docker and Docker-compose, and that the environment you will use will look
quite unlike a standard eZPublish setup! Also, it will take a considerable amount of disk space and time to build.

Steps to set up a dedicated test environment and run the tests in it:

    git clone --depth 1 https://github.com/tanoconsulting/euts.git teststack
    # if you have a github auth token, it is a good idea to copy it now to teststack/docker/data/.composer/auth.json

    # this config sets up a test environment with eZPlatform 2.5 running on php 7.4 / ubuntu jammy
    export TESTSTACK_CONFIG_FILE=Tests/environment/.euts.2.5.env

    ./teststack/teststack build
    ./teststack/teststack runtests
    ./teststack/teststack stop

Note: this will take some time the 1st time your run it, but it will be quicker on subsequent runs.
Note: make sure to have enough disk space available.

In case you want to run manually commands, such as the symfony console:

    ./teststack/teststack console cache:clear

Or easily get to a database shell prompt:

    ./teststack/teststack dbconsole

Or command-line shell prompt to the Docker container where tests are run:

    ./teststack/teststack shell

The tests in the Docker container run using the version of debian/php/mysql/eZPlatform kernel specified in the file
`Tests/environment/.euts.2.5.env`, as specified in env var `TESTSTACK_CONFIG_FILE`.
If no value is set for that environment variable, a file named `.euts.env` is looked for.
If no such file is present, some defaults are used, you can check the documentation in ./teststack/README.md to find out
what they are.
If you want to test against a different version of eZ/php/debian, feel free to:
- create the `.euts.env` file, if it does not exist
- add to it any required var (see file `teststack/.euts.env.example` as guidance)
- rebuild the test stack
- run tests the usual way

You can even keep multiple test stacks available in parallel, by using different env files, eg:
- create a file `.euts.env.local` and add to it any required env var, starting with a unique `COMPOSE_PROJECT_NAME`
- build the new test stack via `./teststack/teststack. -e .euts.env.local build`
- run the tests via: `./teststack/teststack -e .euts.env.local runtests`

[![License](https://poser.pugx.org/gggeek/ezdbintegrity/license)](https://packagist.org/packages/gggeek/ezdbintegrity)
[![Latest Stable Version](https://poser.pugx.org/gggeek/ezdbintegrity/v/stable)](https://packagist.org/packages/gggeek/ezdbintegrity)
[![Total Downloads](https://poser.pugx.org/gggeek/ezdbintegrity/downloads)](https://packagist.org/packages/gggeek/ezdbintegrity)

[![Build Status](https://github.com/gggeek/ezdbintegrity/actions/workflows/ci.yml/badge.svg)](https://github.com/gggeek/ezdbintegrity/actions/workflows/ci.yml)
[![Code Coverage](https://codecov.io/gh/gggeek/ezdbintegrity/branch/main/graph/badge.svg)](https://codecov.io/gh/gggeek/ezdbintegrity/tree/main)
