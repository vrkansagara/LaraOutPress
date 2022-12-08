# LaraOutPress (Laravel Output Press)
  This is simply compress your final out of Larvel Application and serve to the browser.

## How to install ?

~~~bash
composer require vrkansagara/lara-out-press
~~~

## How to activate this compression middleware in your application ?

Add the ServiceProvider to the providers array in `config/app.php`

~~~php
Vrkansagara\LaraOutPress\ServiceProvider::class,
~~~

Copy the package configuration to your local config directory using the publish command:

~~~bash
php artisan vendor:publish --provider="Vrkansagara\LaraOutPress\ServiceProvider"
~~~

Enable on single environment `.env`

~~~bash
VRKANSAGARA_COMPRESS_ENVIRONMENT="${APP_ENV}" 
~~~

Enable on multiple environment `.env`

~~~bash
VRKANSAGARA_COMPRESS_ENVIRONMENT='prod,testing,dev,local' 
~~~

Enable this compressor  by placing bellow code in `.env` file.

~~~bash
VRKANSAGARA_COMPRESS_ENABLED=true
~~~

### Display usage on each page

~~~bash
VRKANSAGARA_COMPRESS_DEBUG= true
~~~

### TO Do List

- [x] Compress browser output.
- [x] Except route(s)

### Task

- [x] Add analytics before compress and after compress.
- [x] Migrate code to Laravel package format.

### Code assumption

This code is developed with the mind set of each request is filtered by this middleware.
So most of the code will not be flexible by nature except configuration.

Improvement and suggestion are always welcome.


#### LaraOutPress Screen

![LaraOutPress](Images/LaraOutPress.png?raw=true "LaraOutPress")

#### You can

I would like take issue and pull request regarding this project and
love to answer if anything on this. I would be more happy if you have on this.

## Made with :heart: in India
<img src="Images/India.svg" width="20" height="20">