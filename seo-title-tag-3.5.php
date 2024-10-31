<?php

if ( ! class_exists( 'SeoTitleTag' ) ) {

  require_once('WPSEOTitleTag.php');

  class SeoTitleTag
  {

    #-------------------------------------------------------------------------
    # change as required:

    private $default_title_tag_key        = "title_tag";
    private $default_meta_description_key = "meta_description";

    private $download_as_file = 1;
    # 1 = download as file
    # 0 = show in browser, so user can copy/paste as/if needed

    #-------------------------------------------------------------------------
    # do not change settings below:

    private $current_title_tag_key        = "";
    private $current_meta_description_key = "";

    private $yoast_enabled = false;

    #-------------------------------------------------------------------------

    public function __construct()
    {
        global $wpdb;

        #error_log( $_SERVER['REQUEST_URI'] );

        # do not run if called using 'admin-ajax.php'
        if ( '/wp-admin/admin-ajax.php' == $_SERVER['REQUEST_URI'] ) {
          return;
        }

        #error_log("initializing...");
        #error_log( $_SERVER['REQUEST_URI'] );

        $this->current_title_tag_key        = $this->default_title_tag_key;
        $this->current_meta_description_key = $this->default_meta_description_key;

        if (!defined('COMMENT_TEXT')) {
            define('COMMENT_TEXT', '//commented by seo plugin:');
        }

        $seoTitleTag = new WPSEOTitleTag();
        $WPTitleReference = $seoTitleTag->getTitleTagReference();
        $WPMetaReference = $seoTitleTag->getMetaTagReference();

        // this is called on plugin installation
        add_action('activate_seo-title-tag/seo-title-tag.php', array( $this, 'seo_title_tag_install' ) );

        // detect is Yoast SEO enabled
        add_action('plugins_loaded', array( $this, 'detect_yoast_seo' ) );
        // download file, once all plugins are initialized and ready
        // we need it here, since we need to detect Yoast SEO, before running
        add_action('plugins_loaded', array( $this, 'download' ) );

        // add menu options in tools and in settings
        add_action('admin_menu', array( $this, 'seo_title_tag_menus' ) );

        // this is called on plugin activation.
        ### add_action('wp_head', $WPMetaReference);
        #add_action('wp_title', $WPTitleReference);
        ### add_filter('pre_get_document_title', $WPTitleReference); # instead of previous line 'wp_title'

        // load CSS
        add_action('admin_enqueue_scripts', array( $this, 'admin_custom_css' ) );

        ### add_filter('the_title_rss', array( $this, 'update_title_rss' ) );

        ### add_filter('single_tag_title', array( $this, 'seo_title_tag_filter_single_tag_title' ), 1, 2);

        // reference: https://wp-kama.com/123/hooks-on-the-edit-post-admin-page
        //
        ### add_action('edit_page_form',     array( $this, 'seo_edit_page_form' ) );
        ### add_action('edit_form_advanced', array( $this, 'seo_edit_page_form' ) );
        ### add_action('simple_edit_form',   array( $this, 'seo_edit_page_form' ) );
        ### add_action('dbx_post_sidebar',   array( $this, 'seo_edit_page_form' ) ); // new test jjj

        ### add_action('edit_post',    array( $this, 'seo_update_title_tag' ) );
        ### add_action('save_post',    array( $this, 'seo_update_title_tag' ) );
        ### add_action('publish_post', array( $this, 'seo_update_title_tag' ) );

    }

    #-------------------------------------------------------------------------

    public function detect_yoast_seo() {

        if ( ! function_exists( 'is_plugin_active' ) ) require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

        if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
             /* Let's do cool things */
          #error_log( "Yoast SEO installed and active!" );
          $this->yoast_enabled = true;

          $this->current_title_tag_key        = "_yoast_wpseo_title";
          $this->current_meta_description_key = "_yoast_wpseo_metadesc";

        }
    }

    #-------------------------------------------------------------------------

    // this will create the DB table if needed.
    public function seo_title_tag_install()
    {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = '';

        if (version_compare($wpdb->db_version(), '4.1.0', '>=')) {
            if (!empty($wpdb->charset)) {
                $charset_collate .= "DEFAULT CHARACTER SET $wpdb->charset";
            }

            if (!empty($wpdb->collate)) {
                $charset_collate .= " COLLATE $wpdb->collate";
            }
        }

        foreach ($wpdb->get_col("SHOW TABLES", 0) as $table) {
            $tables[$table] = $table;
        }

        $table_name = $wpdb->prefix . "seo_title_tag_url";
        // the URL table
        $sql = "CREATE TABLE $table_name (
          id bigint NOT NULL AUTO_INCREMENT,
          url varchar(255) NOT NULL,
          title varchar(255) NOT NULL,
          description varchar(255) NOT NULL,
          UNIQUE KEY id (id),
          PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql);

        // the category table
        $table_name = $wpdb->prefix . "seo_title_tag_category";
        $sql = "CREATE TABLE $table_name (
                  id bigint NOT NULL AUTO_INCREMENT,
                  category_id varchar(255) NOT NULL,
                  title varchar(255) NOT NULL,
                  description varchar(255) NOT NULL,
                  UNIQUE KEY id (id),
                  PRIMARY KEY  (id)
                ) $charset_collate;";

        dbDelta($sql);

        // the tag table
        $table_name = $wpdb->prefix . "seo_title_tag_tag";
        $sql = "CREATE TABLE $table_name (
                  id bigint NOT NULL AUTO_INCREMENT,
                  tag_id varchar(255) NOT NULL,
                  title varchar(255) NOT NULL,
                  description varchar (255) NOT NULL,
                  UNIQUE KEY id (id),
                  PRIMARY KEY  (id)
                ) $charset_collate;";

        dbDelta($sql);

        if (!get_option("custom_title_key")) {
            update_option('custom_title_key', $this->default_title_tag_key);
        }

        if (!get_option("custom_meta_description_key")) {
            update_option('custom_meta_description_key', $this->default_meta_description_key);
        }

        if (!get_option("use_category_description_as_title")) {
            update_option('use_category_description_as_title', false);
        }

        if (!get_option("include_title_form")) {
            update_option('include_title_form', true);
        }

        if (!get_option("include_meta_description_form")) {
            update_option('include_meta_description_form', true);
        }

        if (!get_option("include_slug_form")) {
            update_option('include_slug_form', true);
        }

        if (!get_option("uploaded_file")) {
            update_option('uploaded_file', true);
        }

        if (!get_option('include_blog_name_in_titles')) {
            update_option('include_blog_name_in_titles', false);
        }

        if (!get_option('manage_elements_per_page')) {
            update_option("manage_elements_per_page", 20);
        }
    }

    #-------------------------------------------------------------------------

    // OK:
    public function seo_title_tag_menus()
    {
        if (function_exists('add_options_page')) {
            add_options_page('SEO Title Tag', 'SEO Title Tag', 'manage_options', 'seo-title-tag', array( $this, 'seo_title_tag_menu_tools' ) );
            add_management_page('SEO Title Tag', 'SEO Title Tag', 'manage_options', 'seo-title-tag', array( $this, 'seo_title_tag_menu_settings' ) );
        }
    }

    #-------------------------------------------------------------------------

    public function seo_title_tag_menu_tools()
    {
        global $wp_version;

        // Function for csv file upload
        if (isset($_POST['upload'])) {
            $this->upload();
        }

        if (isset($_POST['info_update'])) {
            if (function_exists('check_admin_referer')) {
                check_admin_referer('seo-title-tag-action_options');
            }

            if ($_POST['custom_title_key'] != "") {
                update_option('custom_title_key', stripslashes(strip_tags($_POST['custom_title_key'])));
            }

            update_option('custom_meta_description_key', stripslashes(strip_tags($_POST['custom_meta_description_key'])));
            update_option('custom_title_key', stripslashes(strip_tags($_POST['custom_title_key'])));
            update_option('home_page_title', stripslashes(strip_tags($_POST['home_page_title'])));
            update_option('home_page_meta_description', stripslashes(strip_tags($_POST['home_page_meta_description'])));
            update_option('error_page_title', stripslashes(strip_tags($_POST['error_page_title'])));
            update_option('error_page_meta_description', stripslashes(strip_tags($_POST['error_page_meta_description'])));
            update_option('separator', stripslashes(strip_tags($_POST['separator'])));
            update_option('use_category_description_as_title', stripslashes(strip_tags($_POST['use_category_description_as_title'])));
            update_option('include_blog_name_in_titles', stripslashes(strip_tags($_POST['include_blog_name_in_titles'])));
            /* for title form hide/Show */
            update_option('include_title_form', stripslashes(strip_tags($_POST['include_title_form'])));
            /* end for title form hide/show */
            /* for Meta Description form hide/show */
            update_option('include_meta_description_form', stripslashes(strip_tags($_POST['include_meta_description_form'])));
            /* end for Meta Description form hide/show */
            /* for slug form hide/show */
            update_option('include_slug_form', stripslashes(strip_tags($_POST['include_slug_form'])));
            /* end for slug form hide/show */

            /* for uploadedfile */
            update_option('uploaded_file', stripslashes(strip_tags($_POST['uploaded_file'])));
            /* end for uploadedfile */

            update_option('short_blog_name', stripslashes(strip_tags($_POST['short_blog_name'])));
            update_option("manage_elements_per_page", intval($_POST['manage_elements_per_page']));

            echo '<div class="updated"><p>Options saved.</p></div>';
        }

        if (get_option("custom_title_key") OR get_option("custom_meta_description_key")) {
            // the name of the custom title
            //and  Meta description
            $custom_title_key = get_option("custom_title_key");
            $custom_meta_description_key = get_option("custom_meta_description_key");
            $home_page_title = get_option("home_page_title");
            $home_page_meta_description = get_option("home_page_meta_description");
            $home_page_title = htmlspecialchars(stripslashes($home_page_title));
            $error_page_title = get_option("error_page_title");
            $error_page_meta_description = get_option("error_page_meta_description");
            $error_page_title = htmlspecialchars(stripslashes($error_page_title));
            $separator = get_option("separator");
            $separator = htmlspecialchars(stripslashes($separator));
            $use_category_description_as_title = get_option("use_category_description_as_title");
            $include_title_form = get_option("include_title_form");
            $include_meta_description_form = get_option("include_meta_description_form");
            $include_slug_form = get_option("include_slug_form");
            $uploaded_file = get_option("uploaded_file");

            // shall we always print out the blog name at the end of the title?
            $include_blog_name_in_titles = get_option("include_blog_name_in_titles");
            $short_blog_name = get_option("short_blog_name");
            $short_blog_name = htmlspecialchars(stripslashes($short_blog_name));

            // how many elements do we show per page in the manage page
            $manage_elements_per_page = get_option("manage_elements_per_page");
        } else {
            $custom_title_key = $this->default_title_tag_key;
            $use_category_description_as_title = false;
            $include_blog_name_in_titles = false;
            $manage_elements_per_page = 20;
        };

        ?>

        <div class="wrap">
            <h2>SEO Title Tag Options</h2>
            <div style="width:400px;float:right">
                <div align="right" style="float:left;width:100%;">
                    <div style="float: left;">

                        <a href="http://scienceofseo.com/"><img width="233" height="60" alt="visit The Science of SEO" align="right" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/seo-title-tag/nclogo.jpg" /></a>
                    </div>
        <?php $url = plugins_url(); ?>
                    <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery-1.6.2.min.js' ?>"></script>
                    <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.js' ?>"></script>
                    <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.validate.min.js' ?>"></script>
                    <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/testimonial.js' ?>"></script>



                    <script type="text/javascript" src=" <?php echo $url . '/seo-title-tag/jquery.form.js' ?>"></script>
                    <link rel="stylesheet" type="text/css" href=" <?php echo $url . '/seo-title-tag/style.css' ?>" />


                    <script type='text/javascript'>
                        $(function() {
                            $('#myselect').change(function() {

                                var x = $(this).val();
                                var value = x.split("~");
                                var name = value[0];
                                var paypalemail = value[1];

                                // and update the hidden input's value
                                var str = $('#item_name').val(name);
                                var str2 = $('#business').val(paypalemail);
                            });
                        });
                    </script>

                    <select id='myselect'>
                        <option value='impact network~donate@impactnetwork.org'>Impact Network</option>
                        <option value='PETA~PayPal@peta.org'>PETA</option>

                    </select>

                    <form action="https://www.paypal.com/cgi-bin"  method="post" >
                        <input type="hidden" name="cmd" value="_donations">
                        <input type="hidden" name="business" id="business" value="donate@impactnetwork.org">
                        <input type="hidden" name="lc" value="US">

                        <input type="hidden" name="item_name" id="item_name" value="impact network" >
                        <input type="hidden" name="item_number" value="1">
                        <input type="hidden" name="no_note" value="0">
                        <input type="hidden" name="currency_code" value="USD">
                        <input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                    </form>

                </div>
                <!-- Testimonial form -->
                <div style="float:left;width:100%;">

        <?php
        $emailUser = "me@stephanspencer.com";
        $message = '';

        if (isset($_POST['testimonial']) && $_POST['testimonial'] == "testimonial") {
            $name = null;
            $email = null;
            $comments = null;

            if (isset($_POST['contactName'])) {
                $name = trim($_POST['contactName']);
            }

            if (isset($_POST['email'])) {
                $email = trim($_POST['email']);
            }

            if (isset($_POST['commentsText'])) {
                $comments = trim($_POST['commentsText']);
            }

            $emailTo = $emailUser;

        if ( $name == null ) {
            $name = $emailUser;
        }

        if ( $email == null ) {
              $email = $emailUser;
        }

            //$subject = '[PHP Snippets] From '.$name;
            $subject = "Testmonial by User";
            $body = "Name: $name \n\n";
            $body .= "Email: $email \n\n";
            $body .= "Comments: $comments";
            $headers = 'From: ' . $name . ' <' . $emailTo . '>' . "\r\n" . 'Reply-To: ' . $email;
            $sent = wp_mail($emailTo, $subject, $body, $headers);

            if ($sent) {
                $message = "Your Email Has been sent successfully.";
            }
        }
        ?>
                    <div><?php echo $message; ?></div>
                    <fieldset class="testimonial">
                        <legend>Testimonial</legend>
                        <form action="" id="contactForm" name="contactForm" method="post">
                            <ul class="form_inputfield">
                                <li>
                                    <label for="contactName">Name*</label>
                                    <input type="text" name="contactName" id="contactName" value="" />
                                </li>
                                <li>
                                    <label for="email">Email*</label>
                                    <input type="text" name="email" id="email" value="" />
                                </li>
                                <li>
                                    <label for="commentsText">Message*</label>
                                    <textarea name="commentsText" id="commentsText" rows="10" cols="30"></textarea>
                                </li>
                                <li>
                                    <input type="submit" name="submit" value="submit testimonial" />
                                    <input type="hidden" name="testimonial" id="testimonial" value="testimonial">
                                </li>
                                <!-- <li> Thanks for useing our plugin.. <a href="http://wordpress.org/extend/plugins/seo-title-tag/" title="Seo Title Tag" >SEO TITLE TAG</a></li> -->
                                <li>Thanks for using our <a href="http://wordpress.org/extend/plugins/seo-title-tag/" title="Seo Title Tag" target="_blank">SEO Title Tag</a> plugin.</li>
                            </ul>

                        </form></fieldset>
                </div></div>
            <form name="stto_main" method="post">
                    <?php
                    if (function_exists('wp_nonce_field')) {
                        wp_nonce_field('seo-title-tag-action_options');
                    }
                    ?>
                <table class="form-table" style="display:inline-block;width:auto;">
                    <tr valign="top">
                        <th scope="row">Key name for custom Title Tag field</th>
                        <td><input name="custom_title_key" type="text" id="custom_title_key"  value="<?php echo $custom_title_key; ?>" size="40" /></td>
                    </tr>
                    <!-- meta description -->
                    <tr valign="top">
                        <th scope="row">Key name for custom Meta Description field</th>
                        <td><input name="custom_meta_description_key" type="text" id="custom_meta_description_key" value="<?php echo $custom_meta_description_key; ?>" size="40" /></td>
                    </tr>
                    <!-- end meta description -->

                    <tr valign="top">
                        <th scope="row">Number of posts per page in mass edit mode</th>
                        <td><input name="manage_elements_per_page" value="<?php echo $manage_elements_per_page; ?>" size="5" class="code" /></td>
                    </tr>

                    <tr valign="top">
        <?php if ('page' == get_option('show_on_front')) { ?>
                            <th scope="row"><a href="<?php echo get_permalink(get_option('page_for_posts')); ?>">Posts Page</a> title tag (leave blank to use blog name)</th>
        <?php } else { ?>
                            <th scope="row">Home page title tag (leave blank to use blog name)</th>
        <?php } ?>
                        <td><input name="home_page_title" value="<?php echo $home_page_title; ?>" size="60" class="code" /></td>
                    </tr>
                    <!--  meta description for homepage-->
                    <tr valign="top">
        <?php if ('page' == get_option('show_on_front')) { ?>
                            <th scope="row"><a href="<?php echo get_permalink(get_option('page_for_posts')); ?>">Posts Page</a> title tag (leave blank to use blog name)</th>
        <?php } else { ?>
                            <th scope="row">Home page Meta Description tag (leave blank to use Default  )</th>
        <?php } ?>
                        <td><input name="home_page_meta_description" value="<?php echo $home_page_meta_description; ?>" size="60" class="code" /></td>
                    </tr>

                    <!-- end meta description for homepage -->
                    <tr valign="top">
                        <th scope="row">404 Error title tag (leave blank to use blog name)</th>
                        <td><input name="error_page_title" value="<?php echo $error_page_title; ?>" size="60" class="code" /></td>
                    </tr>
                    <!--  meta description for 404 page -->
                    <tr valign="top">
                        <th scope="row">404 Error Meta Description tag (leave blank to use default Meta Description)</th>
                        <td><input name="error_page_meta_description" value="<?php echo $error_page_meta_description; ?>" size="60" class="code" /></td>
                    </tr>
                    <!-- end meta description 404 page -->

                    <tr valign="top">
                        <th scope="row">Use category descriptions as titles on category pages</th>
                        <td>
                            <label><input name="use_category_description_as_title" type="radio" value="1" <?php if ($use_category_description_as_title) {
            echo 'checked="checked"';
        } ?> /> Yes</label><br />
                            <label><input name="use_category_description_as_title"  type="radio" value="0"  <?php if (!$use_category_description_as_title) {
            echo 'checked="checked"';
        } ?> /> No</label>

                        </td>
                    </tr>
                    <!-- title hide/show on Post/page editing screen -->
                    <tr valign="top">
                        <th scope="row">Include Title Form on Posts/Pages editing screen</th>
                        <td>
                            <label><input name="include_title_form" type="radio" value="1" <?php if ($include_title_form) {
            echo 'checked="checked"';
        } ?> /> Yes</label><br />
                            <label><input name="include_title_form"  type="radio" value="0" <?php if (!$include_title_form) {
                        echo 'checked="checked"';
                    } ?> /> No</label>

                        </td>
                    </tr>
                    <!-- End title hide/show Post/page on editing screen -->

                    <!-- Meta Description hide/show on Post/page editing screen -->
                    <tr valign="top">
                        <th scope="row">Include Meta Description Form on Posts/Pages editing Screen</th>
                        <td>
                            <label><input name="include_meta_description_form" type="radio" value="1" <?php if ($include_meta_description_form) {
                        echo 'checked="checked"';
                    } ?> /> Yes</label><br />
                            <label><input name="include_meta_description_form"  type="radio" value="0" <?php if (!$include_meta_description_form) {
                        echo 'checked="checked"';
                    } ?> /> No</label>

                        </td>
                    </tr>
                    <!-- End Meta Description hide/show Post/page on editing screen -->

                    <!-- Slug hide/show on Post/page editing screen -->
                    <tr valign="top">
                        <th scope="row">Include Slug Form on Posts/Pages editing Screen</th>
                        <td>
                            <label><input name="include_slug_form" type="radio" value="1" <?php if ($include_slug_form) {
                        echo 'checked="checked"';
                    } ?> /> Yes</label><br />
                            <label><input name="include_slug_form"  type="radio" value="0" <?php if (!$include_slug_form) {
                        echo 'checked="checked"';
                    } ?> /> No</label>

                        </td>
                    </tr>
                    <!-- Slug hide/show Post/page on editing screen -->
                    <tr valign="top">
                        <th scope="row">Include blog name in titles</th>
                        <td>
                            <label><input name="include_blog_name_in_titles" type="radio" value="1" <?php if ($include_blog_name_in_titles) {
                        echo 'checked="checked"';
                    } ?> /> Yes</label><br />
                            <label><input name="include_blog_name_in_titles"  type="radio" value="0"  <?php if (!$include_blog_name_in_titles) {
                        echo 'checked="checked"';
                    } ?> /> No</label>
                        </td>
                    </tr>
                </table>

                <h3>Complete the following if "Yes" selected above:</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Separator (leave blank to use "|")</th>
                        <td><input name="separator" value="<?php echo $separator; ?>" size="10" class="code" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Short blog name (leave blank to use full blog name)</th>
                        <td><input name="short_blog_name" value="<?php echo $short_blog_name; ?>" size="60" class="code" /></td>
                    </tr>
                </table>

                <!-- </div>
                 <div id='preview'>-->
                <!-- end for csv file upload -->

                <p class="submit">
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="page_options" value="custom_title_key, home_page_title,separator,use_category_description_as_title,include_blog_name_in_titles,short_blog_name"/>
                    <input type="submit" name="info_update" class="button" value="<?php _e('Save Changes', 'Localization name') ?> &raquo;" />
                </p>

            </form>
            <!-- for csv file upload -->
            <h3>Upload Titles, Meta Descriptions, and Slugs directly through a CSV File:</h3>
            <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
                <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
                    <table border="0" cellpadding="0" cellspacing="0" style="padding-left:10px;" >
                        <tr>
                            <td scope="row" width="221">Download current CSV file for editing:</td>
                            <td>
                                <input type='submit' name='download' value='Download'>
                            </td>
                        </tr>

                        <tr>
                            <td scope="row">Select a CSV file to upload:</td>
                            <td>
                                <input size='30' type='file' name='filename'>
                                <input type='submit' name='upload' value='Upload'>
                            </td>
                        </tr>
                    </table>


                </form>
                <!-- end for csv file upload -->

        </div>
        <?php
    }

    #-------------------------------------------------------------------------

    public function seo_title_tag_menu_settings()
    {
        global $wpdb, $tabletags, $tablepost2tag, $install_directory, $wp_version;

        $search_value = '';
        $search_query_string = '';

        if (isset($_POST['action']) && (($_POST['action'] == 'pages') || ($_POST['action'] == 'posts'))) {

            // Save Pages Form

            // form save pages
            // form save posts

            if (function_exists('check_admin_referer')) {
                check_admin_referer('seo-title-tag-action_posts-form');
            }

            foreach ($_POST as $name => $value) {

                // Update Title Tag
                if (preg_match('/^tagtitle_(\d+)$/', $name, $matches)) {
                    $value = stripslashes(strip_tags($value));
                    //print_r( $value); die();
                    delete_post_meta($matches[1], $this->current_title_tag_key);
                    add_post_meta($matches[1], $this->current_title_tag_key, $value);
                }
                // Update Description
                if (preg_match('/^tagdescription_(\d+)$/', $name, $matches)) {
                    $value_meta = stripslashes(strip_tags($value));

                    delete_post_meta($matches[1], $this->current_meta_description_key);
                    add_post_meta($matches[1], $this->current_meta_description_key, $value_meta);
                }

                // Update Slug
                if (preg_match('/^post_name_(\d+)$/', $name, $matches)) {
                    $postarr = get_post($matches[1], ARRAY_A);
                    $old_post_name = $postarr['post_name'];
                    $postarr['post_name'] = sanitize_title($value, $old_post_name);
                    $postarr['post_category'] = array();
                    $cats = get_the_category($postarr['ID']);

                    if (is_array($cats)) {
                        foreach ($cats as $cat) {
                            $postarr['post_category'][] = $cat->term_id;
                        }
                    }

                    $tags_input = array();
                    $tags = get_the_tags($postarr['ID']);

                    if (is_array($tags)) {
                        foreach ($tags as $tag) {
                            $tags_input[] = $tag->name;
                        }
                    }

                    $postarr['tags_input'] = implode(', ', $tags_input);
                    wp_insert_post($postarr);
                }
            }

            echo '<div class="updated"><p>The custom ' . ('pages' == $_POST['action'] ? 'page' : 'post') . ' titles have been updated.</p></div>';

        } elseif (isset($_POST['action']) && (($_POST['action'] == 'categories') || ($_POST['action'] == 'tags'))) {

            // Save Category and Tag Forms

            // form save categories
            // form save tags

            if (function_exists('check_admin_referer')) {
                check_admin_referer('seo-title-tag-action_taxonomy-form');
            }

            $singular = ('tags' == $_POST['action'] ? 'tag' : 'category');
            $type = ('tags' == $_POST['action'] ? 'post_tag' : 'category');
            #error_log( print_r( $_POST, 1 ));

            foreach ($_POST as $name => $value) {

                if (preg_match('/^title_(\d+)$/', $name, $matches)) {

                    $id = $matches[1];

                    $seo_title = stripslashes(strip_tags($_POST['title_' . $id]));
                    #$seo_title = esc_sql($seo_title);

                    $seo_description = stripslashes(strip_tags($_POST['description_' . $id]));
                    #$seo_description = esc_sql($seo_description);

                    $slug = stripslashes(strip_tags($_POST['slug_' . $id]));
                    #$slug = esc_sql($slug);

                    if ( $this->yoast_enabled ) {

                        $yoastmeta   = get_option( 'wpseo_taxonomy_meta' );
                        # $id
                        # $title
                        # $seo_title
                        # $seo_description
                        $yoastmeta[$type][$id]['wpseo_title'] = $seo_title;
                        $yoastmeta[$type][$id]['wpseo_desc'] = $seo_description;
                        update_option( 'wpseo_taxonomy_meta', $yoastmeta );

                    }

                    // update the category slug
                    wp_update_term( $id, $type, array(
                        'slug' => $slug,
                    ));

                }
            }

            echo '<div class="updated"><p>The custom ' . $singular . ' titles have been saved.</p></div>';

        } elseif (isset($_POST['action']) and ($_POST['action'] == 'urls')) {

            // Save URLs Form

            // form save urls

            if (function_exists('check_admin_referer')) {
                check_admin_referer('seo-title-tag-action_urls-form');
            }

            $table_name = $wpdb->prefix . "seo_title_tag_url";

            foreach ($_POST as $name => $value) {
                // Update Title Tag
                if (preg_match('/^url_(\d+)$/', $name, $matches)) {
                    $url = stripslashes($value);
                    $url = esc_sql($url);

                    $title = stripslashes(strip_tags($_POST['title_' . $matches[1]]));
                    $title = esc_sql($title);

                    $meta_description_url = stripslashes(strip_tags($_POST['meta_description_' . $matches[1]]));
                    $meta_description_url = esc_sql($meta_description_url);
                    //for url description insert
                    if ((!empty($url)) and (!empty($title))) {
                        $wpdb->query('UPDATE ' . $table_name . ' SET url = \'' . $url . '\', title = \'' . $title . '\', description = \'' . $meta_description_url . '\' WHERE id = ' . $matches[1]);
                    } elseif (empty($url) and empty($title)) {
                        $wpdb->query('DELETE FROM ' . $table_name . ' WHERE id = ' . $matches[1]);
                    }
                } elseif (preg_match('/^url_new_(\d+)$/', $name, $matches)) {
                    $url = stripslashes($value);
                    $url = esc_sql($url);

                    $title = stripslashes(strip_tags($_POST['title_new_' . $matches[1]]));
                    $title = esc_sql($title);
                    //for url description insert
                    $meta_description_url = stripslashes(strip_tags($_POST['meta_description_new_' . $matches[1]]));
                    $meta_description_url = esc_sql($meta_description_url);

                    if ((!empty($url)) and (!empty($title))) {
                        $wpdb->query('INSERT INTO ' . $table_name . ' (url,title,description) VALUES (\'' . $url . '\',\'' . $title . '\',\'' . $meta_description_url . '\')');
                    }
                }
            }

            echo '<div class="updated"><p>The custom URLs and URL titles and description have been saved.</p></div>';

            // Filter by Search Value
        } elseif (isset($_POST['search_value'])) {
            $search_value = stripslashes(strip_tags($_POST['search_value']));
        }

        // If no search value from POST check for value in GET
        if (!isset($_POST['search_value']) && isset($_GET['search_value'])) {
            $search_value = stripslashes(strip_tags($_GET['search_value']));
        }

        $title_tags_type = stripslashes(strip_tags( isset($_GET['title_tags_type']) ? $_GET['title_tags_type'] : "" ));
        $page_no = intval(isset($_GET['page_no']) ? $_GET['page_no'] : 1);
        $manage_elements_per_page = get_option("manage_elements_per_page");
        $element_count = 0;

        if (empty($title_tags_type)) {
            $title_tags_type = 'pages';
        }

        if (empty($manage_elements_per_page)) {
            $manage_elements_per_page = 15;
        }

        $_SERVER['QUERY_STRING'] = preg_replace('/&title_tags_type=[^&]+/', '', $_SERVER['QUERY_STRING']);
        $_SERVER['QUERY_STRING'] = preg_replace('/&page_no=[^&]+/', '', $_SERVER['QUERY_STRING']);
        $_SERVER['QUERY_STRING'] = preg_replace('/&search_value=[^&]*/', '', $_SERVER['QUERY_STRING']);
        $search_query_string = '&search_value=' . $search_value;

        if (!$page_no) {
            $page_no = 0;
        }
        ?>

    <div class="wrap">

        <form  id="posts-filter" action="" method="post">
            <h2>SEO Title Tags</h2>

            <p id="post-search">
                <label class="hidden" for="search_value">Search Title Tags:</label>
                <input type="text" id="search_value" name="search_value" value="<?php if (isset($search_value)) {
    echo esc_html($search_value);
} ?>" />
                <input type="submit" value="Search Title Tags" class="button" />

            </p>

            <p><a href="options-general.php?page=seo-title-tag">Edit main SEO Title Tag plugin options &raquo;</a></p>

            <br class="clear" />

        </form>
        <!-- csv upload in tool page -->
