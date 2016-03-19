# srchub

Source hub is an open source fork of Indefero. Indefero was an open source clone of Google Code.

You can view it live here: https://srchub.org

This exists because I believe in self hosting - not that I'm saying there is anything wrong with github/bitbucket/gitlab etc
I just feel like there are individuals who would rather not trust their source code data to a third party. This could be
due to code belonging to a private business or someone who just wants something that will work and stand the test of time.
Rather than being at the mercy of the third parties in the event that they remove or change features.

Also I am a fan of the Google Code hosting format. It was simple, had no frills, was fast, and worked really well.
Github's social integrations are nice but I've found many people are not actually...well social.

srchub service is completely free to use - and while I do highlight reasons not to use it; srchub is ran by an individual
rather than an entire company. So you will be able to talk directly to someone who will listen and can fix whatever
problem you are having.

Questions/comments/concerns can be directed towards adamsna[at]datanethost.net

## Installation

### Requirements

#### Required
- Linux based OS (srchub currently runs on Debian - but should run on any sane modern distro)
- PHP
- MySQL

#### Recommended
- Root access (to install packages)

(Note - it has been reported that people have setup indefero on shared hosting - I do not recommend this)

## Install script

I have attempted to make a debian install script for srchub. It is not complete and will probably not work but should get you an
idea of what some of the pitfalls are.

https://srchub.org/p/srchub-setup/source/tree/tip/srchub/DEBIAN/postinst

### Migrating from Indefero to srchub

#### Before you do ANYTHING

Step 1: Backup

Step 2: Verify Backup

#### Installing srchub

##### Migrations

If you are currently running indefero but want to run srchub you will need to do a few things.

First off you need to run migrations to upgrade your database schema:

<pre class="prettyprint">
php /home/www/pluf/src/migrate.php --conf=IDF/conf/idf.php -a -d
</pre>

Currently this adds a project request table - but more tables will be added in the future.

##### Passwords

**This is extremely critical**

srchub stores passwords using SHA1, which is different than indefero (which used salted passwords). If you are switching you will need to reset the password of all your users. If you don't do this, and install the new system then your users will NOT be able to login.

### Install instructions for Indefero

These are the install instructions from indefero - they also apply to srchub with some slight changes

### Quick installation instruction

