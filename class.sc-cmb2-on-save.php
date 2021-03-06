<?php
/**
 * @package   SC-CMB2-On-Save
 * @version   1.2.0
 * @link      https://github.com/SpoddyCoder/sc-cmb2-on-save
 * @author    Paul Fernihough (spoddycoder.com)
 * @copyright Copyright (c) 2017, Paul Fernihough
 * @license   MIT
 *
 *
 * For description & usage see README
 *
 */

/*
    MIT License

    Copyright (c) 2017 Paul Fernihough

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
*/


if ( ! class_exists( 'SC_CMB2_On_Save' ) ) {

    class SC_CMB2_On_Save
    {

        /**
         * the id given to the hidden field
         */
        const HIDDEN_FIELD_ID = 'sc_cmb2_on_save_count';

        /**
         * the priority on cmb2_admin_init hook
         * this runs late, after the metaboxes have been fully setup
         */
        const CMB2_LATE_HOOK_PRIORITY = 900;

        /**
         * the priority on wp option_updated hook
         * this runs at standard priority, just after updated
         */
        const WP_UPDATED_HOOK_PRIORITY = 10;

        /**
         * array of on saves
         *
         * NB: this is public to allow ReflectionClass updates,
         * in reality no one should need to access this externally
         */
        public static $on_saves;

        /**
         * PHP5.3 compatible singleton pattern
         */
        private static $instances = array();
        protected function __construct() {}
        protected function __clone() {}
        public function __wakeup() {
            throw new Exception( "Cannot unserialize singleton" );
        }


        ////////////////////////////////////////////////////////////
        // public methods
        ////////////////////////////////////////////////////////////

        /**
         * should be run before calling SC_CMB2_On_Save::add_callabck
         * leverages cmb2_admin_init so needs to be run at a point before that hook expires
         *
         * @return object SC_CMB2_On_Save singleton
         */
        public static function init() {
            $class = get_called_class(); // late-static-bound class name (PHP5.3+)
            if ( !isset( self::$instances[$class] ) ) {
                self::$instances[$class] = new static;
                add_action( 'updated_option', 'SC_CMB2_On_Save::on_metabox_save', self::WP_UPDATED_HOOK_PRIORITY, 3 );
                if( is_multisite() ) {
                    // for: 'admin_menu_hook' => 'network_admin_menu'
                    add_action( 'update_site_option', 'SC_CMB2_On_Save::on_metabox_save', self::WP_UPDATED_HOOK_PRIORITY, 3 );
                }
                add_action( 'cmb2_admin_init', 'SC_CMB2_On_Save::cmb2_admin_init_late', self::CMB2_LATE_HOOK_PRIORITY );
            }
            return self::$instances[$class];
        }

        /**
         * adds a new on save callback
         * throws an exception if the singleton has not yet been initialised
         *
         * @param object cmb2_metabox object
         * @param mixed function to call on metabox save (string/array)
         * @return null
         */
        public static function add_callback( $cmb2_metabox, $callback ) {
            if( ! self::init() ) {
                throw new Exception( "Cannot add_callback(), SC_CMB2_On_Save not yet initialised" );
            }
            $registered_on_saves = self::$on_saves;
            $registered_on_saves[] = array(
                'cmb2_metabox' => $cmb2_metabox,
                'callback' => $callback,
            );
            $class = new ReflectionClass( "SC_CMB2_On_Save" );
            $class->setStaticPropertyValue( 'on_saves', $registered_on_saves );
        }


        ////////////////////////////////////////////////////////////
        // "private" methods
        //
        // NB: public because WP/CMB2 hook framework requires access
        ////////////////////////////////////////////////////////////

        /**
         * bound to cmb2_admin_init, run late
         */
        public static function cmb2_admin_init_late() {
            if( ! empty(self::$on_saves) ) {
                foreach( self::$on_saves as $on_save ) {
                    $cmb2_metabox = $on_save['cmb2_metabox'];
                    // add hidden field used to hook into on save
                    $cmb2_metabox->add_field( array(
                        'id' => self::HIDDEN_FIELD_ID,
                        'name' => '',
                        'desc' => '',
                        'type' => 'text',
                        'default' => 0,
                        'save_field'  => true,
                        'attributes'  => array(
                            'readonly' => 'readonly',
                        ),
                        'before_row' => 'SC_CMB2_On_Save::hide_count_field', // hide the field row with a little css include
                        'sanitization_cb' => 'SC_CMB2_On_Save::on_hidden_field_save', // hook into save
                    ) );
                }
            }
        }

        /**
         * this filter increases the hidden field
         */
        public static function on_hidden_field_save( $value, $field_args, $field  ) {
            foreach( self::$on_saves as $on_save ) {
                $on_save_cmb2_metabox = $on_save['cmb2_metabox'];
                $on_save_callback = $on_save['callback'];
                if( $on_save_cmb2_metabox->object_id ===  $field->object_id ) {
                    $value = (int)$value;   // sanitize the counter value, ensure int
                    $value ++;  // this ensures cmb2 gives a 'settings updated' notice rather a 'nothing to update'
                    //call_user_func( $on_save_callback );    // could be used to run a callback just before save
                    return $value;  // return updated count to cmb2 save
                }
            }
        }

        /**
         * hide the hidden field row
         */
        public static function hide_count_field( $field_args, $field ) {
            $class = '.cmb2-id-' . str_replace( '_', '-', self::HIDDEN_FIELD_ID );  // convert underscores to dashes
            echo '<style>' . $class . ' { display: none; }</style>';
        }

        /**
         * this action hook is used to run the on save callback
         */
        public static function on_metabox_save( $option_name, $old_value, $value ) {
            if( ! self::$on_saves ) {
                return; // prevent warnings if not intiialised
            }
            foreach( self::$on_saves as $on_save ) {
                $on_save_cmb2_metabox = $on_save['cmb2_metabox'];
                $on_save_callback = $on_save['callback'];
                if( $on_save_cmb2_metabox->object_id ===  $option_name ) {
                    call_user_func( $on_save_callback );    // do what needs to be done after save
                }
            }
        }
    }

}