<?php

        // Functions for csv file upload
        if (isset($_POST['upload'])) {
            $this->upload();
        }

?>
        <!-- End Funcations for csv file upload -->
        <?php $url = plugins_url(); ?>
        <h3>Upload Titles and Meta Descriptions directly through a CSV File:</h3>
        <!-- for csv file upload -->
        <form enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]; ?>'  method='post'>
            <table border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td scope="row">Download current CSV file for editing:</td>
                    <td><input type='submit' name='download' value='Download'></td>
                </tr>
                <tr>
                    <td scope="row">Select a CSV file to upload:</td>
                    <td><input size='30' type='file' name='filename'>
                        <input type='submit' name='upload' value='Upload'> <br/>

                    </td>
                </tr>
            </table>
        </form>
        <!-- end csv upload in tool page -->

<?php
//do the nav menu items for the subsubmenu
if (empty($_REQUEST['title_tags_type'])) {
    $_REQUEST['title_tags_type'] = 'pages';
}

echo '<ul id="subsubmenu">' . "\n";
echo '<li ' . $this->is_current($_REQUEST['title_tags_type'], 'pages') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=pages">Pages</a></li>' . "\n";
echo '<li ' . $this->is_current($_REQUEST['title_tags_type'], 'posts') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=posts">Posts</a></li>' . "\n";
echo '<li ' . $this->is_current($_REQUEST['title_tags_type'], 'categories') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=categories">Categories</a></li>' . "\n";
echo '<li ' . $this->is_current($_REQUEST['title_tags_type'], 'tags') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=tags">Tags</a></li>' . "\n";
echo '<li ' . $this->is_current($_REQUEST['title_tags_type'], 'urls') . '><a href="?' . $_SERVER['QUERY_STRING'] . '&title_tags_type=urls">URLs</a></li>' . "\n";
echo '</ul>' . "\n";