The installation of InDefero is composed of 2 parts, first the
installation of the [Pluf framework](http://www.pluf.org) and second,
the installation of InDefero by itself.

#### Recommended Layout of the Files

If your server document root is in `/var/www` a good thing is to keep
the number of files under the `/var/www` folder to its minimum. So,
you should create a `/home/www` folder in which we are going to
install all but the files which need to be available under the
document root.

    /home/www/pluf/src/
    /home/www/pluf/src/Pluf.php
    /home/www/pluf/src/migrate.php
    /home/www/indefero/src
    /home/www/indefero/www
    /home/www/indefero/www/index.php
    /home/www/indefero/www/media

The you need to link the `media` and `index.php` files into your
docroot.

    $ cd /var/www
    $ ln -s /home/www/indefero/www/index.php
    $ ln -s /home/www/indefero/www/media

#### Installation of Pluf

* Checkout the trunk of [Pluf](http://www.pluf.org).
* Install the `Mail` and `Mail_mime` classes from [PEAR](http://pear.php.net). You must use the `--alldeps` flag when installing these modules.

**Pear install/upgrade:**

    $ sudo pear upgrade-all
    $ sudo pear install --alldeps Mail
    $ sudo pear install --alldeps Mail_mime

If you already have some of the PEAR packages installed with your
distribution, the `Mail` package is often not up-to-date,
[read more here](http://projects.ceondo.com/p/indefero/issues/104/#ic347).

The Pluf installation folder is the folder containing the file `Pluf.php`.

## Installation of InDefero

The installation is composed of the following steps:

* Get the InDefero archive.
* Configure it correctly.
* Installation the database with the `migrate.php` script.
* Bootstrap the application with a `bootstrap.php` script.

Here is the step-by-step installation procedure:

* Extract the InDefero archive somewhere.
* The InDefero installation folder is the folder containing this file INSTALL.mdtext.
* Make a copy of `src/IDF/conf/idf.php-dist` as `src/IDF/conf/idf.php`.
* Update the idf.php file to match your system.
* Make a copy of `src/IDF/conf/path.php-dist` as `src/IDF/conf/path.php`.
* Update the path.php file to match your installation paths. It should work out of the box if you followed the recommended file layout.
* Open a terminal/shell and go into the `src` folder in the InDefero installation folder.

**Command:**

    $ cd /home/www/indefero/src

* Run `php /home/www/pluf/src/migrate.php --conf=IDF/conf/idf.php -a -i -d -u` to test the installation of the tables.
* Run `php /home/www/pluf/src/migrate.php --conf=IDF/conf/idf.php -a -i -d` to really install the tables.
* More details about the migration is available in the [migration documentation](http://pluf.org/doc/migrations.html) of the Pluf framework.
* Create a bootstrap file to create the admin user for example `www/bootstrap.php`. Do not forget to update the second line with your path to Pluf.

**Bootstrap script:**

    <?php
    require '/home/www/indefero/src/IDF/conf/path.php';
    require 'Pluf.php';
    Pluf::start('/home/www/indefero/src/IDF/conf/idf.php');
    Pluf_Dispatcher::loadControllers(Pluf::f('idf_views'));

    $user = new Pluf_User();
    $user->first_name = 'John';
    $user->last_name = 'Doe'; // Required!
    $user->login = 'doe'; // must be lowercase!
    $user->email = 'doe@example.com';
    $user->password = 'yourpassword'; // the password is salted/hashed
                                      // in the database, so do not worry :)
    $user->administrator = true;
    $user->active = true;
    $user->create();
    print "Bootstrap ok\n";
    ?>

* Run `php www/bootstrap.php`.
* Remove the `www/bootstrap.php` file.
* Open the `www/index.php` file and ensure that the path to Pluf and
  Indefero are correctly set for your configuration.
* Now you can login with this user into the interface.
* Click on the Forge Management link on top and create your first project.

#### Upgrade InDefero

To upgrade:

* Make a backup of your data, including the database.
* Extract the new archive on top of the current one.
* Update your version of Pluf.
* Check that the path in the `index.php` are still good.
* Remove all the `*.phps` files in your temp folder.
* Upgrade the database with the upgrade commands:

**Upgrade commands:**

    $ cd /home/www/indefero/src
    $ php /home/www/pluf/src/migrate.php --conf=IDF/conf/idf.php -a -d -u
    $ php /home/www/pluf/src/migrate.php --conf=IDF/conf/idf.php -a -d


#### Repository Synchronization

The documentation is available in the `doc` folder.

* Subversion: `doc/syncsvn.mdtext`.
* Mercurial: `doc/syncmercurial.mdtext`.
* Git: `doc/syncgit.mdtext`.

## For the Apache Webserver Users

If you are using [Apache](http://httpd.apache.org/) for your webserver
and want to have nice URLs like `http://yourdomain.com/p/yourproject/`
and not `http://yourdomain.com/index.php/p/yourproject/` you can use
the following `.htaccess` file to be put in the same folder of the
`www/index.php` file.

    Options +FollowSymLinks
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*) /index.php/$1

`Options +FollowSymLinks` is only needed if you are using symlinks.

## For the Gentoo users

If you get the error:

    T_CHARACTER Use of undefined constant T_CHARACTER - assumed 'T_CHARACTER'"

you need to compile PHP with the "tokenizer" flag.

#### For People with open_basedir restriction error

If you get an error like:

    file_get_contents(): open_basedir restriction in effect.
    File(/etc/mime.types) is not within the
    allowed path(s): (/srv/http/:/home/:/tmp/:/usr/share/pear/)

Just copy the file `/etc/mime.types` into the folder `/home` and put
this in your configuration file:

    $cfg['idf_mimetypes_db'] = '/home/mime.types';

#### FreeBSD Installation

You need to install `/usr/ports/lang/php5-extensions` which contains
the Standard PHP Library (SPL).

## Using a SMTP server with authentication

If your SMTP server requires authentication, for example,
*smtp.gmail.com*, you can use the following email configuration:

    $cfg['send_emails'] = true;
    $cfg['mail_backend'] = 'smtp';
    $cfg['mail_auth'] = true;
    $cfg['mail_host'] = 'ssl://smtp.gmail.com';
    $cfg['mail_port'] = 465;
    $cfg['mail_username'] = 'YOURGMAILADDRESS';
    $cfg['mail_password'] = 'YOURPASSWORD';

Check with your provider to get the right settings.

#### Git Daemon on Ubuntu Karmic

If you have problems getting it to run, you can follow this procedure
proposed by Mathias in ticket 369.

1. Install git-daemon-run in addition to git-core
2. Edit /etc/sv/git-daemon/run to look as follows:

    #!/bin/sh
    exec 2>&1
    echo 'git-daemon starting.'
    exec chpst -ugit:git \
      /usr/lib/git-core/git-daemon \
      --reuseaddr \
      --syslog \
      --verbose \
      --base-path=/home/git/repositories \
      /home/git/repositories

3. Restart git-daemon-run

    sv restart git-daemon

#### If Subversion is not working

If you access a Subversion server with a self-signed certificate, you
may have problems as your certificate is not trusted, check the
[procedure provided here][svnfix] to solve the problem.

[svnfix]: http://projects.ceondo.com/p/indefero/issues/319/#ic1358

#### If the registration links are not working

If You have standard instalaction of PHP ie in Debian, php.ini sets
mbstring.func_overload to value "2" for overloading str*
functions. You need to prevent the overload as it does not make sense
anyway (magic in the background is bad!).
See the [corresponding ticket][reglink].

[reglink]: http://projects.ceondo.com/p/indefero/issues/481/

# Attributions

srchub is licensed under the GPL and leverages the following projects

- [Pluf Framework](https://srchub.org/p/pluf/) - GPL
- [Indefero](https://srchub.org/p/indefero/) - GPL
- [syntaxhighlighter](http://alexgorbatchev.com/SyntaxHighlighter/) - MIT or GPLv3
- [tabbing in textarea](http://stackoverflow.com/questions/6637341/use-tab-to-indent-in-textarea/25655639)
- [jQuery](https://jquery.com/) - MIT
- [jQuery Autocomplete](https://github.com/dyve/jquery-autocomplete) - MIT or GPL or Apache
- [bgiframe](https://github.com/brandonaaron/bgiframe) - MIT
- [jQuery hotkeys](https://github.com/tzuryby/jquery.hotkeys) - MIT or GPLv2 (or later)
- [code-prettify](https://github.com/google/code-prettify) - Apache
- [ccurl](http://php.net/manual/en/book.curl.php#90821)
- [Base32 class](http://php.net/manual/en/function.base-convert.php#102232)
- [PHP OTP](https://github.com/lelag/otphp) - MIT
- [jQuery QRCode](https://github.com/jeromeetienne/jquery-qrcode) - MIT