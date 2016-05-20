eZ DB Integrity Extension for eZ publish
========================================

Goals:
------

Allow checking integrity of data in a database (the eZ Publish one, but any other as well).
Allow checking integrity of the eZPublish storage files (images and binary files from content).


Current checks supported:
-------------------------

1. Foreign Key integrity

    This applies to databases which have no FK enabled, but where parent-child relationships do in fact exist.
    The relationships to check are defined via configuration files.
    The standard configuration has FK definitions for the eZPublish schema (versions 4.x => 5.2)

2. Content Objects integrity

    This checks eZPublish Content Objects, validating every attribute based on its datatype definition.
    F.e. it checks if attributes are null which should not be, or if image files are missing.
    NB: not all datatypes are supported for now, just a limited set

3. Orphan storage files

    This checks all files found on disk in the known eZPublish storage directories,
    and lists any which are not found in the database, corresponding to the ezmedia, ezimage and ezbinaryfile
    attributes.

    *NOTE* be warned that:
    - image variations generated via the eZ5 stack are not stored in the ezimage table in the db. As such, they
        will be reported as orphans. If you delete them, eZ5 will regenerate them, so it's not a huge deal, but
        that might include many gigs of files which are actually in use

    The script can optionally delete the files as well. We assume no responsibility if you use this feature!

    It does *NOT* support cluster mode yet

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