// Render Page and Post Tabs
if ($title_tags_type == 'pages' || $title_tags_type == 'posts') {
  // form edit pages
  // form edit posts

    $post_type = substr($title_tags_type, 0, -1); // Database table uses singular version
    ?>
            <p>Use the form below to enter or update a custom <?php echo $post_type; ?> title.<br /></p>
    <?php
$limit = 10; # default
    if (empty($search_value)) {
        if ($page_no > 0) {
            // $limit = ' LIMIT ' . ($page_no * $manage_elements_per_page) . ', ' . $manage_elements_per_page;
            $limit = 10;
        } else {
            //  $limit = ' LIMIT ' . $manage_elements_per_page;
            $limit = 10;
        }

        $posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' AND post_status IN (\'publish\', \'draft\') ORDER BY ID ASC' );
        #$posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' ORDER BY menu_order ASC' . ('posts' == $title_tags_type ? ', post_date DESC' : ', ID ASC') );
        #$posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' ORDER BY menu_order ASC' . ('posts' == $title_tags_type ? ', post_date DESC' : ', ID ASC') . $limit);
        #error_log( print_r( $posts, 1 ) );

    } else {

        $posts = $wpdb->get_results('SELECT * FROM ' . $wpdb->posts . ' WHERE post_type = \'' . $post_type . '\' ORDER BY menu_order ASC' . ('posts' == $title_tags_type ? ', post_date DESC' : ', ID ASC'));
        $new_posts;

        foreach ($posts as $post) {
            if (isset($post->post_type) and ($post->post_type != $post_type)) {
                continue;
            }

            if (empty($search_value)) {
                // No search value, add all
                $new_posts[] = $post;
            } else {
                // Filter based on search value
                if (preg_match('/' . $search_value . '/i', $post->post_title)) {
                    $new_posts[] = $post;
                } else {
                    $post_custom = get_post_custom($post->ID);

                    if (
                        preg_match('/' . $search_value . '/i', $post_custom[$this->current_title_tag_key][0]) ||
                        preg_match('/' . $search_value . '/i', $post->post_content) ||
                        preg_match('/' . $search_value . '/i', $post->post_excerpt)
                    ) {
                        $new_posts[] = $post;
                    }
                }
            }
        }

        $posts = $new_posts;
        $element_count = count($posts);

        if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
            if ($page_no > 0) {
                $posts = array_splice($posts, ($page_no * $manage_elements_per_page));
            }

            $posts = array_slice($posts, 0, $manage_elements_per_page);
        }
    }

    if ($posts) {
        ?>
                <form name="posts-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                <?php
                if (function_exists('wp_nonce_field')) {
                    wp_nonce_field('seo-title-tag-action_posts-form');
                }
                ?>
                    <input type="hidden" name="action" value="<?php echo $title_tags_type; ?>" />
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Title</th>
                                <th scope="col">Custom Title and Meta Description</th>
                                <th scope="col">Slug</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php
                $this->manage_seo_title_tags_recursive($title_tags_type, $posts);

                echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
            } else {
                echo '<p><b>No ' . $title_tags_type . ' found!</b></p>';
            }

            // Render Categories Tab
        } elseif ($title_tags_type == 'categories' || $title_tags_type == 'tags') {
          // form edit categories
          // form edit tags

            $singular = ('tags' == $title_tags_type ? 'tag' : 'category');

            $taxonomy = ('tags' == $title_tags_type ? 'post_tag' : 'category');
            ?>
                    <p>Use the form below to enter or update a custom <?php echo $singular; ?> title.<br /></p>
    <?php

            $terms = array();

            if ( $this->yoast_enabled ) {
                $meta = array();
                if ( $this->yoast_enabled ) {
                    $yoastmeta = get_option( 'wpseo_taxonomy_meta' );
                    foreach ($yoastmeta as $type => $data) {
                        foreach ($data as $id => $record) {
                            $meta[$type][$id]['title'] = $record['wpseo_title'];
                            $meta[$type][$id]['description'] = $record['wpseo_desc'];
                        }
                    }
                }

                # $meta['category'][$id]['title']
                # $meta['category'][$id]['description']

                # $meta['post_tag'][$id]['title']
                # $meta['post_tag'][$id]['description']
                #

                $data = array();

                if ( 'tags' === $title_tags_type ) {

                  $tags = get_tags();

                  foreach ($tags as $tag) {
                    #error_log( print_r( $category, 1 ));

                    $id = $tag->term_id;
                    $title = $tag->name;
                    $slug = $tag->slug;
                    $seo_title = isset($meta['post_tag'][$id]['title']) ? $meta['post_tag'][$id]['title'] : "";
                    $seo_description = isset($meta['post_tag'][$id]['description']) ? $meta['post_tag'][$id]['description'] : "";

                    $data[$id]['id'] = $id;
                    $data[$id]['title'] = $title;
                    $data[$id]['seo_title'] = $seo_title;
                    $data[$id]['seo_description'] = $seo_description;
                    $data[$id]['slug'] = $slug;
                  }

                } elseif ( 'categories' === $title_tags_type ) {

                  $categories = get_categories();

                  foreach ($categories as $category) {
                    #error_log( print_r( $category, 1 ));

                    $id = $category->term_id;
                    $title = $category->name;
                    $slug = $category->slug;
                    $seo_title = isset($meta['category'][$id]['title']) ? $meta['category'][$id]['title'] : "";
                    $seo_description = isset($meta['category'][$id]['description']) ? $meta['category'][$id]['description'] : "";

                    $data[$id]['id'] = $id;
                    $data[$id]['title'] = $title;
                    $data[$id]['seo_title'] = $seo_title;
                    $data[$id]['seo_description'] = $seo_description;
                    $data[$id]['slug'] = $slug;
                  }

                }

                ksort( $data ); # order by $id

                $terms = $data;

                // Yoast SEO part finished

            } else {

                // Yoast SEO is not installed, normal part

                $terms = $this->seo_title_tag_get_taxonomy($taxonomy);
                $table_name = $wpdb->prefix . "seo_title_tag_" . $singular;
                $term_titles = array();
                /*
                  if (get_option("use_category_description_as_title") && 'categories' == $title_tags_type) {


                  foreach ($terms as $category) {
                  print_r($term_titles[$category->term_id] = $category->category_description );
                  }
                  } else { */
                // defult filling of the category titles field.
                $sql = 'SELECT ' . $singular . '_id as term_id, title,description FROM ' . $table_name;

                $results = $wpdb->get_results($sql);
                $term_titles = array();
                $term_description = array();

                foreach ($results as $term) {
                    $term_titles[$term->term_id] = $term->title;
                    $term_description[$term->term_id] = $term->description;
                }

                $terms_new = array();

                  if ($terms) {
                      foreach ($terms as $term) {
                          $term->title = (isset($term_titles[$term->term_id]) ? $term_titles[$term->term_id] : '');
                          $term->description = (isset($term_description[$term->term_id]) ? $term_description[$term->term_id] : '');

                          if (empty($search_value)) {
                              $terms_new[] = $term;
                          } else {
                              if (
                                  preg_match('/' . $search_value . '/i', $term->title) ||
                                  preg_match('/' . $search_value . '/i', $term->name)
                              ) {
                                  $terms_new[] = $term;
                              }
                          }
                      }

                      $terms = $terms_new;
                  }

                // Yoast SEO is not installed, normal part, finished

              // }
            }


    $element_count = count($terms);

    if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
        if ($page_no > 0) {
            $terms = array_splice($terms, ($page_no * $manage_elements_per_page));
        }

        $terms = array_slice($terms, 0, $manage_elements_per_page);
    }

        # add data to output terms array
        $terms_new = array();
        foreach ( $terms as $id => $record ) {
          $terms_new[] = $record;
        }
        $terms = $terms_new;

    if ($terms) {
        ?>
                        <form name="categories-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                        <?php
                        if (function_exists('wp_nonce_field')) {
                            wp_nonce_field('seo-title-tag-action_taxonomy-form');
                        }
                        ?>
                            <input type="hidden" name="action" value="<?php echo $title_tags_type; ?>" />
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col"><?php echo ucfirst($singular); ?></th>
                                        <th scope="col">Custom Title and Meta Description</th>
                                        <th scope="col">Slug</th>
                                    </tr>
                                </thead>
                                <tbody>
                        <?php
                        //jjj1
                        foreach ($terms as $id => $term) {
                            $term_href = ('tags' == $title_tags_type ? get_tag_link($term['id']) : get_category_link($term['id']));
                            ?>
                                        <tr>

                                            <td><a href="<?php echo $term_href ?>" target="_blank"><?php echo $term['id'] ?></a></td>
                                            <td><?php echo $term['title'] ?></td>
                                            <td><span style="width:110px;float:left; padding-right: 5px;">Title </span><input type="text" name="title_<?php echo $term['id'] ?>" value="<?php echo esc_html($term['seo_title']); ?>" size="80" /><br />
                                                <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" name="description_<?php echo $term['id'] ?>" value="<?php echo esc_html($term['seo_description']); ?>" size="80" />

                                            </td>
                                            <td><input type="text" name="slug_<?php echo $term['id'] ?>" id="slug_<?php echo $term['id'] ?>" value="<?php echo esc_html($term['slug']); ?>" size="20" /></td>
                                <?php
                            }

                            echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
                        } else { //End of check for terms
                            print "<b>No " . ucfirst($title_tags_type) . " found!</b>";
                        }
                    } elseif ($title_tags_type == 'urls') {
                      // form edit urls

                        ?>
                            <p>Use the form below to enter or update a title tag for any URL, including archives pages, tag conjunction pages, etc.</p><p>In the URL field, leave off the http:// and your domain and your blog's directory (if you have one). e.g. <i>tag/seo+articles</i> is okay; <i>http://www.netconcepts.com/tag/seo+articles</i> is NOT.<br /></p>
    <?php
    $table_name = $wpdb->prefix . "seo_title_tag_url";
    $urls;

    $sql = 'SELECT id, url, title,description from ' . $table_name;

    if (!empty($search_value)) {
        $sql .= ' WHERE url LIKE "%' . esc_sql($search_value) . '%" OR title LIKE "%' . esc_sql($search_value) . '%"';
    }

    $sql .= ' ORDER BY title';
    $urls = $wpdb->get_results($sql);
    $element_count = count($urls);

    if (($element_count > $manage_elements_per_page) and (($page_no != 'all') or empty($page_no))) {
        if ($page_no > 0) {
            $urls = array_splice($urls, ($page_no * $manage_elements_per_page));
        }

        $urls = array_slice($urls, 0, $manage_elements_per_page);
    }
    ?>
                            <form name="urls-form" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                            <?php
                            if (function_exists('wp_nonce_field')) {
                                wp_nonce_field('seo-title-tag-action_urls-form');
                            }
                            ?>
                                <input type="hidden" name="action" value="urls" />
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">URL</th>
                                            <th scope="col">Custom Title and Meta Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            <?php
                            if (is_array($urls)) {
                                foreach ($urls as $url) {
                                    $url_value = $url->title;
                                    $url_value_meta_description = $url->description;

                                    if (get_magic_quotes_runtime()) {
                                        $url_value = stripslashes($url_value);
                                    }
                                    ?>
                                                <tr>
                                                    <td><a href="/<?php echo preg_replace('/^\//', '', $url->url) ?>">view link</a></td>
                                                    <td><input type="text" title="<?php echo esc_html($url->url) ?>" name="url_<?php echo $url->id ?>" value="<?php echo esc_html($url->url) ?>" size="40" /></td>
                                                    <td>
                                                        <span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" title="<?php echo esc_html($url->title, true) ?>" name="title_<?php echo $url->id ?>" value="<?php echo esc_html($url_value); ?>" size="70" /><br />
                                                        <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" title="<?php echo esc_html($url->description) ?>" name="meta_description_<?php echo $url->id ?>" value="<?php echo esc_html($url_value_meta_description); ?>" size="70" />

                                                    </td>
                                                </tr>
            <?php
        }
    }

    for ($n = 0; $n < 5; $n++) {
        ?>
                                            <tr>
                                            <td>New <!-- (<?php // echo ($n + 1)  ?>) --> </td>
                                                <td><input type="text" name="url_new_<?php echo $n ?>" value="" size="40" /></td>
                                                <td><span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" name="title_new_<?php echo $n ?>" value="" size="70" /><br />
                                                    <span style="width:110px;float:left; padding-right: 5px;"> Meta Description</span><input type="text" name="meta_description_new_<?php echo $n ?>" value="" size="70" />
                                                </td>
                                            </tr>
                                            <?php
                                        }

                                        echo '</table><br /><input type="submit" class="button" value="Submit" /></form>';
                                    } else {
                                        echo '<p>unknown title tags type!</p>';
                                    }
                                    ?>

<?php
if ($element_count > $manage_elements_per_page) {
    if (($page_no == 'all') and (!empty($page_no))) {
        //  echo 'View All&nbsp;&nbsp;';
    } else {
        // echo '<a href="'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&page_no=all&title_tags_type='.$title_tags_type.$search_query_string.'">View All</a>&nbsp;&nbsp;';
    }
}
?>
                                <style>
                                    .pagination{float:right;}
                                    .pagination a{border:1px solid #ccc;padding:4px 8px;}
                                </style>
<?php
echo "<div class='pagination'>";

if ($element_count > $manage_elements_per_page) {
    $max = (int) ceil($element_count / $manage_elements_per_page);
    $inital = 0;

    if ($max > 5) {
        $max = 5;
    }

    if ($element_count > ($page_no * $manage_elements_per_page) && $page_no >= 4) {
        $inital = $page_no - (($page_no + 1) % 5);
        $max = (int) ceil($element_count / $manage_elements_per_page);

        if ($max > ($inital + 5)) {
            $max = $inital + 5;
        }

        echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . ($page_no - 1) . '&title_tags_type=' . $title_tags_type . $search_query_string . '"><< Prev</a> ';
    }

    for ($p = $inital; $p < $max; $p++) {
        if ($page_no == $p) {
            echo ($p + 1) . '&nbsp;';
        } else {
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . $p . '&title_tags_type=' . $title_tags_type . $search_query_string . '">' . ($p + 1) . '</a> ';
        }
    }

    if ($page_no != ($max - 1) && $max >= 5) {
        echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '&page_no=' . ($page_no + 1) . '&title_tags_type=' . $title_tags_type . $search_query_string . '">Next >> </a> ';
    }
}

echo "</div>";
?>
                                </div>
                                <?php
    }

    public function manage_seo_title_tags_recursive($type, $elements = 0)
    {
        if (!$elements) {
            return;
        }

        $cache = array();

        foreach ($elements as $element) {
            $level = 0;

            $element_custom = get_post_custom($element->ID);
            #error_log( $element->ID );

            $pad = str_repeat('&#8212; ', $level);
            $element_value = isset($element_custom[$this->current_title_tag_key]) ? $element_custom[$this->current_title_tag_key][0] : "";
            $element_value_meta = isset($element_custom[$this->current_meta_description_key]) ? $element_custom[$this->current_meta_description_key][0] : "";

            if (get_magic_quotes_runtime()) {
                $element_value = stripslashes($element_value);
            }

            if (get_magic_quotes_runtime()) {
                $element_value_meta = stripslashes($element_value_meta);
            }
            $draft = "";
            if ( $element->post_status === 'draft' ) {
              $draft = " (draft)";
            }
            ?>
            <tr>
                <td><a href="<?php echo get_permalink($element->ID) ?>" target="_blank"><?php echo $element->ID ?></a></td>
                <td><?php echo $pad . $element->post_title . $draft ?></td>
                <td><span style="width:110px;float:left; padding-right: 5px;">Title</span><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="tagtitle_<?php echo $element->ID ?>" id="tagtitle_<?php echo $element->ID ?>" value="<?php echo esc_html($element_value); ?>" size="70" /><br />
                    <!--meta description -->
                    <span style="width:110px;float:left; padding-right: 5px;">Meta Description</span><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="tagdescription_<?php echo $element->ID ?>" id="tagdescription_<?php echo $element->ID ?>" value="<?php echo esc_html($element_value_meta); ?>" size="70" />
                </td>

            <?php if ('pages' == $type || 'posts' == $type): ?>
                    <td><input type="text" title="<?php echo esc_html($element->post_title) ?>" name="post_name_<?php echo $element->ID ?>" id="post_name_<?php echo $element->ID ?>" value="<?php echo esc_html($element->post_name); ?>" size="20" /></td>
            <?php endif; ?>
            <?php
        }
    }

    // returns class=current if the strings exist and match else nothing.
    // Used down on the top nav to select which page is selected.

    public function is_current($aRequestVar, $aType)
    {
        if (!isset($aRequestVar) || empty($aRequestVar)) {
            return;
        }

        //do the match
        if ($aRequestVar == $aType) {
            return 'class=current';
        }
    }

    #-------------------------------------------------------------------------

    public function download() {
      if ( isset( $_POST['download'] ) ) {

# jjj - what to put as title/description, if seo title and seo description not set

        # 1. download pages
        # 2. download posts
        # 3. download categories
        # 4. download tags
        # 5. download urls

        # global $wpdb;
        # $wp_postmeta = $wpdb->prefix . "postmeta";

        $csv = array();

        #---------------------------------------------------------------------
        # 1. download pages

        $data = array();
        $pages = get_pages( array( "post_status" => "publish,draft" ) ); // all pages
        foreach ($pages as $page) {
          $id = $page->ID;
          $title = $page->post_title;
          if ( "draft" === $page->post_status ) {
            $title .= " (draft)";
          }
          $slug = $page->post_name;
          $seo_title = get_post_meta( $id, $this->current_title_tag_key, true );
          $seo_description = get_post_meta( $id, $this->current_meta_description_key, true );

          $data[$id]['id'] = "page_" . $id;
          $data[$id]['title'] = $title;
          $data[$id]['seo_title'] = $seo_title;
          $data[$id]['seo_description'] = $seo_description;
          $data[$id]['slug'] = $slug;
        }
        ksort( $data ); # order by $id

        # add data to output csv array
        foreach ( $data as $id => $record ) {
          $csv[] = $record;
        }

        # id
        # title
        # seo_title
        # seo_description
        # slug

        #---------------------------------------------------------------------
        # 2. download posts

        $data = array();
        $posts = get_posts( array( "post_status" => "publish,draft", "numberposts" => "-1" ) ); // all posts

        foreach ($posts as $post) {
          $id = $post->ID;
          $title = $post->post_title;
          if ( "draft" === $post->post_status ) {
            $title .= " (draft)";
          }
          $slug = $post->post_name;
          $seo_title = get_post_meta( $id, $this->current_title_tag_key, true );
          $seo_description = get_post_meta( $id, $this->current_meta_description_key, true );

          $data[$id]['id'] = "post_" . $id;
          $data[$id]['title'] = $title;
          $data[$id]['seo_title'] = $seo_title;
          $data[$id]['seo_description'] = $seo_description;
          $data[$id]['slug'] = $slug;
        }
        ksort( $data ); # order by $id

        # add data to output csv array
        foreach ( $data as $id => $record ) {
          $csv[] = $record;
        }

        #---------------------------------------------------------------------
        # 3. download categories

        $meta = array();
        if ( $this->yoast_enabled ) {
            $yoastmeta   = get_option( 'wpseo_taxonomy_meta' );
            foreach ($yoastmeta as $category => $data) {
                foreach ($data as $id => $record) {
                    $meta[$category][$id]['title'] = isset($record['wpseo_title']) ? $record['wpseo_title'] : "";
                    $meta[$category][$id]['description'] = isset($record['wpseo_desc']) ? $record['wpseo_desc'] : "";
                }
            }
        }

        # $meta['category'][$id]['wpseo_title']
        # $meta['category'][$id]['wpseo_desc']

        # $meta['post_tag'][$id]['wpseo_title']
        # $meta['post_tag'][$id]['wpseo_desc']

        $data = array();
        $categories = get_categories();

        foreach ($categories as $category) {
          #error_log( print_r( $category, 1 ));

          $id = $category->term_id;
          $title = $category->name;
          $slug = $category->slug;
          $seo_title = isset($meta['category'][$id]['title']) ? $meta['category'][$id]['title'] : "";
          $seo_description = isset($meta['category'][$id]['description']) ? $meta['category'][$id]['description'] : "";

          $data[$id]['id'] = "category_" . $id;
          $data[$id]['title'] = $title;
          $data[$id]['seo_title'] = $seo_title;
          $data[$id]['seo_description'] = $seo_description;
          $data[$id]['slug'] = $slug;
        }
        ksort( $data ); # order by $id

        # add data to output csv array
        foreach ( $data as $id => $record ) {
          $csv[] = $record;
        }

        #---------------------------------------------------------------------
        # 4. download tags

        $data = array();
        $tags = get_tags();

        foreach ($tags as $tag) {
          #error_log( print_r( $tag, 1 ));

          $id = $tag->term_id;
          $title = $tag->name;
          $slug = $tag->slug;
          $seo_title = isset($meta['post_tag'][$id]['title']) ? $meta['post_tag'][$id]['title'] : "";
          $seo_description = isset($meta['post_tag'][$id]['description']) ? $meta['post_tag'][$id]['description'] : "";

          $data[$id]['id'] = "tag_" . $id;
          $data[$id]['title'] = $title;
          $data[$id]['seo_title'] = $seo_title;
          $data[$id]['seo_description'] = $seo_description;
          $data[$id]['slug'] = $slug;
        }
        ksort( $data ); # order by $id

        # add data to output csv array
        foreach ( $data as $id => $record ) {
          $csv[] = $record;
        }

        #---------------------------------------------------------------------
        # 5. download urls

        global $wpdb;
        $table_name = $wpdb->prefix . "seo_title_tag_url";

        $sql = 'SELECT id, url, title,description from ' . $table_name;
        $sql .= ' ORDER BY url';
        $urls = $wpdb->get_results($sql);

        $data = array();

        foreach ($urls as $order => $url) {
          #error_log( print_r( $url, 1 ));

          $id = $url->id;
          $title = $url->title;
          $slug = $url->url;
          $seo_title = $url->title;
          $seo_description = $url->description;

          #$data[$id]['id'] = "url_" . $id;
          $data[$order]['id'] = "url";
          $data[$order]['title'] = $title;
          $data[$order]['seo_title'] = $seo_title;
          $data[$order]['seo_description'] = $seo_description;
          $data[$order]['slug'] = $slug;
        }
        ksort( $data ); # order by $id

        # add data to output csv array
        foreach ( $data as $id => $record ) {
          $csv[] = $record;
        }

        #---------------------------------------------------------------------
        # send file to user

    // get domain, removing 'www.'
    $domain = $_SERVER['SERVER_NAME'];
    $domain = preg_replace( '/^www\./', '', $domain );

    // If $domain allowed to enter filename, add "." at the end
    $domain .= ".";
    // If not allowed, make it empty string
    // $domain = "";
    
        $csv_filename = 'Seo-Title-Tag.' . $domain . date('Ymd-Hi') . '.csv';
        #$csv_header_array = array("Post Id", "Title", "Meta Description", "Slug");
        $csv_header_array = array("id", "Slug", "Title", "SEO Title", "SEO Description");

        # new
        if ( isset( $csv ) ) {

          if ( $this->download_as_file ) {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
            header('Content-Type: text/csv; charset=' . get_option('blog_charset'), true);
          } else {
            header('Content-Type: text/plain; charset=' . get_option('blog_charset'), true);
          }

            $csvfile = fopen('php://output', 'w') or show_error("Can't open php://output");
            $n = 0;

            if (isset($csv_header_array)) {
                if (!fputcsv($csvfile, $csv_header_array, ',')) {
                    echo "Can't write line $n: $line";
                }
            }

            foreach ($csv as $data) {
                $n++;

                if (isset($data['id'])) {
                    $csv_line = array($data['id'], $data['slug'], $data['title'], $data['seo_title'], $data['seo_description']);

                    if (!fputcsv($csvfile, $csv_line, ',')) {
                        echo "Can't write line $n: $line";
                    }
                }
            }

            fclose($csvfile) or show_error("Can't close php://output");

            exit;
        }

      }
      # function download() ...
    }

    #-------------------------------------------------------------------------

    public function upload() {

      if (isset($_POST['upload'])) {
        global $wpdb;

        # 1. upload pages
        # 2. upload posts
        # 3. upload categories
        # 4. upload tags
        # 5. upload urls

        # jjj

            $allowedExts = array("csv");
            $tmp = explode(".", $_FILES['filename']['name']);
            $extension = end($tmp);

            if (($_FILES['filename']['type'] == "text/csv") || in_array($extension, $allowedExts)) {
                if ($_FILES['filename']['error'] > 0) {
                    echo "Error: " . $_FILES['filename']['error'] . "<br />";
                } else {
                    if (is_uploaded_file($_FILES['filename']['tmp_name'])) {
                        echo "<h3 style='color:green;'>" . "File " . $_FILES['filename']['name'] . " uploaded successfully." . "</h3>";
                    }

                    // remove all URLS from database ( we will insert it during process )
                    global $wpdb;
                    $table_name = $wpdb->prefix . "seo_title_tag_url";
                    $sql = 'DELETE FROM ' . $table_name;
                    $urls = $wpdb->get_results($sql);

                    //Import uploaded file to Database
                    $handle = fopen($_FILES['filename']['tmp_name'], "r");
                    $i = 0;

                    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                        $num = count($data);
                        $i++;

                        // skip CSV header with field names
                        if ( 1 === $i ) {
                          continue;
                        }

                        #error_log("DATA:");
                        #error_log( print_r( $data, 1 ) );

                        $id = $data[0];
                        $slug = $data[1];
                        $title = $data[2];
                        $seo_title = $data[3];
                        $seo_description = $data[4];

                        list( $type, $id ) = explode( '_', $id . "_" );
                        #error_log( "[$type] [$id]" );

                        if ( 'page' === $type || 'post' == $type ) {

                          update_post_meta( $id, $this->current_title_tag_key, $seo_title );
                          update_post_meta( $id, $this->current_meta_description_key, $seo_description );

                          // update the post slug
                          wp_update_post( array(
                              'ID' => $id,
                              'post_name' => $slug,
                          ));

                        } elseif ( 'category' === $type ) {
                          // jjj

                          if ( $this->yoast_enabled ) {

                              $yoastmeta   = get_option( 'wpseo_taxonomy_meta' );
                              # $id
                              # $title
                              # $seo_title
                              # $seo_description
                              # $slug
                              $yoastmeta['category'][$id]['wpseo_title'] = $seo_title;
                              $yoastmeta['category'][$id]['wpseo_desc'] = $seo_description;
                              update_option( 'wpseo_taxonomy_meta', $yoastmeta );

                              // update the category slug
                              wp_update_term( $id, 'category', array(
                                  'slug' => $slug,
                              ));

                          }

                        } elseif ( 'tag' === $type ) {

//jjj2
                          if ( $this->yoast_enabled ) {

                              $yoastmeta   = get_option( 'wpseo_taxonomy_meta' );
                              # $id
                              # $title
                              # $seo_title
                              # $seo_description
                              # $slug
                              $yoastmeta['post_tag'][$id]['wpseo_title'] = $seo_title;
                              $yoastmeta['post_tag'][$id]['wpseo_desc'] = $seo_description;
                              update_option( 'wpseo_taxonomy_meta', $yoastmeta );

                              // update the tag slug
                              wp_update_term( $id, 'post_tag', array(
                                  'slug' => $slug,
                              ));

                          }
                        } elseif ( 'url' === $type ) {

//jjjurlimport
                          //jjj - currently we are deleting all records, and then updating new ones
                          //jjj - maybe we should keep unchanged records, and update/delete/add new records ?

                              # $id
                              # $title
                              # $seo_title
                              # $seo_description
                              # $slug

                            $url = esc_sql($slug);
                            $title = esc_sql($seo_title);
                            $meta_description_url = esc_sql($seo_description);

                            //for url description insert

                            if ((!empty($url)) and (!empty($title))) {
                                $wpdb->query('INSERT INTO ' . $table_name . ' (url,title,description) VALUES (\'' . $url . '\',\'' . $title . '\',\'' . $meta_description_url . '\')');
                            }

                        } else {
                          // jjj - error, unknown type in source document
                        }

                        continue;

                        if ($i > 1) {
                        } else {
                            echo $sucess_msg = "<h3 style='color:green;'>CSV File Data Successfully Inserted.</h3>";
                        }

                        $i++;
                    }

                    fclose($handle);
                }
            } else {
                echo "<h2 style='color:red';>Invalid File! Please Select Valid CSV File to upload</h2>";
            }
        }
        # function upload() ...
    }

    #-------------------------------------------------------------------------

    public function seo_title_tag_get_taxonomy($taxonomy)
    {
        global $wpdb, $wp_version;

        $results = $wpdb->get_results("
            SELECT
                tt.term_id,
                t.name,
                t.slug,
                tt.description,
                tt.parent,
                tt.count
            FROM
                " . $wpdb->term_taxonomy . " tt
                INNER JOIN " . $wpdb->terms . " t
                ON tt.term_id = t.term_id
            WHERE
                tt.taxonomy = '$taxonomy'
            ORDER BY
                t.name"
        );

        $terms = array();

        foreach ($results as $term) {
            $terms[$term->term_id] = $term;
        }

        return $terms;
    }

    #-------------------------------------------------------------------------

    // OK:
    public function admin_custom_css() {
       wp_enqueue_style( 'seo-title-tag-style-1', plugin_dir_url( __FILE__ ) . 'admin-2.5.css' );

    }

    #-------------------------------------------------------------------------

    /// For RSS FEEDs
    function update_title_rss($content)
    {
        global $wp_query;

        $postid = $wp_query->post->ID;
        $post_title = get_the_title($postid);

        $title_tag_key = $this->current_title_tag_key;
        $content = get_post_meta($postid, $title_tag_key, true);

        if ($content == '') {
            $content = $post_title;
        }

        return $content;
    }

    #-------------------------------------------------------------------------

    // This fixes how wordpress 2.3 only shows the first tag name when you view
    // Taxonomy Intersections and Unions

    public function seo_title_tag_filter_single_tag_title($prefix = '', $display = true)
    {
        global $wp_query, $wpdb;

        $tags = explode(' ', str_replace(',', ' ,', $wp_query->query_vars['tag']));
        $tag_title = '';

        foreach (array_keys($tags) as $k) {
            if (0 == $k) {
                $prefix = '';
            } elseif (',' == $tags[$k][0]) {
                $prefix = ' or ';
                $tags[$k] = substr($tags[$k], 1);
            } else {
                $prefix = ' and ';
            }

            $sql = "SELECT
                t.name
            FROM
                " . $wpdb->terms . " t INNER JOIN " . $wpdb->term_taxonomy . " tt
                ON t.term_id = tt.term_id
            WHERE
                t.slug = '" . esc_sql($tags[$k]) . "' AND
                tt.taxonomy = 'post_tag'
            LIMIT 1";

            $temp = $wpdb->get_results($sql);

            if (is_array($temp) && isset($temp[0])) {
                $tag_title .= $prefix . $temp[0]->name;
            }
        }

        return $tag_title;
    }

    #-------------------------------------------------------------------------

    public function seo_edit_page_form()
    {
        global $post;

        // if Yoast SEO is installed and active, do not add and do not use your own postmeta data at page/post editor
        if ( $this->yoast_enabled ) {
          return;
        }

        $custom_title_value = get_post_meta($post->ID, $this->current_title_tag_key, true);
        $custom_meta_description = get_post_meta($post->ID, $this->current_meta_description_key, true);
        $include_title_form = get_option("include_title_form");
        $include_meta_description_form = get_option("include_meta_description_form");
        $include_slug_form = get_option("include_slug_form");
    ?>
    <?php $url = plugins_url(); ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            var jdivHTML = jQuery('#seotitlejdiv').html();
            if (jQuery('#normal-sortables').length)
            {
                jQuery('#normal-sortables').prepend(jdivHTML);
            }
            jQuery('#seotitlejdiv').remove();
        });
    </script>

    <script type="text/javascript" src="<?php echo $url . '/seo-title-tag/charCount.js' ?>"></script>
    <script type="text/javascript" src="<?php echo $url . '/seo-title-tag/metaCount.js' ?>"></script>
    <script type="text/javascript">
        jQuery.noConflict();
        jQuery(document).ready(function() {
            //custom usage
            jQuery("#<?php echo $this->current_title_tag_key ?>").charCount({
                allowed: 0,
                warning: 0,
                counterText: 'Title Tag Character Count: '
            });
            //custom usage
            jQuery("#<?php echo $this->current_meta_description_key ?>").metaCount({
                allowed: 0,
                warning: 0,
                counterText: 'Meta Description Character Count: '
            });
        });
    </script>

    <style>

        form div{position:relative;}
        form .counter{
            position:absolute;
            right:9px;
            top:0;
            font-size:15px;
            font-weight:bold;
            color:#FFA500;
        }
        form .warning{color:#600;}
        form .exceeded{color:#e00;}
        form .yellow1{color:#FFC40F;}
        form .green{color:green;}
        form .red{color:red;}
    </style>
    <form id="form" method="Post"> <div id="seotitlejdiv">
    <?php
    //echo "Radio buttion value here".$include_title_form;
    if ($include_title_form == 1) {
        ?>
                <div id="seodiv-title" class="postbox">
                    <h3>Seo Title Tag (optional) - Enter your post/page title</h3>
                    <div class="inside">
                        <input type="text" name="<?php echo $this->current_title_tag_key ?>" value="<?php if ($custom_title_value) {
            echo esc_html($custom_title_value);
        } else {
            echo "";
        } ?>" id="<?php echo $this->current_title_tag_key ?>" size="50" />

                    </div>
                </div>
    <?php } else {
        echo "&nbsp;";
    } ?>
    <?php if ($include_meta_description_form == 1) { ?>

                <div id="seodiv-meta" class="postbox">
                    <h3>Seo Meta Description (optional) - Enter your post/page meta description</h3>
                    <div class="inside">
                        <input type="text" name="<?php echo $this->current_meta_description_key ?>" value="<?php if ($custom_meta_description) {
            echo esc_html($custom_meta_description);
        } else {
            echo "";
        } ?>" id="<?php echo $this->current_meta_description_key ?>" size="50" />
                    </div>
                </div>
            <?php } else {
                echo "&nbsp;";
            } ?>

    <?php if (false && $include_slug_form == 1) { // disabling this as it causes issues with the other post_name text field, which exists per default ?>

                <div id="seodiv-slug" style="display:none;visible:hidden" class="postbox">
                    <h3>Slug </h3>
                    <div class="inside">
                        <label class="screen-reader-text" for="post_name"><?php _e('Slug') ?></label><input name="post_name" type="text" size="50" id="post_name" value="<?php echo esc_attr(apply_filters('editable_slug', $post->post_name)); ?>" />
                    </div>
                </div>
            <?php } else {
                echo "&nbsp;";
            } ?>    </div>
            <?php
    }

    #-------------------------------------------------------------------------

    public function seo_update_title_tag($id)
    {

        // if Yoast SEO is installed and active, do not add and do not use your own postmeta data at page/post editor
        if ( $this->yoast_enabled ) {
          return;
        }

        if (isset($_POST[get_option("custom_title_key")])) {
            delete_post_meta($id, $this->current_title_tag_key);
        }

        if (isset($_POST[get_option("custom_meta_description_key")])) {
            delete_post_meta($id, $this->current_meta_description_key);
        }

        $value = $_POST[get_option("custom_title_key")];
        $value = stripslashes(strip_tags($value));
        $Value_meta = $_POST[get_option("custom_meta_description_key")];
        $Value_meta = stripslashes(strip_tags($Value_meta));

        if (!empty($value)) {
            add_post_meta($id, $this->current_title_tag_key, $value);
        }

        if (!empty($Value_meta)) {
            add_post_meta($id, $this->current_meta_description_key, $Value_meta);
        }
    }

    #-------------------------------------------------------------------------

  }

  $seotitletag = new SeoTitleTag();

};

