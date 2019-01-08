# LaraOutPress (Laravel Output Press)
This is simply compress your final out of Larvel Application and serve to the browser.

### How to install

~~~bash
	composer require vrkansagara/lara-out-press
~~~

### How to activate this compression middleware in your application

Add the ServiceProvider to the providers array in `config/app.php`

```php
 Vrkansagara\LaraOutPress\ServiceProvider::class,
```

Copy the package config to your local config with the publish command:

```shell
php artisan vendor:publish --provider="Vrkansagara\LaraOutPress\ServiceProvider"
```

Enable on single environment `.env`

~~~bash
      VRKANSAGARA_COMPRESS_ENVIRONMENT='${APP_ENV}' 
~~~

Enable on multiple environment `.env`

~~~bash
      VRKANSAGARA_COMPRESS_ENVIRONMENT='prod,testing,dev,local' 
~~~


Enable this compressor  by placing bellow code in `.env` file.

~~~bash
    VRKANSAGARA_COMPRESS_ENABLED = true
~~~

### Display usage on each page.

Set ` $debug = 1; ` in ` AfterMiddleware.php `

### TO Do List

- [x] Compress browser output.
- [] Combine all CSS
- [] Combine all JavaScript files.
- [] Compress using varis algorithms.
- [] Versioning the compressed file.
- [] Except route(s),middleware,group,prefix

### Task

- [x] Add analytics before compress and after compress.
- [x] Migrate code to laravel package format. 

### Code Assumption
This code is developed with the mind set of each request is filtered by this middleware. So most of the code will not be flexi.

Improvement and suggestion are always welcome.