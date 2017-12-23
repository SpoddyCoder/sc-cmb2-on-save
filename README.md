# SpoddyCoder CMB2 On Save

Adds "on save" callback functionality to CMB2 metaboxes


## Usage

Somewhere in your init cycle include the class and init the singleton object...

```
require_once( MY_PATH . 'lib/sc-cmb2-on-save/class.sc-cmb2-on-save.php' );
SC_CMB2_On_Save::init();
```

After you have created your metabox, use SC_CMB2_On_Save::add_callback to register a new on save callback...

```
SC_CMB2_On_Save::add_callback( $my_cmb2_metabox, 'my_on_save_callback' );
SC_CMB2_On_Save::add_callback( $my_other_cmb2_metabox, array( 'MyClass' 'other_on_save_callback' ) );
```

That's it. You can register as many callbacks across as many metaboxes as you need.
The callback isn't passed anything at this point (TODO).


## Additionl Info
This is a very simple static singleton class with very little overhead and footprint. It works by;
+ Registering a hidden field to the metabox and then hooks into the fields sanitization_cb.
+ The sanitization_cb function increments the hidden field, which ensures cmb2 gives a 'settings updated' notice rather than a 'nothing updated' notice.
+ It then runs the callback.
+ The hidden field row is hidden from the metabox display.
+ The cost is you will see a new field 'sc_cmb2_on_save_count' in your metabox settings in the database.
