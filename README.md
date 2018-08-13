# SpoddyCoder CMB2 On Save

Adds simple "on save" callback functionality to CMB2 metaboxes


## Usage

Somewhere in your init cycle include the class and init the `SC_CMB2_Pn_Save` singleton object...

```
require_once( 'class.sc-cmb2-on-save.php' );
SC_CMB2_On_Save::init();
```

After you have created your cmb2 metabox, use the `SC_CMB2_On_Save::add_callback` function to register a new on save callback against it.

Eg: inside `functions.php`...
```
SC_CMB2_On_Save::add_callback( $my_cmb2_metabox, 'my_on_save_callback' );
```

Or from inside your plugin class file...
```
SC_CMB2_On_Save::add_callback( $my_cmb2_metabox, array( 'MyClass' 'my_on_save_callback' ) );
```

That's it. 
You can register as many callbacks across as many metaboxes as you need.
The callback isn't passed anything at this point (TODO), but you could query the `$_REQUEST` object inside your callback function to get info about what's being saved.


## Additionl Info
This is a very simple static singleton class with very little overhead and an extremely small footprint. It works by;
+ Registering a hidden field to the metabox and then hooks into the fields sanitization_cb.
+ The sanitization_cb function increments the hidden field, which ensures cmb2 gives a 'settings updated' notice rather than a 'nothing updated' notice.
+ It then runs the callback.
+ The hidden field row is hidden from the metabox display.
+ The cost is you will see a new field 'sc_cmb2_on_save_count' in your metabox settings in the database.
