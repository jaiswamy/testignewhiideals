<?php

/**

* The base configuration for WordPress

*

* The wp-config.php creation script uses this file during the installation.

* You don't have to use the web site, you can copy this file to "wp-config.php"

* and fill in the values.

*

* This file contains the following configurations:

*

* * Database settings

* * Secret keys

* * Database table prefix

* * ABSPATH

*

* @link https://wordpress.org/support/article/editing-wp-config-php/

*

* @package WordPress

*/

// ** Database settings - You can get this info from your web host ** //

/** The name of the database for WordPress */

define('DB_NAME', 'u499940289_hitpLmanager');

/** MySQL database username */

define('DB_USER', 'u499940289_hiidealsHitpl');

/** MySQL database password */

define('DB_PASSWORD', 'hi!de@sl@User#5466');

/** Database hostname */

define( 'DB_HOST', 'db' );

/** Database charset to use in creating database tables. */

define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */

define( 'DB_COLLATE', '' );

/**#@+

* Authentication unique keys and salts.

*

* Change these to different unique phrases! You can generate these using

* the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.

*

* You can change these at any point in time to invalidate all existing cookies.

* This will force all users to have to log in again.

*

* @since 2.6.0

*/

define( 'AUTH_KEY',         '0]k@<n|HaD%axg3WXPXBh8?j1xi-mm}}i_PoU{99k)?~v?yP7:^CrkHPJYd}RM>U' );

define( 'SECURE_AUTH_KEY',  'bc,deT^*enC^rOAd$D!5l!E>$ebGbE0n/jb`t?|O02yAf_)odyNrwu@@d:;,wdY(' );

define( 'LOGGED_IN_KEY',    '1&wME4Z ,;tlLnFW XH<aOta9;|PCd}0y#*)aB5LdGA.>!DukGl_tN6E@VWd;63{' );

define( 'NONCE_KEY',        'h*&1&Rk{`JgH12pomNZceTyflAsXLH|B!2cOxCp`n[@@4,KL,AB;g&Xh|KgBN<hL' );

define( 'AUTH_SALT',        '+e,[Jy*Qky8=MY3`E?zdoPY^zH!||3F[]?qAx>6&C|UxW,3z_cA%D00CYN0)q2[N' );

define( 'SECURE_AUTH_SALT', '>RdvHcWo@;!<eIznQmGQWQ]#k&:prQk%|^0k6SR G]aQG/cz.MTtN.Uh^{?D:|N>' );

define( 'LOGGED_IN_SALT',   'C**10_E$p&Zsv$sm=:P, GcGviL=Y?f!PG+QT-oJ/$w>x_I7G>dK*>6VZ6AoDR:z' );

define( 'NONCE_SALT',       '0{G?9$P`bpvb_BL>@]yja:HGn+A5}*9Q2l<O;d%#J`RGwQ9(q~/QLL[i:GiXM[6R' );

/**#@-*/

/**

* WordPress database table prefix.

*

* You can have multiple installations in one database if you give each

* a unique prefix. Only numbers, letters, and underscores please!

*/

$table_prefix = 'Hitpl_';

/**

* For developers: WordPress debugging mode.

*

* Change this to true to enable the display of notices during development.

* It is strongly recommended that plugin and theme developers use WP_DEBUG

* in their development environments.

*

* For information on other constants that can be used for debugging,

* visit the documentation.

*

* @link https://wordpress.org/support/article/debugging-in-wordpress/

*/



define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

define( 'FS_METHOD', 'direct' );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */

if ( ! defined( 'ABSPATH' ) ) {

define( 'ABSPATH', __DIR__ . '/' );

}

/** Sets up WordPress vars and included files. */

require_once ABSPATH . 'wp-settings.php';

