# LaraOutPress (Laravel Output Press)
This is simply compress your final out of Larvel Application and serve to the browser.

### How to activate this compression middleware in your application

Update your `app/Http/Kernel.php` file with below line
~~php
protected $middleware = [
		...
        \Vrkansagara\Http\Middleware\AfterMiddleware::class,
    	...
    ];
~~

### TO Do List
- [x] Compress browser output.
	-[] Excempt tag list
	-[] Excempt by class list
	-[]	Excempt by id list
- [] Combile all CSS
- [] Combile all JavaScript files.
- [] Compress using varis algorithms.
- [] Versioning the compress file.

